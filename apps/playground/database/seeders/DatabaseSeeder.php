<?php

namespace Database\Seeders;

use App\Enums\Country;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Vanda\Access\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = Role::query()->create([
            'slug' => 'admin',
            'name' => 'Администратор',
            'permissions' => ['platform.users' => true, 'platform.roles' => true],
        ]);
        $manager = Role::query()->create([
            'slug' => 'manager',
            'name' => 'Менеджер',
            'permissions' => ['platform.users' => true],
        ]);

        // Sign in to /admin with admin@example.com / password
        User::factory()->create([
            'name' => 'Вера Миронова',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'country' => Country::Russia,
            'city' => 1,
        ])->replaceRoles([$admin->id]);

        $people = [
            ['Анна Смирнова', 'anna.smirnova@example.com', Country::Russia, 1, UserStatus::Active, $manager],
            ['Игорь Петров', 'i.petrov@example.com', Country::Russia, 2, UserStatus::Active, $manager],
            ['Мария Ковалёва', 'm.kovaleva@example.com', Country::Germany, 10, UserStatus::Invited, null],
            ['Дмитрий Орлов', 'd.orlov@example.com', Country::Kazakhstan, 30, UserStatus::Active, null],
            ['Елена Васнецова', 'e.vasnetsova@example.com', Country::France, 20, UserStatus::Active, $manager],
            ['Сергей Лебедев', 's.lebedev@example.com', Country::Russia, 3, UserStatus::Blocked, null],
        ];

        foreach ($people as [$name, $email, $country, $city, $status, $role]) {
            $user = User::factory()->create([
                'name' => $name,
                'email' => $email,
                'country' => $country,
                'city' => $city,
                'status' => $status,
                'vat' => $country->inEu() ? $country->value.' 129 273 398' : null,
            ]);

            if ($role) {
                $user->replaceRoles([$role->id]);
            }
        }
    }
}
