<?php

namespace App\Vanda\Forms;

use App\Enums\UserStatus;
use Vanda\Attributes\Validate;
use Vanda\Form\Form;

final class QuickUserForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    public UserStatus $status = UserStatus::Active;
}
