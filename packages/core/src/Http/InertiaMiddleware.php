<?php

declare(strict_types=1);

namespace Vanda\Http;

use Illuminate\Http\Request;
use Inertia\Middleware;

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
        $file = AssetController::directory().'/vanda.js';

        return is_file($file) ? substr(md5((string) filemtime($file)), 0, 12) : null;
    }
}
