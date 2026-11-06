<?php

declare(strict_types=1);

namespace Vanda\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Vanda\Screen\ScreenRuntime;

/**
 * Serves every route registered with Route::screen():
 * GET renders the page (HTML shell or JSON IR), POST runs a reaction or an action,
 * stores an upload or finds the options of a Relation field.
 */
final class ScreenController
{
    public function __invoke(Request $request): SymfonyResponse
    {
        $route = $request->route();
        $screen = $route->defaults['_vanda_screen'];
        $parameters = Arr::except($route->parameters(), ['_vanda_screen']);
        $runtime = ScreenRuntime::boot($screen, $parameters, '/'.ltrim($request->path(), '/'));

        if (! $request->isMethod('POST')) {
            if (is_string($request->query('export')) && $request->header('X-Vanda') !== 'true') {
                return $runtime->export($request->query('export'));
            }

            $page = $runtime->ir();

            return $request->header('X-Vanda') === 'true'
                ? new JsonResponse($page)
                : Inertia::render('vanda', ['page' => $page])->toResponse($request);
        }

        if ($request->input('type') === 'upload') {
            $runtime->restore((string) $request->input('snapshot', ''));

            return new JsonResponse(...$this->flip($runtime->upload((string) $request->input('field', ''), $request->file('file'))));
        }

        $payload = $request->json()->all();
        $runtime->restore((string) ($payload['snapshot'] ?? ''));
        $client = (array) ($payload['state'] ?? []);

        return match ($payload['type'] ?? null) {
            'react' => new JsonResponse($runtime->react($client)),
            'options' => new JsonResponse($runtime->options($client, (string) ($payload['field'] ?? ''), (string) ($payload['query'] ?? ''))),
            'action' => new JsonResponse(...$this->flip($runtime->act(
                (string) ($payload['action'] ?? ''),
                (array) ($payload['args'] ?? []),
                (string) ($payload['token'] ?? ''),
                $client,
                isset($payload['node']) ? (string) $payload['node'] : null,
                (bool) ($payload['bulk'] ?? false),
                isset($payload['inputs']) ? (array) $payload['inputs'] : null,
            ))),
            default => abort(400, 'Unknown Vanda request type. Expected "react", "action" or "options".'),
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
