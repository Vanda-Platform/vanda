<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Attributes\Validate;
use Vanda\Form\Form;

final class QuickForm extends Form
{
    #[Validate('required|min:3')]
    public string $title = '';
}
