<?php

declare(strict_types=1);

namespace Vanda\Screen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Vanda\VandaException;

/**
 * Knows which route serves which screen (registered with Route::screen()).
 */
final class ScreenRegistry
{
    public function __construct(private readonly Router $router) {}

    /**
     * @return list<array{route: Route, screen: class-string<Screen>}>
     */
    public function all(): array
    {
        $screens = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $screen = $route->defaults['_vanda_screen'] ?? null;

            if (is_string($screen)) {
                $screens[] = ['route' => $route, 'screen' => $screen];
            }
        }

        return $screens;
    }

    /**
     * URL of the route that can take exactly these parameters, so the same screen
     * may serve "users/create" and "users/{user}/edit".
     *
     * @param array<string, mixed> $parameters
     */
    public function url(string $screen, array $parameters = []): string
    {
        $candidates = array_filter($this->all(), static fn (array $entry) => $entry['screen'] === $screen);

        if ($candidates === []) {
            throw VandaException::screenNotRouted($screen);
        }

        $parameters = array_map(
            static fn (mixed $value) => $value instanceof Model ? $value->getRouteKey() : $value,
            $parameters,
        );

        foreach ($candidates as $entry) {
            $route = $entry['route'];
            $required = array_diff($route->parameterNames(), array_keys($route->defaults), $this->optional($route));

            if (array_diff($required, array_keys($parameters)) === [] && array_diff(array_keys($parameters), $route->parameterNames()) === []) {
                return $route->getName() !== null
                    ? route($route->getName(), $parameters)
                    : url($this->fill($route->uri(), $parameters));
            }
        }

        $route = reset($candidates)['route'];

        return url($this->fill($route->uri(), $parameters));
    }

    /**
     * @return list<string>
     */
    private function optional(Route $route): array
    {
        preg_match_all('/\{(\w+)\?\}/', $route->uri(), $matches);

        return $matches[1];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function fill(string $uri, array $parameters): string
    {
        return preg_replace_callback(
            '/\{(\w+)\??\}/',
            static fn (array $match) => rawurlencode((string) ($parameters[$match[1]] ?? '')),
            $uri,
        ) ?? $uri;
    }
}
