<?php

declare(strict_types=1);

namespace Vanda\Activity;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionEnum;
use Vanda\Access\Impersonation;
use Vanda\VandaException;

/**
 * Writes the activity log. Screens wrap every action call in during(); models with
 * RecordsActivity report their changes through recordModel(); anything else goes through note():
 *
 *     ActivityLog::note('Заявка отправлена в 1С', $ticket);
 */
final class ActivityLog
{
    /** Longer values are cut: the log is for seeing what changed, not for backups. */
    private const MAX_VALUE = 2000;

    private ?ActivityContext $context = null;

    private int $written = 0;

    /**
     * A line of your own in the log, with the current screen and user.
     *
     * @param array<string, mixed> $properties
     */
    public static function note(string $text, ?Model $subject = null, array $properties = []): ?Activity
    {
        return app(self::class)->write(ActivityEvent::Action, $subject, action: $text, properties: $properties);
    }

    /**
     * Runs one action call of a screen. Changes made inside are recorded with its screen and
     * button; a call that changed no recorded model is recorded by itself.
     *
     * @template T
     * @param Closure(): T $call
     * @return T
     */
    public function during(ActivityContext $context, Closure $call): mixed
    {
        $previous = $this->context;
        $before = $this->written;
        $this->context = $context;

        try {
            $result = $call();
        } finally {
            $this->context = $previous;
        }

        if ($context->recordsCall && $this->written === $before && self::enabled()) {
            $this->context = $context;

            try {
                $this->write(ActivityEvent::Action, $context->subject, properties: $context->properties);
            } finally {
                $this->context = $previous;
            }
        }

        return $result;
    }

    /**
     * Inside an action call (or for models recorded everywhere()).
     */
    public function recordModel(ActivityEvent $event, Model $model): void
    {
        $options = $this->options($model);

        if (! self::enabled() || ($this->context === null && ! $options->recordsEverywhere())) {
            return;
        }

        [$changes, $secret] = $this->changes($event, $model, $options);

        if ($event === ActivityEvent::Updated && $changes === []) {
            return;
        }

        $labels = [];
        foreach (array_keys($changes) as $attribute) {
            $label = $options->labelOf($attribute) ?? $this->context?->labels[$attribute] ?? null;
            if ($label !== null) {
                $labels[$attribute] = $label;
            }
        }

        $this->write($event, $model, $changes, array_filter(['labels' => $labels, 'secret' => $secret]));
    }

