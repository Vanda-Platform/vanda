<?php

declare(strict_types=1);

namespace Vanda\Layouts;

enum Tone: string
{
    case Neutral = 'neutral';
    case Accent = 'accent';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
}
