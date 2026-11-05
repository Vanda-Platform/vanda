<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Illuminate\Validation\Rule;
use Vanda\Attributes\Validate;
use Vanda\Form\Form;

final class UserForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email')]
    public string $email = '';

    public ?string $country = null;

    public ?int $city = null;

    public ?string $vat = null;

    public bool $admin = false;

    public string $notes = '';

    public function rules(UserEditScreen $screen): array
    {
        return ['email' => [Rule::unique('users', 'email')->ignore($screen->user->getKey())]];
    }
}
