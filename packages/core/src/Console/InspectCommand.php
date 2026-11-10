<?php

declare(strict_types=1);

namespace Vanda\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use ReflectionClass;
use ReflectionMethod;
use Stringable;
use Vanda\Attributes\Action;
use Vanda\Attributes\Updated;
use Vanda\Screen\ScreenRuntime;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaCheck;

/**
 * Shows a screen exactly as the browser receives it: nodes, ids, rules,
 * actions, hooks and the PHP line that created every node.
 */
final class InspectCommand extends Command
{
    protected $signature = 'vanda:inspect
        {target : URL path such as /admin/users/1/edit, or a screen class}
        {--json : Print the page IR as JSON}
        {--as= : Act as the user with this id}
        {--param=* : Route parameter for a class target, e.g. --param=user=1}';

    protected $description = 'Describe a Vanda screen: layout tree, fields, rules, actions and sources';

    public function handle(): int
    {
        // The agent's tool: always with source lines and the page checks, whatever the environment.
        Node::$captureSource = true;
        SchemaCheck::$enabled = true;

        if ($this->option('as') !== null) {
            Auth::onceUsingId($this->option('as'));
        }

        [$class, $parameters, $scope] = $this->resolveTarget((string) $this->argument('target'));

        if ($class === null) {
            $this->error('No Vanda screen matches '.$this->argument('target').'. Run `php artisan vanda:screens` to list screens.');

            return self::FAILURE;
        }

        $runtime = ScreenRuntime::boot($class, $parameters, $scope);
        $page = $runtime->ir();

        if ($this->option('json')) {
            $this->line((string) json_encode(Arr::except($page, ['snapshot']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        [$rules] = $runtime->rules(null, $page['state']);

        $this->line("<info>{$class}</info>  {$scope}");
        if ($page['title']) {
            $this->line("Title:   {$page['title']}");
        }
        $this->line('Actions: '.(implode(', ', $this->actions($class)) ?: '—'));
        $this->line('Hooks:   '.(implode(', ', $this->hooks($class)) ?: '—'));
        $this->line('State:   '.json_encode($page['state'], JSON_UNESCAPED_UNICODE));

        foreach (['commandBar', 'layout'] as $part) {
            $this->newLine();
            $this->line("<comment>{$part}</comment>");
            $this->printNodes($page[$part], $rules, 1);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: ?class-string, 1: array<string, mixed>, 2: string}
     */
    private function resolveTarget(string $target): array
    {
        if (class_exists($target)) {
            $parameters = [];
            foreach ((array) $this->option('param') as $pair) {
                [$key, $value] = array_pad(explode('=', (string) $pair, 2), 2, null);
                $parameters[$key] = $value;
            }

            return [$target, $parameters, '/'];
        }

        $request = Request::create($target);

        try {
            $route = app('router')->getRoutes()->match($request);
        } catch (\Throwable) {
            return [null, [], ''];
        }

        $class = $route->defaults['_vanda_screen'] ?? null;

        return [$class, Arr::except($route->parameters(), ['_vanda_screen', '_vanda_panel']), '/'.ltrim($request->path(), '/')];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, list<mixed>> $rules
     */
    private function printNodes(array $nodes, array $rules, int $depth): void
    {
        foreach ($nodes as $node) {
            $label = $node['label'] ?? $node['title'] ?? $node['text'] ?? null;
            $details = array_filter([
                $label !== null ? '"'.$label.'"' : null,
                isset($node['action']) ? '→ '.$node['action']['name'].'()' : null,
                isset($node['href']) ? '→ '.$node['href'] : null,
                isset($rules[$node['path'] ?? '']) ? 'rules: '.$this->describeRules($rules[$node['path']]) : null,
                isset($node['dependsOn']) ? 'dependsOn: '.implode(', ', $node['dependsOn']) : null,
                isset($node['visibleWhen']) ? 'visibleWhen: '.json_encode($node['visibleWhen'], JSON_UNESCAPED_UNICODE) : null,
                is_array($node['rows'] ?? null) ? count($node['rows']).' rows' : null,
                isset($node['kind']) && $node['type'] === 'chart' ? $node['kind'].' chart' : null,
                isset($node['source']) ? '<fg=gray>'.$node['source'].'</>' : null,
            ]);

            $this->line(str_repeat('  ', $depth).sprintf('<fg=cyan>%-8s</> %s  %s', $node['type'], $node['id'], implode('  ', $details)));
            $this->printNodes($node['children'] ?? [], $rules, $depth + 1);
        }
    }

    /**
     * @param list<mixed> $rules
     */
    private function describeRules(array $rules): string
    {
        return implode('|', array_map(
            static fn (mixed $rule) => is_string($rule) ? $rule : ($rule instanceof Stringable ? (string) $rule : class_basename($rule)),
            $rules,
        ));
    }

    /**
     * @return list<string>
     */
    private function actions(string $class): array
    {
        return array_values(array_map(
            static fn (ReflectionMethod $method) => $method->getName().'()',
            array_filter(
                (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC),
                static fn (ReflectionMethod $method) => $method->getAttributes(Action::class) !== [],
            ),
        ));
    }

    /**
     * @return list<string>
     */
    private function hooks(string $class): array
    {
        $hooks = [];
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Updated::class) as $attribute) {
                $hooks[] = $method->getName().'() ← '.$attribute->newInstance()->path;
            }
        }

        return $hooks;
    }
}
