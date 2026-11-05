<?php

declare(strict_types=1);

namespace Vanda\Fields;

enum InputType: string
{
    case Text = 'text';
    case Email = 'email';
    case Password = 'password';
    case Number = 'number';
    case Tel = 'tel';
    case Url = 'url';
    case Search = 'search';
    case Date = 'date';
    case DateTime = 'datetime-local';
}
