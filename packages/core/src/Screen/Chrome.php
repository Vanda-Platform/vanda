<?php

declare(strict_types=1);

namespace Vanda\Screen;

/**
 * What surrounds a screen: the panel with sidebar, or a bare centred card (sign-in pages).
 */
enum Chrome: string
{
    case Panel = 'panel';
    case Card = 'card';
}
