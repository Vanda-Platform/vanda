<?php

declare(strict_types=1);

namespace Vanda\Actions;

enum Variant: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Ghost = 'ghost';
    case Danger = 'danger';
    case Link = 'link';
}
