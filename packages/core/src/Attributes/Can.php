<?php

declare(strict_types=1);

namespace Vanda\Attributes;

use Attribute;

/**
 * Requires a Gate ability for a whole screen (on the class) or one action (on the method):
 *
 *     #[Can('platform.users')]               // a Vanda permission or any Gate ability
 *     #[Can('update', model: 'user')]         // a policy, checked against $this->user / the `user` argument
 *
 * Buttons and menu items that lead to a forbidden screen or action are hidden automatically.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Can
{
    public function __construct(
        public string $ability,
        public ?string $model = null,
    ) {}
}
