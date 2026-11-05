<?php

namespace App\Vanda\Forms;

use App\Enums\Country;
use App\Enums\UserStatus;
use App\Vanda\Screens\UserEditScreen;
use Illuminate\Validation\Rule;
use Vanda\Attributes\Validate;
use Vanda\Form\Form;

final class UserForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    public UserStatus $status = UserStatus::Active;

    #[Validate('nullable')]
    public ?Country $country = null;

    #[Validate('nullable|integer')]
    public ?int $city = null;

    #[Validate('nullable|string|max:32')]
    public ?string $vat = null;

    public function rules(UserEditScreen $screen): array
    {
        return [
            'email' => [Rule::unique('users', 'email')->ignore($screen->user->getKey())],
            'status' => [Rule::enum(UserStatus::class)],
            'country' => [Rule::enum(Country::class)],
        ];
    }
}
