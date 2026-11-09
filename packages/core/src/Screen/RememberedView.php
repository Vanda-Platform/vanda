<?php

declare(strict_types=1);

namespace Vanda\Screen;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vanda\Layouts\Table;
use Vanda\Support\Preferences;

/**
 * Tables keep the user's view: search, filters, sort, rows per page and page, per account
 * (Vanda\Support\Preferences), so a list looks as the user left it.
 *
 * - Saving: a visit made by the table's own controls (they send the header X-Vanda-Table) and any
 *   address that carries the table's parameters (a shared link) become the saved view, an empty one
 *   after «Reset» too.
 * - Restoring: a visit with none of them (the menu, a breadcrumb, a typed address) is redirected to
 *   the same address with the saved view, so the address shows what is on the screen. The page is
 *   brought back only on the way back from the list's own screens (the previous page's path lies
 *   under the list's: /admin/users/12/edit for /admin/users); from anywhere else the list starts on
 *   page 1, keeping its search, filters, sort and rows per page.
 *
 * Saved values are cleaned like those of the URL (Table::viewOf()): a filter value that is no
 * longer offered, a sort or a page size the table does not have are dropped. The view belongs to the
 * screen and its route parameters (one per folder, per menu), since its tables share the URL's
 * parameters. Guests, JSON requests (X-Vanda, tools and tests) and tables with ->remember(false)
 * neither save nor restore; a link Inertia prefetches on hover restores but does not save.
 */
final class RememberedView
{
    /** The header the renderer's table controls send with their visits. */
    public const HEADER = 'X-Vanda-Table';

    /** The URL's parameters of a table's view. */
    public const PARAMETERS = ['q', 'filter', 'sort', 'perPage', 'page'];

    /**
     * Saves the view of this visit or sends the user to the saved one.
     *
     * @param array<string, mixed> $parameters route parameters
     */
    public static function handle(Request $request, ScreenRuntime $runtime, array $parameters): ?RedirectResponse
    {
        if (! $request->isMethod('GET') || $request->header('X-Vanda') === 'true' || $request->query->has('export')) {
            return null;
        }

        $user = Auth::guard(config('vanda.guard'))->user();
        $tables = array_values(array_filter(
            $runtime->tree()->nodes(),
            static fn ($node) => $node instanceof Table && $node->isVisible() && $node->remembers(),
        ));

        if ($user === null || $tables === []) {
            return null;
        }

        $key = self::key($runtime->screen::class, $parameters);
        $query = $request->query();
        $given = array_intersect_key($query, array_flip(self::PARAMETERS));

        if ($given !== [] || $request->hasHeader(self::HEADER)) {
            // A link Inertia fetches ahead on hover is not a visit yet: it must not change the saved view.
            if ($request->header('Purpose') !== 'prefetch') {
                Preferences::put($user, $key, self::clean($tables, $given));
            }

            return null;
        }

        $saved = self::clean($tables, Preferences::get($user, $key) ?? []);
        if (! self::returningFromSection($request)) {
            unset($saved['page']);
        }

        return $saved === [] ? null : new RedirectResponse($request->url().'?'.http_build_query([...$query, ...$saved]));
    }

    /**
     * The key of a screen's view: the screen and its route parameters (each folder its own).
     *
     * @param array<string, mixed> $parameters
     */
    public static function key(string $screen, array $parameters): string
    {
        $parameters = array_map(static fn (mixed $value) => is_scalar($value) ? (string) $value : (is_object($value) && method_exists($value, 'getRouteKey') ? (string) $value->getRouteKey() : ''), $parameters);
        ksort($parameters);

        return 'table:'.$screen.($parameters === [] ? '' : ':'.json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * What the screen's tables accept of a view; a parameter any of them takes is kept.
     *
     * @param list<Table> $tables
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function clean(array $tables, array $raw): array
    {
        $view = [];
        foreach ($tables as $table) {
            $view = array_replace_recursive($view, $table->viewOf($raw));
        }

        // The URL's order, so the address reads the same way every time.
        return array_filter(array_merge(array_fill_keys(self::PARAMETERS, null), $view), static fn ($value) => $value !== null);
    }

    /**
     * The previous page is one of the list's own screens: its path lies under the list's.
     */
    private static function returningFromSection(Request $request): bool
    {
        $referer = (string) $request->headers->get('referer', '');
        $host = parse_url($referer, PHP_URL_HOST);
        $path = (string) parse_url($referer, PHP_URL_PATH);

        return $host === $request->getHost() && str_starts_with($path, '/'.trim($request->path(), '/').'/');
    }
}
