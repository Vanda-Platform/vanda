<?php

declare(strict_types=1);

namespace Vanda\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Vanda\Navigation\Shell;

/**
 * Records for the ⌘K palette from every Vanda::search() provider.
 */
final class SearchController
{
    public function __invoke(Request $request, Shell $shell): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        return new JsonResponse(['results' => mb_strlen($query) < 2 ? [] : $shell->search($query)]);
    }
}
