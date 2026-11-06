<?php

declare(strict_types=1);

namespace Vanda\Screen;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Vanda\Access\Authorizer;
use Vanda\Actions\Button;
use Vanda\Actions\ModalToggle;
use Vanda\Activity\ActivityContext;
use Vanda\Activity\ActivityLog;
use Vanda\Attributes\Action;
use Vanda\Attributes\Updated;
use Vanda\Fields\Field;
use Vanda\Fields\Relation;
use Vanda\Fields\Repeater;
use Vanda\Fields\RichText;
use Vanda\Fields\Upload;
use Vanda\Form\Form;
use Vanda\Layouts\Table;
use Vanda\Navigation\Crumb;
use Vanda\Navigation\Shell;
use Vanda\Schema\Get;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;
use Vanda\Schema\Tree;
use Vanda\State\ScreenState;
use Vanda\State\Snapshot;
use Vanda\Support\Paths;
use Vanda\VandaException;

/**
 * Runs one request of a screen: first render, a reaction or an action.
 *
 *   boot()      → #[Can] → query() with route parameters (models bound by type) → #[Can] with model
 *   restore()   → state from the signed snapshot of the previous response
 *   react()     → client edits on whitelisted paths → #[Updated] hooks → patches
 *   act()       → client edits → hooks → validation of the button's scope → #[Action] → Result
 */
final class ScreenRuntime
{
    private ?Tree $tree = null;

    /** @var list<string> paths whose browser value was changed by Field::sanitize() */
    private array $sanitized = [];

    private function __construct(
        public readonly Screen $screen,
        private readonly string $scope,
    ) {}

    /**
     * @param class-string $class
     * @param array<string, mixed> $parameters route parameters
     */
    public static function boot(string $class, array $parameters = [], string $scope = '/'): self
    {
        if (! is_subclass_of($class, Screen::class)) {
            throw VandaException::notAScreen($class);
        }

        if (! Authorizer::allowsScreenClass($class)) {
            throw new AuthorizationException(sprintf('This action is unauthorized (%s).', class_basename($class)));
        }

        /** @var Screen $screen */
        $screen = app($class);

        if (method_exists($screen, 'query')) {
            $props = app()->call([$screen, 'query'], self::bindModels($screen, $parameters));
            $screen->setProps(is_iterable($props) ? iterator_to_array($props) : []);
        }

        Authorizer::authorizeScreen($screen);

        return new self($screen, $scope);
    }

    public function restore(string $snapshot): void
    {
        ScreenState::hydrate($this->screen, Snapshot::open($snapshot, $this->screen::class, $this->scope));
        $this->tree = null;
    }

    /**
     * The part of the state the browser may edit (without #[Secret] values).
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return ScreenState::dehydrate($this->screen, clientOnly: true);
    }

    /**
     * Editable state including secrets: what validation and hooks see.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return ScreenState::dehydrate($this->screen, clientOnly: true, secrets: true);
    }

    public function snapshot(): string
    {
        return Snapshot::seal($this->screen::class, $this->scope, ScreenState::dehydrate($this->screen));
    }

    public function tree(): Tree
    {
        return $this->tree ??= new Tree(
            array_values(iterator_to_array($this->screen->commandBar(), false)),
            array_values(iterator_to_array($this->screen->layout(), false)),
        );
    }

    /**
     * Stores a file sent to an Upload field of this screen, or an image pasted into a RichText
     * with ->uploads() (multipart POST with type=upload).
     *
     * @return array{0: int, 1: array<string, mixed>} HTTP status and JSON body
     */
    public function upload(string $path, ?UploadedFile $file): array
    {
        $field = $this->editableField($path);
        // A RichText with ->uploads() stores images through an Upload of its own.
        $field = $field instanceof RichText ? $field->uploader() : $field;
        if (! $field instanceof Upload) {
            throw new HttpException(404, "There is no editable Upload field (or RichText with ->uploads()) [{$path}] on ".$this->screen::class.'.');
        }

        try {
            $attachment = $field->store($file ?? throw ValidationException::withMessages(['file' => [__('validation.required', ['attribute' => 'file'])]]));
        } catch (ValidationException $exception) {
            return [422, ['errors' => [$path => $exception->validator->errors()->all()]]];
        }

        return [200, [...$attachment->toUpload(), 'value' => $field->valueOf($attachment)]];
    }

    /**
     * Records of a Relation field matching what the user typed (JSON request with type=options).
     * The browser's values are applied first, so the field's query can read the other fields.
     *
     * @param array<string, mixed> $client
     * @return array{options: list<array{value: int|string, label: string}>}
     */
    public function options(array $client, string $path, string $term): array
    {
        $this->applyClientState($client);
        $field = $this->editableField($path);

        if (! $field instanceof Relation) {
            throw new HttpException(404, "There is no editable Relation field [{$path}] on ".$this->screen::class.'.');
        }

        return ['options' => $field->search(mb_substr($term, 0, 200), new Get($this->state()))];
    }

