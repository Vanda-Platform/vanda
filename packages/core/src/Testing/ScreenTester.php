<?php

declare(strict_types=1);

namespace Vanda\Testing;

use Closure;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Assert;
use Vanda\Schema\Expr;
use Vanda\Screen\ActionCall;
use Vanda\Screen\ScreenRuntime;
use Vanda\Support\Patch;

/**
 * Drives a screen through the same pipeline as the browser, without HTTP or HTML.
 * Created with Vanda::test(Screen::class, [...route parameters]).
 */
final class ScreenTester
{
    private const SCOPE = '/vanda-test';

    /** @var array<string, mixed> */
    private array $page;

    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed>|null */
    private ?array $lastResult = null;

    /**
     * @param class-string $screen
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private readonly string $screen,
        private readonly array $parameters = [],
    ) {
        $this->page = ScreenRuntime::boot($screen, $parameters, self::SCOPE)->ir();
    }

    /**
     * Change a field as the user would; runs the server reaction.
     */
    public function set(string $path, mixed $value): self
    {
        $state = $this->page['state'];
        Arr::set($state, $path, $value);
        $this->page['state'] = $state;

        $response = $this->runtime()->react($state);
        $this->page = Patch::apply($this->page, $response['patches']);
        $this->page['snapshot'] = $response['snapshot'];
        $this->errors = array_filter([...$this->errors, ...$response['errors']]);

        return $this;
    }

    /**
     * Call an #[Action] method as a button would. Pass the button id as $from
     * to validate the way that button does (e.g. only its modal's fields).
     *
     * @param array<string, mixed> $arguments
     */
    public function call(string $action, array $arguments = [], ?string $from = null): self
    {
        $arguments = ActionCall::normalizeArguments($arguments);
        $token = ActionCall::sign($this->screen, $action, $arguments);

        return $this->apply($this->runtime()->act($action, $arguments, $token, $this->page['state'], $from));
    }

    /**
     * Calls an action whose arguments come from the browser, like a tree move:
     *
     *     ->callWithInput('move', ['id' => 5, 'parent' => 2, 'position' => 0])
     *
     * @param array<string, mixed> $input
     */
    public function callWithInput(string $action, array $input, ?string $from = null): self
    {
        $inputs = array_keys($input);
        sort($inputs);
        $token = ActionCall::sign($this->screen, $action, ['__input' => $inputs]);

        return $this->apply($this->runtime()->act($action, $input, $token, $this->page['state'], $from, inputs: $inputs));
    }

    /**
     * Call a Table::bulkActions() action for the selected row ids.
     *
     * @param list<int|string> $ids
     */
    public function callBulk(string $action, array $ids): self
    {
        $token = ActionCall::sign($this->screen, $action, ActionCall::BULK);

        return $this->apply($this->runtime()->act($action, ['ids' => $ids], $token, $this->page['state'], null, bulk: true));
    }

    /**
     * @param array{0: int, 1: array<string, mixed>} $response
     */
    private function apply(array $response): self
    {
        [$status, $body] = $response;

        $this->lastResult = $body;
        $this->errors = $status === 422 ? $body['errors'] : [];

        if (isset($body['page'])) {
            $this->page = $body['page'];
        } elseif (isset($body['snapshot'])) {
            $this->page['snapshot'] = $body['snapshot'];
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function page(): array
    {
        return $this->page;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function node(string $id): ?array
    {
        return $this->findNode([...$this->page['commandBar'], ...$this->page['layout']], $id);
    }

    public function assertSet(string $path, mixed $expected): self
    {
        Assert::assertSame($expected, data_get($this->page['state'], $path), "State [{$path}] differs.");

        return $this;
    }

    /**
     * @param (Closure(array<string, mixed>): mixed)|null $check
     */
    public function assertNode(string $id, ?Closure $check = null): self
    {
        $node = $this->node($id);
        Assert::assertNotNull($node, "Node [{$id}] is not on the screen.");

        if ($check !== null) {
            $outcome = $check($node);
            if (is_bool($outcome)) {
                Assert::assertTrue($outcome, "Node [{$id}] does not match the expectation.");
            }
        }

        return $this;
    }

    public function assertVisible(string $id): self
    {
        Assert::assertTrue($this->isShown($id), "Node [{$id}] is not visible.");

        return $this;
    }

    public function assertHidden(string $id): self
    {
        Assert::assertFalse($this->isShown($id), "Node [{$id}] is visible.");

        return $this;
    }

    /**
     * @param list<string>|string $paths
     */
    public function assertHasErrors(array|string $paths = []): self
    {
        Assert::assertNotEmpty($this->errors, 'Expected validation errors, got none.');

        foreach ((array) $paths as $path) {
            Assert::assertArrayHasKey($path, $this->errors, "No validation error for [{$path}]. Errors: ".json_encode($this->errors, JSON_UNESCAPED_UNICODE));
        }

        return $this;
    }

    public function assertHasNoErrors(): self
    {
        Assert::assertSame([], $this->errors, 'Unexpected validation errors: '.json_encode($this->errors, JSON_UNESCAPED_UNICODE));

        return $this;
    }

    public function assertToast(?string $message = null): self
    {
        $toast = $this->lastResult['toast'] ?? null;
        Assert::assertNotNull($toast, 'The last action did not show a toast.');

        if ($message !== null) {
            Assert::assertSame($message, $toast['message']);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>|null body of the last action response
     */
    public function lastResult(): ?array
    {
        return $this->lastResult;
    }

    public function assertRedirect(?string $url = null): self
    {
        $redirect = $this->lastResult['redirect'] ?? null;
        Assert::assertNotNull($redirect, 'The last action did not redirect.');

        if ($url !== null) {
            Assert::assertSame($url, $redirect);
        }

        return $this;
    }

    private function runtime(): ScreenRuntime
    {
        $runtime = ScreenRuntime::boot($this->screen, $this->parameters, self::SCOPE);
        $runtime->restore($this->page['snapshot']);

        return $runtime;
    }

    private function isShown(string $id): bool
    {
        $path = $this->pathTo([...$this->page['commandBar'], ...$this->page['layout']], $id);

        if ($path === null) {
            return false;
        }

        foreach ($path as $node) {
            if (isset($node['visibleWhen']) && ! Expr::fromArray($node['visibleWhen'])->isTrue($this->page['state'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return array<string, mixed>|null
     */
    private function findNode(array $nodes, string $id): ?array
    {
        $path = $this->pathTo($nodes, $id);

        return $path === null ? null : end($path);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>|null
     */
    private function pathTo(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $id) {
                return [$node];
            }

            $found = $this->pathTo($node['children'] ?? [], $id);
            if ($found !== null) {
                return [$node, ...$found];
            }
        }

        return null;
    }
}
