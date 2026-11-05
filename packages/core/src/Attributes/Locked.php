<?php

declare(strict_types=1);

namespace Vanda\Attributes;

use Attribute;

/**
 * The property keeps its value between requests but can never be changed
 * from the browser. Eloquent models are locked automatically.
 *
 * On PHP 8.4+ `public private(set)` properties are treated as locked too.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Locked {}
