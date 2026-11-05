<?php

namespace App\Vanda\Forms;

use App\Enums\Country;
use App\Enums\UserStatus;
use App\Models\User;
use App\Vanda\Screens\UserEditScreen;
use Illuminate\Validation\Rule;
use Vanda\Attributes\Secret;
use Vanda\Attributes\Validate;
use Vanda\Form\Form;

final class UserForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:32')]
    public ?string $phone = null;

    #[Validate('nullable|timezone')]
    public ?string $timezone = null;

    public ?int $avatar = null;

    public UserStatus $status = UserStatus::Active;

    #[Validate('nullable')]
    public ?Country $country = null;

    #[Validate('nullable|integer')]
    public ?int $city = null;

    #[Validate('nullable|string|max:32')]
    public ?string $vat = null;

    #[Secret]
    #[Validate('nullable|string|min:8|confirmed')]
    public string $password = '';

    #[Secret]
    public string $password_confirmation = '';

    /** @var list<int> */
    #[Validate('array')]
    public array $roles = [];

    /** @var array<string, bool> personal overrides of role permissions */
    public array $permissions = [];

    public static function fromUser(User $user): self
    {
        return self::fromArray([
            ...$user->only(['name', 'email', 'phone', 'status', 'country', 'city', 'vat', 'timezone']),
            'avatar' => $user->exists ? $user->attachments('avatar')->value('attachments.id') : null,
            'roles' => $user->roles->pluck('id')->all(),
            'permissions' => $user->permissions ?? [],
        ]);
    }

    public function rules(UserEditScreen $screen): array
    {
        return [
            'email' => [Rule::unique('users', 'email')->ignore($screen->user->getKey())],
            'status' => [Rule::enum(UserStatus::class)],
            'country' => [Rule::enum(Country::class)],
            'password' => $screen->user->exists ? [] : ['required'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
        ];
    }
}