    /**
     * The field at this path, also inside a Repeater row ("form.slides.2.image"),
     * if it is shown and editable.
     */
    private function editableField(string $path): ?Field
    {
        $field = $this->tree()->find($path);
        $owner = $field;

        if ($field === null) {
            foreach ($this->tree()->fields() as $repeater) {
                $rest = $repeater instanceof Repeater && str_starts_with($path, $repeater->path().'.') ? substr($path, strlen($repeater->path()) + 1) : '';
                if (preg_match('/^\d+\.(.+)$/', $rest, $match) === 1) {
                    $owner = $repeater;
                    $field = array_values(array_filter($repeater->getFields(), static fn (Field $child) => $child->path() === $match[1]))[0] ?? null;
                    break;
                }
            }
        }

        return $field instanceof Field && $field->isEditable() && $owner instanceof Field && $owner->isEditable() && $this->tree()->isShown($owner, $this->state())
            ? $field
            : null;
    }

    /**
     * Every filtered row of an exportable table on this screen as CSV (GET ?export=<table id>).
     */
    public function export(string $tableId): StreamedResponse
    {
        $table = $this->tree()->find($tableId);

        abort_unless(
            $table instanceof Table && $table->isExportable() && $this->tree()->isShown($table, $this->state()),
            404,
            "Table [{$tableId}] on ".$this->screen::class.' is not exportable. Call ->exportable() on it.',
        );

        return $table->export(new SchemaContext($this->screen, $this->state()));
    }

    /**
     * The full page description sent to the renderer.
     *
     * @return array<string, mixed>
     */
    public function ir(): array
    {
        $tree = $this->tree();
        $context = new SchemaContext($this->screen, $this->state());
        $serialize = static fn (array $nodes) => array_values(array_map(
            static fn (Node $node) => $node->toSchema($context),
            array_filter($nodes, static fn (Node $node) => $node->isVisible()),
        ));

        return [
            'schema' => 'vanda/v1',
            'screen' => $this->screen::class,
            'url' => $this->scope,
            'chrome' => $this->screen->chrome()->value,
            'title' => $this->screen->title(),
            'description' => $this->screen->description(),
            'avatar' => $this->screen->avatar()?->toArray(),
            'badge' => $this->screen->badge()?->id('header.badge')->toSchema($context),
            'breadcrumbs' => array_values(array_map(
                static fn (Crumb $crumb) => $crumb->toArray(),
                iterator_to_array($this->screen->breadcrumbs(), false),
            )),
            'commandBar' => $serialize($tree->commandBar),
            'layout' => $serialize($tree->layout),
            'state' => $context->state,
            'snapshot' => $this->snapshot(),
            'app' => app(Shell::class)->toArray($this->scope),
        ];
    }

    /**
     * Applies browser values, but only to paths of editable fields that exist
     * on this screen and belong to non-locked properties.
     *
     * @param array<string, mixed> $client
     * @return list<string> paths that actually changed
     */
    public function applyClientState(array $client): array
    {
        $before = $this->state();
        $next = $this->values();

        foreach ($this->tree()->fields() as $field) {
            $path = $field->path();
            $root = explode('.', $path, 2)[0];

            if ($field->isEditable() && $field->isVisible() && ScreenState::isClientProperty($this->screen, $root) && Arr::has($client, $path)) {
                $raw = Arr::get($client, $path);
                $clean = $field->sanitize($raw, Arr::get($next, $path));
                Arr::set($next, $path, $clean);

                if ($clean !== $raw) {
                    $this->sanitized[] = $path;
                }
            }
        }

        ScreenState::hydrate($this->screen, $next, clientOnly: true);
        $this->tree = null;

        return Paths::diff($before, $this->state());
    }

