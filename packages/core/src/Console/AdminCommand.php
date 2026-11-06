<?php

declare(strict_types=1);

namespace Vanda\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Vanda\Access\PermissionRegistry;

/**
 * Creates (or updates) a user with every registered permission, like orchid:admin:
 *
 *     php artisan vanda:admin admin@example.com --name="Admin" --password=secret
 */
final class AdminCommand extends Command
{
    protected $signature = 'vanda:admin {email : Sign-in email} {--name=Admin : Display name} {--password= : Password; asked when omitted}';

    protected $description = 'Create a panel user with every registered permission';

    public function handle(PermissionRegistry $permissions): int
    {
        $model = config('auth.providers.'.config('auth.guards.'.config('vanda.guard', 'web').'.provider').'.model');
        $password = $this->option('password') ?: $this->secret('Password');

        if (! is_string($model) || ! class_exists($model)) {
            $this->error('Cannot find the user model of the vanda.guard guard in config/auth.php.');

            return self::FAILURE;
        }
        if (! is_string($password) || $password === '') {
            $this->error('A password is required.');

            return self::FAILURE;
        }

        $user = $model::query()->firstOrNew(['email' => $this->argument('email')]);
        $user->forceFill([
            'name' => $user->name ?? $this->option('name'),
            'password' => Hash::make($password),
            'permissions' => array_fill_keys(array_keys($permissions->labels()), true),
        ])->save();

        $this->info("{$user->email} can sign in with every permission (".count($permissions->labels()).').');

        return self::SUCCESS;
    }
}
