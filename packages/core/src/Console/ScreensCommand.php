<?php

declare(strict_types=1);

namespace Vanda\Console;

use Illuminate\Console\Command;
use ReflectionClass;
use ReflectionMethod;
use Vanda\Attributes\Action;
use Vanda\Screen\ScreenRegistry;

/**
 * Lists every screen registered with Route::screen().
 */
final class ScreensCommand extends Command
{
    protected $signature = 'vanda:screens {--json : Print as JSON}';

    protected $description = 'List Vanda screens with their routes and actions';

    public function handle(ScreenRegistry $registry): int
    {
        $rows = array_map(static function (array $entry): array {
            $actions = array_values(array_map(
                static fn (ReflectionMethod $method) => $method->getName(),
                array_filter(
                    (new ReflectionClass($entry['screen']))->getMethods(ReflectionMethod::IS_PUBLIC),
                    static fn (ReflectionMethod $method) => $method->getAttributes(Action::class) !== [],
                ),
            ));

            return [
                'uri' => '/'.ltrim($entry['route']->uri(), '/'),
                'name' => $entry['route']->getName(),
                'screen' => $entry['screen'],
                'actions' => $actions,
            ];
        }, $registry->all());

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['URI', 'Name', 'Screen', 'Actions'],
            array_map(static fn (array $row) => [$row['uri'], $row['name'] ?? '', $row['screen'], implode(', ', $row['actions'])], $rows),
        );

        return self::SUCCESS;
    }
}