    /**
     * @param list<string> $changed
     */
    public function runUpdatedHooks(array $changed): void
    {
        if ($changed === []) {
            return;
        }

        foreach ((new \ReflectionClass($this->screen))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Updated::class) as $attribute) {
                $pattern = $attribute->newInstance()->path;

                foreach ($changed as $path) {
                    if (Paths::matches($pattern, $path)) {
                        app()->call([$this->screen, $method->getName()], [
                            'value' => data_get($this->values(), $path),
                            'path' => $path,
                        ]);
                        $this->tree = null;
                        break;
                    }
                }
            }
        }
    }

    /**
     * Server reaction to edits: returns patches for the nodes that depend on
     * the changed paths, for state the hooks changed, and live validation errors.
     *
     * @param array<string, mixed> $client
     * @return array{patches: list<array{op: string, path: string, value: mixed}>, errors: array<string, list<string>>, snapshot: string}
     */
    public function react(array $client): array
    {
        $changed = $this->applyClientState($client);
        $afterClient = $this->state();
        $this->runUpdatedHooks($changed);
        $state = $this->state();
        // Paths changed by hooks, plus browser values the server had to clean up.
        $byServer = array_values(array_unique([...Paths::diff($afterClient, $state), ...$this->sanitized]));
        $affected = array_values(array_unique([...$changed, ...$byServer]));

        $context = new SchemaContext($this->screen, $state);
        $tree = $this->tree();
        $patches = [];

        foreach ($tree->dependents($affected) as $node) {
            $patches[] = [
                'op' => 'replace',
                'path' => '/nodes/'.$node->getId(),
                'value' => $node->isVisible() ? $node->toSchema($context) : null,
            ];
        }

        foreach ($byServer as $path) {
            $patches[] = ['op' => 'replace', 'path' => Paths::pointer('state', $path), 'value' => data_get($state, $path)];
        }

        $live = array_values(array_filter(
            array_map(static fn (Field $field) => $field->hasLiveValidation() ? $field->path() : null, $tree->fields()),
            static fn (?string $path) => $path !== null && in_array($path, $changed, true),
        ));
        $errors = $live === [] ? [] : array_merge(array_fill_keys($live, []), $this->validate($live));

        return ['patches' => $patches, 'errors' => $errors, 'snapshot' => $this->snapshot()];
    }

    /**
     * Runs an #[Action] method.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $client
     * @param string|null $trigger id of the button, to validate only its scope (e.g. its modal)
     * @param bool $bulk the button belongs to Table::bulkActions(); arguments carry the selected ids
     * @param list<string>|null $inputs argument names signed with ActionCall::withInput(); only these are taken from $arguments
     * @return array{0: int, 1: array<string, mixed>} HTTP status and JSON body
     */
    public function act(string $name, array $arguments, string $token, array $client, ?string $trigger = null, bool $bulk = false, ?array $inputs = null): array
    {
        $attribute = ActionCall::attribute($this->screen::class, $name);

        if ($attribute === null) {
            throw new HttpException(404, VandaException::unknownAction($this->screen::class, $name)->getMessage());
        }

        if ($bulk) {
            $ids = array_values(array_filter((array) ($arguments['ids'] ?? []), static fn ($id) => is_int($id) || is_string($id)));
            $valid = ActionCall::verify($this->screen::class, $name, ActionCall::BULK, $token);
            $arguments = ['ids' => $ids];
        } elseif ($inputs !== null) {
            $inputs = array_values(array_filter($inputs, 'is_string'));
            sort($inputs);
            $valid = ActionCall::verify($this->screen::class, $name, ['__input' => $inputs], $token);
            $arguments = array_map(
                static fn (mixed $value) => is_scalar($value) || $value === null ? $value : null,
                array_intersect_key($arguments, array_flip($inputs)),
            );
        } else {
            $valid = ActionCall::verify($this->screen::class, $name, $arguments, $token);
        }

        if (! $valid) {
            throw new HttpException(403, VandaException::invalidActionToken($this->screen::class, $name)->getMessage());
        }

        $this->runUpdatedHooks($this->applyClientState($client));

        $scope = array_map(static fn (Field $field) => $field->path(), $this->tree()->fieldsInScope($trigger));
        if ($attribute->validate && ($errors = $this->validate($scope)) !== []) {
            return [422, ['errors' => $errors, 'snapshot' => $this->snapshot()]];
        }

        $bound = ActionCall::bind($this->screen, $name, $arguments);
        Authorizer::authorizeAction($this->screen, $name, $bound);

        try {
            $returned = app(ActivityLog::class)->during(
                $this->activityContext($name, $attribute, $trigger, $bound, $bulk ? $arguments : []),
                fn () => app()->call([$this->screen, $name], $bound),
            );
        } catch (ValidationException $exception) {
            return [422, ['errors' => $exception->errors(), 'snapshot' => $this->snapshot()]];
        }

        $result = match (true) {
            $returned instanceof Result => $returned,
            $returned instanceof RedirectResponse => Result::redirectTo($returned->getTargetUrl()),
            default => Result::refresh(),
        };

        $this->tree = null;
        $body = $result->toArray();

        if ($result->shouldRefresh()) {
            $body['page'] = $this->ir();
        } elseif (! isset($body['redirect'])) {
            $body['snapshot'] = $this->snapshot();
        }

        return [200, $body];
    }

    /**
     * The screen, button and record of an action call for the activity log; field labels name the
     * attributes they edit (`post.title` → title).
     *
     * @param array<string, mixed> $bound arguments of the method (a row's record among them)
     * @param array<string, mixed> $bulk selected ids of a bulk action
     */
    private function activityContext(string $name, Action $attribute, ?string $trigger, array $bound, array $bulk): ActivityContext
    {
        $button = $trigger === null ? null : $this->tree()->find($trigger);

        $labels = [];
        $ambiguous = [];
        foreach ($this->tree()->fields() as $field) {
            $segments = explode('.', $field->path());
            $label = $field->getLabel();
            $key = end($segments);
            if (count($segments) < 2 || $label === null || $label === '' || ctype_digit($key)) {
                continue;
            }
            if (isset($labels[$key]) && $labels[$key] !== $label) {
                $ambiguous[] = $key;
            }
            $labels[$key] ??= $label;
        }

        $subject = null;
        foreach ($bound as $value) {
            if ($value instanceof Model && $value->exists) {
                $subject = $value;
                break;
            }
        }

        return new ActivityContext(
            screen: $this->screen->title(),
            action: $button instanceof Button ? $button->label() : $name,
            url: '/'.ltrim(request()->path(), '/'),
            subject: $subject ?? $this->screen->activitySubject(),
            labels: array_diff_key($labels, array_flip($ambiguous)),
            properties: isset($bulk['ids']) ? ['ids' => $bulk['ids']] : [],
            // Opening a modal loads its values: nothing was done yet.
            recordsCall: $attribute->activity && ! $button instanceof ModalToggle,
            batch: (string) Str::uuid(),
        );
    }

    /**
     * Validates shown fields: rules of Form objects, ad-hoc ->rules(), requiredWhen
     * and Repeater child rules. Hidden fields (server-side or by visibleWhen) are skipped.
     *
     * @param list<string>|null $only
     * @return array<string, list<string>>
     */
    public function validate(?array $only = null): array
    {
        $values = $this->values();
        [$rules, $attributes] = $this->rules($only, $values);

        if ($rules === []) {
            return [];
        }

        $validator = Validator::make($values, $rules, [], $attributes);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    /**
     * Rules of every shown field, keyed by path, and their human names.
     *
     * @param list<string>|null $only
     * @param array<string, mixed> $state
     * @return array{0: array<string, list<mixed>>, 1: array<string, string>}
     */
    public function rules(?array $only, array $state): array
    {
        $tree = $this->tree();
        $formRules = [];
        $rules = [];
        $attributes = [];

        foreach ($tree->fields() as $field) {
            $path = $field->path();

            if (($only !== null && ! in_array($path, $only, true)) || ! $tree->isShown($field, $state)) {
                continue;
            }

            [$root, $key] = array_pad(explode('.', $path, 2), 2, null);
            $form = property_exists($this->screen, $root) ? ($this->screen->{$root} ?? null) : null;

            if ($form instanceof Form && $key !== null) {
                $formRules[$root] ??= $form->validationRules($this->screen);
            }

            $fieldRules = [...($formRules[$root][$key] ?? []), ...$field->getRules(), ...$field->implicitRules(new Get($state))];

            // ->required() is a rule, not only a star: a field marked required is validated as such.
            if (($field->isRequired() || $field->getRequiredWhen()?->isTrue($state)) && ! in_array('required', $fieldRules, true)) {
                array_unshift($fieldRules, 'required');
            }

            if ($fieldRules !== []) {
                $rules[$path] = $fieldRules;
                $attributes[$path] = $field->getLabel() ?? $path;
            }

            // Nested Form rules such as "roles.*" belong to the field "form.roles".
            foreach ($formRules[$root] ?? [] as $formKey => $nested) {
                if ($key !== null && str_starts_with((string) $formKey, $key.'.')) {
                    $rules[$root.'.'.$formKey] = $nested;
                }
            }

            if ($field instanceof Repeater) {
                foreach ($field->getFields() as $child) {
                    $childRules = [...$child->getRules(), ...$child->implicitRules(new Get($state))];
                    if ($childRules !== []) {
                        $rules[$path.'.*.'.$child->path()] = $childRules;
                        $attributes[$path.'.*.'.$child->path()] = $child->getLabel() ?? $child->path();
                    }
                }
            }
        }

        return [$rules, $attributes];
    }

    /**
     * Resolves scalar route parameters into models for parameters typed as models.
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private static function bindModels(Screen $screen, array $parameters): array
    {
        foreach ((new ReflectionMethod($screen, 'query'))->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            if (! array_key_exists($name, $parameters) || ! $type instanceof ReflectionNamedType) {
                continue;
            }

            $class = $type->getName();
            if (is_subclass_of($class, Model::class) && ! $parameters[$name] instanceof Model) {
                $parameters[$name] = (new $class)->resolveRouteBinding($parameters[$name]) ?? abort(404);
            }
        }

        return $parameters;
    }
}