    /**
     * Sign-ins, sign-outs and impersonation: the user is the one given, not the guard's.
     *
     * @param array<string, mixed> $properties
     */
    public function recordSession(ActivityEvent $event, Authenticatable $user, ?Model $subject = null, array $properties = []): ?Activity
    {
        return $this->write($event, $subject, properties: $properties, causer: $user);
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changes
     * @param array<string, mixed> $properties
     */
    private function write(ActivityEvent $event, ?Model $subject, array $changes = [], array $properties = [], ?string $action = null, ?Authenticatable $causer = null): ?Activity
    {
        if (! self::enabled()) {
            return null;
        }

        $causer ??= Auth::guard(config('vanda.guard'))->user();
        $context = $this->context;

        $impersonator = $causer !== null && Impersonation::isActive() ? Impersonation::impersonator() : null;
        if ($impersonator !== null && $impersonator->getAuthIdentifier() !== $causer->getAuthIdentifier()) {
            $properties['impersonator'] = self::nameOf($impersonator);
        }

        $entry = new Activity([
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey() === null ? null : (string) $subject->getKey(),
            'subject_label' => $subject === null ? null : $this->options($subject)->titleOf($subject),
            'causer_type' => $causer instanceof Model ? $causer->getMorphClass() : null,
            'causer_id' => $causer === null ? null : (string) $causer->getAuthIdentifier(),
            'causer_name' => $causer === null ? null : self::nameOf($causer),
            'screen' => $context?->screen === null ? null : Str::limit($context->screen, 250),
            'action' => Str::limit((string) ($action ?? $context?->action), 250) ?: null,
            'url' => $context?->url,
            'diff' => $changes === [] ? null : $changes,
            'properties' => $properties === [] ? null : $properties,
            'batch' => $context?->batch ?: null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
        $entry->created_at = now();

        try {
            $entry->save();
        } catch (QueryException $exception) {
            if (! Schema::hasTable($entry->getTable())) {
                throw new VandaException('The activity log has no table: run `php artisan migrate` (Vanda\'s migration creates `vanda_activity`), or turn the log off with VANDA_ACTIVITY=false in .env (config vanda.activity.enabled).', previous: $exception);
            }

            throw $exception;
        }

        $this->written++;

        return $entry;
    }

    /**
     * @return array{0: array<string, array{0: mixed, 1: mixed}>, 1: list<string>}
     */
    private function changes(ActivityEvent $event, Model $model, ActivityOptions $options): array
    {
        $changes = [];
        $secret = [];

        $attributes = match ($event) {
            ActivityEvent::Updated => array_keys($model->getChanges()),
            ActivityEvent::Created, ActivityEvent::Deleted => array_keys($model->getAttributes()),
            default => [],
        };

        foreach ($attributes as $attribute) {
            if ($attribute === $model->getKeyName() || ! $options->tracks($attribute)) {
                continue;
            }

            $old = $event === ActivityEvent::Created ? null : $model->getRawOriginal($attribute);
            $new = $event === ActivityEvent::Deleted ? null : $model->getAttributes()[$attribute] ?? null;

            if ($options->expands($attribute)) {
                foreach (self::keyChanges(self::decoded($old), self::decoded($new)) as $key => [$before, $after]) {
                    if ($options->isSecret($model, $key)) {
                        $changes[$key] = [null, null];
                        $secret[] = $key;

                        continue;
                    }
                    $enum = $options->enumOf($attribute, $key);
                    $changes[$key] = [self::value(self::labelOfCase($enum, $before), false), self::value(self::labelOfCase($enum, $after), false)];
                }

                continue;
            }

            // A new or deleted record lists its filled values only; an empty text that became
            // null (or back) is no change for a person.
            if (($old ?? '') === '' && ($new ?? '') === '') {
                continue;
            }

            if ($options->isSecret($model, $attribute)) {
                $changes[$attribute] = [null, null];
                $secret[] = $attribute;

                continue;
            }

            $boolean = $model->hasCast($attribute, ['bool', 'boolean']);
            $changes[$attribute] = [
                self::value(self::enumLabel($model, $attribute, $old), $boolean),
                self::value(self::enumLabel($model, $attribute, $new), $boolean),
            ];
        }

        return [$changes, $secret];
    }

    /**
     * Keys of two JSON objects whose values differ; an empty text, an empty list and null are the same.
     *
     * @param array<array-key, mixed> $old
     * @param array<array-key, mixed> $new
     * @return array<string, array{mixed, mixed}>
     */
    private static function keyChanges(array $old, array $new): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $before = $old[$key] ?? null;
            $after = $new[$key] ?? null;

            if (in_array($before, [null, '', []], true) && in_array($after, [null, '', []], true)) {
                continue;
            }
            if (json_encode($before) !== json_encode($after)) {
                // An empty list reads as nothing, like an empty text.
                $changes[(string) $key] = [$before === [] ? null : $before, $after === [] ? null : $after];
            }
        }

        return $changes;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decoded(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $value = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return is_array($value) ? $value : [];
    }

    /**
     * An attribute cast to an enum with label() is kept by its label: «Опубликована», not "published".
     */
    private static function enumLabel(Model $model, string $attribute, mixed $raw): mixed
    {
        $cast = $model->getCasts()[$attribute] ?? null;

        return self::labelOfCase(is_string($cast) ? $cast : null, $raw);
    }

    /**
     * The label() of the case of $cast that $raw stores, or $raw itself.
     */
    private static function labelOfCase(?string $cast, mixed $raw): mixed
    {
        if ($cast === null || ! enum_exists($cast) || ! is_subclass_of($cast, BackedEnum::class) || ! method_exists($cast, 'label') || ! (is_int($raw) || is_string($raw))) {
            return $raw;
        }

        $value = (string) (new ReflectionEnum($cast))->getBackingType() === 'int'
            ? (is_numeric($raw) ? (int) $raw : null)
            : (string) $raw;
        $case = $value === null ? null : $cast::tryFrom($value);

        return $case === null ? $raw : $case->label();
    }

    private static function value(mixed $value, bool $boolean): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($boolean) {
            return (bool) $value;
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if (is_array($value) || is_object($value)) {
            $value = (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = (string) $value;

        return mb_strlen($value) > self::MAX_VALUE ? mb_substr($value, 0, self::MAX_VALUE).'…' : $value;
    }

    /**
     * config('vanda.activity.enabled'): the whole log can be turned off.
     */
    public static function enabled(): bool
    {
        return (bool) config('vanda.activity.enabled', true);
    }

    private function options(Model $model): ActivityOptions
    {
        return method_exists($model, 'activityOptions') ? $model->activityOptions() : ActivityOptions::make();
    }

    private static function nameOf(Authenticatable $user): string
    {
        $name = $user instanceof Model ? ($user->getAttribute('name') ?? $user->getAttribute('email')) : null;

        return Str::limit((string) ($name ?? '#'.$user->getAuthIdentifier()), 250);
    }
}
