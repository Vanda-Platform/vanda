<?php

declare(strict_types=1);

namespace Vanda\Extensions;

use Closure;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Vanda\Access\PermissionRegistry;
use Vanda\Navigation\Shell;
use Vanda\VandaException;

/**
 * Extensions registered with Panel::extension() and the Route::vanda() group being declared,
 * so Route::extension() knows which panel it mounts screens in.
 */
final class ExtensionRegistry
{
    /** @var array<class-string<Extension>, Extension> */
    private array $extensions = [];

    /** Prefix of the Route::vanda() group whose routes are being declared; null outside of one. */
    private ?string $panel = null;

    public function __construct(
        private readonly PermissionRegistry $permissions,
        private readonly Shell $shell,
    ) {}

    public function register(Extension $extension): void
    {
        $class = $extension::class;
        $name = $extension->name();

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) !== 1) {
            throw VandaException::invalidExtensionName($class, $name);
        }
        foreach ($this->extensions as $other => $registered) {
            if ($other !== $class && $registered->name() === $name) {
                throw VandaException::duplicateExtensionName($name, $other, $class);
            }
        }

        $first = ! isset($this->extensions[$class]);
        $this->extensions[$class] = $extension;

        if (! $first) {
            return;
        }

        // The providers read the registered instance on every call, so a later registration of the
        // same class (the application configuring the package's extension) takes their place.
        $this->permissions->define(fn (): iterable => $this->get($class)->permissions());

        // An empty provider would turn the ⌘K search on for a panel that has nothing to find.
        if ((new ReflectionMethod($extension, 'search'))->getDeclaringClass()->getName() !== Extension::class) {
            $this->shell->addSearch(fn (string $query): iterable => $this->get($class)->search($query));
        }
    }

    /**
     * @param class-string<Extension> $class
     */
    public function get(string $class): Extension
    {
        return $this->extensions[$class] ?? throw VandaException::extensionNotRegistered($class);
    }

    /**
     * @param class-string<Extension> $class
     */
    public function has(string $class): bool
    {
        return isset($this->extensions[$class]);
    }

    /**
     * @return list<Extension>
     */
    public function all(): array
    {
        return array_values($this->extensions);
    }

    /**
     * @internal Route::vanda() declares the panel's routes through it.
     */
    public function within(string $prefix, Closure $routes): void
    {
        $previous = $this->panel;
        $this->panel = trim($prefix, '/');

        try {
            $routes();
        } finally {
            $this->panel = $previous;
        }
    }

    /**
     * @internal Route::extension()
     */
    public function mount(string $class, ?string $uri = null): void
    {
        if (! is_subclass_of($class, Extension::class)) {
            throw VandaException::notAnExtension($class);
        }
        if ($this->panel === null) {
            throw VandaException::extensionOutsidePanel($class);
        }

        $extension = $this->get($class);
        $names = trim(str_replace('/', '.', $this->panel).'.'.$extension->name(), '.');

        Route::prefix(trim($uri ?? $extension->name(), '/'))
            ->name($names.'.')
            ->group(static fn () => $extension->routes());
    }
}
