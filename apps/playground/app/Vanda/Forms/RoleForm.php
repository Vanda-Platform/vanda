<?php

namespace App\Vanda\Forms;

use App\Vanda\Screens\RoleEditScreen;
use Illuminate\Validation\Rule;
use Vanda\Attributes\Validate;
use Vanda\Form\Form;

final class RoleForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|max:255|alpha_dash')]
    public string $slug = '';

    /** @var array<string, bool> */
    public array $permissions = [];

    public function rules(RoleEditScreen $screen): array
    {
        return ['slug' => [Rule::unique('roles', 'slug')->ignore($screen->role->getKey())]];
    }
}
