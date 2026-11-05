<?php

declare(strict_types=1);

namespace Vanda\Layouts;

enum FilterType: string
{
    case Select = 'select';
    case Text = 'text';
    case DateRange = 'daterange';
}
