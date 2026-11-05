<?php

namespace Database\Seeders;

use App\Enums\Country;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $people = [
            ['Анна Смирнова', 'anna.smirnova@example.com', Country::Russia, 1, UserStatus::Active],
            ['Игорь Петров', 'i.petrov@example.com', Country::Russia, 2, UserStatus::Active],
            ['Мария Ковалёва', 'm.kovaleva@example.com', Country::Germany, 10, UserStatus::Invited],
            ['Дмитрий Орлов', 'd.orlov@example.com', Country::Kazakhstan, 30, UserStatus::Active],
            ['Елена Васнецова', 'e.vasnetsova@example.com', Country::France, 20, UserStatus::Active],
            ['Сергей Лебедев', 's.lebedev@example.com', Country::Russia, 3, UserStatus::Blocked],
        ];

        foreach ($people as [$name, $email, $country, $city, $status]) {
            User::factory()->create([
                'name' => $name,
                'email' => $email,
                'country' => $country,
                'city' => $city,
                'status' => $status,
                'vat' => $country->inEu() ? $country->value.' 129 273 398' : null,
            ]);
        }
    }
}
