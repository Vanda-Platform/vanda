<?php

declare(strict_types=1);

namespace Vanda\Http;

use Illuminate\Http\RedirectResponse;
use Vanda\Access\Impersonation;
use Vanda\Vanda;

/**
 * "Back to my account" in the impersonation banner.
 */
final class StopImpersonationController
{
    public function __invoke(): RedirectResponse
    {
        Impersonation::stop();

        return redirect()->to(Vanda::home());
    }
}
