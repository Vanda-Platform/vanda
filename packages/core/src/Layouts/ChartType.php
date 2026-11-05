<?php

declare(strict_types=1);

namespace Vanda\Layouts;

enum ChartType: string
{
    case Line = 'line';
    case Bar = 'bar';
    /** Horizontal bars, largest first: "top products". */
    case Ranking = 'ranking';
}
