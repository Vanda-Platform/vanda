<?php

declare(strict_types=1);

namespace Vanda\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Vanda\Screen\ScreenRuntime;

/**
 * Serves every route registered with Route::screen():
 * GET renders the page (HTML shell or JSON IR), POST runs a reaction or an action.
 */
final class ScreenController
{
    public function __invoke(Request $request): Response|JsonResponse
    {
        $route = $request->route();
        $screen = $route->defaults['_vanda_screen'];
        $parameters = Arr::except($route->parameters(), ['_vanda_screen']);
        $runtime = ScreenRuntime::boot($screen, $parameters, '/'.ltrim($request->path(), '/'));

        if (! $request->isMethod('POST')) {
            $page = $runtime->ir();

            return $request->header('X-Vanda') === 'true'
                ? new JsonResponse($page)
                : response()->view('vanda::app', ['page' => $page]);
        }

        $payload = $request->json()->all();
        $runtime->restore((string) ($payload['snapshot'] ?? ''));
        $client = (array) ($payload['state'] ?? []);

        return match ($payload['type'] ?? null) {
            'react' => new JsonResponse($runtime->react($client)),
            'action' => new JsonResponse(...$this->flip($runtime->act(
                (string) ($payload['action'] ?? ''),
                (array) ($payload['args'] ?? []),
                (string) ($payload['token'] ?? ''),
                $client,
            ))),
            default => abort(400, 'Unknown Vanda request type. Expected "react" or "action".'),
        };
    }

    /**
     * @param array{0: int, 1: array<string, mixed>} $result
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function flip(array $result): array
    {
        return [$result[1], $result[0]];
    }
}
