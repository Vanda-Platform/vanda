<?php

declare(strict_types=1);

namespace Vanda\Http;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Vanda\Vanda;

/**
 * Inertia handles navigation between screens (history, prefetch, progress).
 * Reactions and actions keep Vanda's own JSON protocol on the same URL.
 */
final class InertiaMiddleware extends Middleware
{
    protected $rootView = 'vanda::app';

    /**
     * A new renderer build forces a full reload instead of mixing old and new code.
     */
    public function version(Request $request): ?string
    {
        return Vanda::entry()['version'];
    }
}
