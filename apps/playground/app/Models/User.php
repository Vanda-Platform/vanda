<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Country;
use App\Enums\UserStatus;
use App\Vanda\Screens\UserEditScreen;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Vanda\Access\HasPermissions;
use Vanda\Activity\ActivityOptions;
use Vanda\Activity\RecordsActivity;
use Vanda\Attachments\Attachable;

#[Fillable(['name', 'email', 'phone', 'password', 'country', 'city', 'vat', 'timezone', 'status', 'permissions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Attachable, HasFactory, HasPermissions, Notifiable, RecordsActivity;

    public function activityOptions(): ActivityOptions
    {
        return ActivityOptions::make('Пользователь')
            ->screen(UserEditScreen::class, 'user')
            ->except('last_login_at', 'email_verified_at')
            ->labels(['permissions' => 'Личные права']);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'country' => Country::class,
            'city' => 'integer',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * URL of the photo uploaded in the user card. Uses eager-loaded attachments
     * when the list loaded them (->with('attachments')), so a table makes no query per row.
     */
    public function avatarUrl(): ?string
    {
        $photo = $this->relationLoaded('attachments')
            ? $this->attachments->firstWhere('group', 'avatar')
            : $this->attachments('avatar')->first();

        return $photo?->url();
    }
}
