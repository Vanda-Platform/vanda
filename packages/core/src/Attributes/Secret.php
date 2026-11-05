<?php

declare(strict_types=1);

namespace Vanda\Attributes;

use Attribute;

/**
 * A Form property that is accepted from the browser but never sent back:
 * it is left out of the page state and of the snapshot (passwords, tokens).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Secret {}
