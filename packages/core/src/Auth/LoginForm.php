<?php

declare(strict_types=1);

namespace Vanda\Auth;

use Vanda\Attributes\Secret;
use Vanda\Attributes\Validate;
use Vanda\Form\Form;

final class LoginForm extends Form
{
    #[Validate('required|email')]
    public string $email = '';

    #[Secret]
    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = true;
}
