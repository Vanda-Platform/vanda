<?php

declare(strict_types=1);

namespace Vanda\Console;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;
use Vanda\Access\Authorizer;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaCheck;
use Vanda\Screen\Screen;
use Vanda\Screen\ScreenRegistry;
use Vanda\Screen\ScreenRuntime;
use Vanda\Support\Icons;

/**
 * Builds the page of every routed screen and reports what would fail in the browser: the page
 * checks (paths, modals, icons, see SchemaCheck) and anything a screen throws while being built.
 * Screens are built for someone who may do everything (so parts hidden from some roles are checked
 * too); a model parameter gets the first record of its table.
 *
 *     php artisan vanda:check            # a list for people, exit code 1 when something is wrong
 *     php artisan vanda:check --json     # the same for an agent
 *     php artisan vanda:check users      # only screens whose URI or class contains "users"
 */
final class CheckCommand extends Command
{
    protected $signature = 'vanda:check
        {filter? : Only screens whose URI or class contains this}
        {--json : Print the result as JSON}
        {--as= : Sign in as the user with this id (default: the first user of the panel\'s guard)}';

    protected $description = 'Build every Vanda screen and report paths, modals, icons and errors that would fail in the browser';

    public function handle(ScreenRegistry $registry): int
    {
        Node::$captureSource = true;
        SchemaCheck::$enabled = false;
        $this->signIn();
        // Everything is allowed here, so the parts some roles never see are checked too
        // (#[Can] and Vanda's permissions through Authorizer, other abilities, also for a guest, here).
        Authorizer::$allowAll = true;
        Gate::before(static fn (?Authenticatable $user = null) => Authorizer::$allowAll ? true : null);

        try {
            return $this->checkAll($registry);
        } finally {
            Authorizer::$allowAll = false;
        }
    }

    private function checkAll(ScreenRegistry $registry): int
    {
        $filter = (string) ($this->argument('filter') ?? '');
        $results = [];
        foreach ($registry->all() as $entry) {
            $uri = '/'.ltrim($entry['route']->uri(), '/');
            if ($filter !== '' && ! str_contains(strtolower($uri.' '.$entry['screen']), strtolower($filter))) {
                continue;
            }
            $results[] = ['uri' => $uri, 'screen' => $entry['screen'], ...$this->check($entry['screen'], $entry['route'])];
        }

        $summary = ['screens' => count($results)];
        foreach (['ok', 'problems', 'error', 'skipped'] as $status) {
            $summary[$status] = count(array_filter($results, static fn (array $result) => $result['status'] === $status));
        }
        $failed = $summary['problems'] + $summary['error'] > 0;

        // An unknown icon is easier to fix with the names at hand, given once.
        $icons = array_filter($results, static fn (array $result) => str_contains(implode("\n", $result['problems'] ?? []), 'which the renderer does not draw'));

        if ($this->option('json')) {
            $this->line((string) json_encode(
                ['ok' => ! $failed, 'summary' => $summary, 'screens' => $results, ...($icons === [] ? [] : ['icons' => Icons::names()])],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        } else {
            $this->report($results, $summary);
            if ($icons !== []) {
                $this->line('Icons the renderer draws: '.implode(', ', Icons::names()).'.');
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param class-string<Screen> $screen
     * @return array<string, mixed>
     */
    private function check(string $screen, Route $route): array
    {
        $parameters = $this->parameters($screen, $route);
        if (is_string($parameters)) {
            return ['status' => 'skipped', 'reason' => $parameters];
        }

        $scope = '/'.ltrim((string) preg_replace_callback('/\{(\w+)\??\}/', static function (array $match) use ($parameters): string {
            $value = $parameters[$match[1]] ?? '';

            return (string) ($value instanceof Model ? $value->getRouteKey() : $value);
        }, $route->uri()), '/');

        $shown = array_map(static fn (mixed $value) => $value instanceof Model ? $value->getKey() : $value, $parameters);

        try {
            $runtime = ScreenRuntime::boot($screen, $parameters, $scope);
            $problems = SchemaCheck::problems($runtime->screen, $runtime->ir());
        } catch (Throwable $exception) {
            return ['status' => 'error', 'parameters' => $shown, 'error' => $exception->getMessage(), 'at' => $this->where($exception)];
        }

        return $problems === []
            ? ['status' => 'ok', 'parameters' => $shown]
            : ['status' => 'problems', 'parameters' => $shown, 'problems' => $problems];
    }

    /**
     * Values for the route's parameters: the first record for a model parameter of query();
     * a sentence why the screen cannot be built when one is missing.
     *
     * @param class-string<Screen> $screen
     * @return array<string, mixed>|string
     */
    private function parameters(string $screen, Route $route): array|string
    {
        preg_match_all('/\{(\w+)\}/', $route->uri(), $required);
        if ($required[1] === []) {
            return [];
        }

        $types = [];
        if (method_exists($screen, 'query')) {
            foreach ((new ReflectionMethod($screen, 'query'))->getParameters() as $parameter) {
                $type = $parameter->getType();
                $types[$parameter->getName()] = $type instanceof ReflectionNamedType ? $type->getName() : null;
            }
        }

        $parameters = [];
        foreach ($required[1] as $name) {
            $class = $types[$name] ?? null;
            if ($class === null || ! is_subclass_of($class, Model::class)) {
                return sprintf('{%s} is not a model parameter of query(), so there is no value to build the screen with.', $name);
            }

            $record = $class::query()->first();
            if ($record === null) {
                return sprintf('{%s}: there is no %s record to build the screen with. Seed one and run the check again.', $name, $class);
            }
            $parameters[$name] = $record;
        }

        return $parameters;
    }

    private function signIn(): void
    {
        $guard = Auth::guard((string) config('vanda.guard', 'web'));
        Auth::shouldUse((string) config('vanda.guard', 'web'));

        $user = $this->option('as') !== null
            ? $guard->getProvider()->retrieveById($this->option('as'))
            : (method_exists($guard->getProvider(), 'createModel') ? $guard->getProvider()->createModel()->newQuery()->first() : null);

        if ($user instanceof Authenticatable) {
            $guard->setUser($user);
        }
    }

    /** The first line of application code in the trace: where the screen went wrong. */
    private function where(Throwable $exception): ?string
    {
        $vanda = dirname(__DIR__);
        $frames = [['file' => $exception->getFile(), 'line' => $exception->getLine()], ...$exception->getTrace()];

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;
            if (is_string($file) && ! str_starts_with($file, $vanda) && ! str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                $base = base_path().DIRECTORY_SEPARATOR;

                return (str_starts_with($file, $base) ? substr($file, strlen($base)) : $file).':'.($frame['line'] ?? 0);
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $results
     * @param array<string, int> $summary
     */
    private function report(array $results, array $summary): void
    {
        foreach ($results as $result) {
            $screen = class_basename($result['screen']);
            $with = $result['parameters'] ?? [] ? ' ('.implode(', ', array_map(static fn (string $name, mixed $value) => "{$name} #{$value}", array_keys($result['parameters']), $result['parameters'])).')' : '';

            match ($result['status']) {
                'ok' => $this->line(sprintf('<info>✓</info> %-36s %s%s', $result['uri'], $screen, $with)),
                'skipped' => $this->line(sprintf('<comment>–</comment> %-36s %s: skipped. %s', $result['uri'], $screen, $result['reason'])),
                'error' => $this->line(sprintf('<error>✗</error> %-36s %s%s%s'."\n    %s", $result['uri'], $screen, $with, isset($result['at']) ? ' at '.$result['at'] : '', $result['error'])),
                default => $this->line(sprintf('<error>✗</error> %-36s %s%s'."\n%s", $result['uri'], $screen, $with, implode("\n", array_map(
                    static fn (int $index, string $problem) => '    '.($index + 1).'. '.$problem,
                    array_keys($result['problems']),
                    $result['problems'],
                )))),
            };
        }

        $this->newLine();
        $this->line(sprintf(
            '%d screens: %d fine, %d with problems, %d failed to build, %d skipped.',
            $summary['screens'],
            $summary['ok'],
            $summary['problems'],
            $summary['error'],
            $summary['skipped'],
        ));
    }
}
