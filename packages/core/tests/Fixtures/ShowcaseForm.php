<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Attributes\Secret;
use Vanda\Attributes\Validate;
use Vanda\Form\Form;

final class ShowcaseForm extends Form
{
    #[Validate('required')]
    public string $name = '';

    #[Secret]
    public string $token = '';

    /** @var list<array{name?: string, value?: string}> */
    public array $specs = [];

    /** @var list<array{key: string, value: string}> */
    public array $utm = [];

    /** @var array<string, bool> */
    public array $permissions = [];
}
