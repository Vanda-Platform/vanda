<?php

namespace Database\Seeders;

use App\Enums\Country;
use App\Enums\PageStatus;
use App\Enums\UserStatus;
use App\Models\Page;
use App\Models\User;
use App\Vanda\Screens\PageListScreen;
use App\Vanda\Screens\UserEditScreen;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Vanda\Access\Role;
use Vanda\Layouts\Tone;
use Vanda\Notifications\PanelMessage;

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
            'permissions' => ['platform.users' => true, 'platform.roles' => true, 'platform.pages' => true, 'platform.impersonate' => true],
        ]);
        $manager = Role::query()->create([
            'slug' => 'manager',
            'name' => 'Менеджер',
            'permissions' => ['platform.users' => true],
        ]);

        // Sign in to /admin with admin@example.com / password
        $vera = User::factory()->create([
            'name' => 'Вера Миронова',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'phone' => '(912) 345-67-89',
            'country' => Country::Russia,
            'city' => 1,
            'timezone' => 'Europe/Moscow',
        ]);
        $vera->replaceRoles([$admin->id]);

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
                'timezone' => $country->inEu() ? 'Europe/Berlin' : 'Europe/Moscow',
                'last_login_at' => $status === UserStatus::Invited ? null : now()->subMinutes(random_int(2, 60 * 24 * 5)),
            ]);

            if ($role) {
                $user->replaceRoles([$role->id]);
            }
        }

        $this->seedMorePeople();
        $this->seedPages();
        $this->seedNotifications($vera);
    }

    /**
     * A few dozen more accounts so the list has pages, filters and views to show.
     */
    private function seedMorePeople(): void
    {
        $first = ['Алексей', 'Ольга', 'Павел', 'Татьяна', 'Никита', 'Юлия', 'Роман', 'Ксения', 'Артём', 'Дарья', 'Глеб', 'Полина'];
        $last = [['Морозов', 'Морозова'], ['Николаев', 'Николаева'], ['Соколов', 'Соколова'], ['Волков', 'Волкова'], ['Зайцев', 'Зайцева'], ['Белов', 'Белова']];
        $statuses = [UserStatus::Active, UserStatus::Active, UserStatus::Active, UserStatus::Invited, UserStatus::Blocked];

        foreach (range(0, 29) as $index) {
            $name = $first[$index % count($first)];
            $female = in_array($name, ['Ольга', 'Татьяна', 'Юлия', 'Ксения', 'Дарья', 'Полина'], true);
            $surname = $last[intdiv($index, 2) % count($last)][$female ? 1 : 0];

            User::factory()->create([
                'name' => "{$name} {$surname}",
                'email' => sprintf('user%02d@example.com', $index + 1),
                'country' => [Country::Russia, Country::Kazakhstan, Country::Germany][$index % 3],
                'status' => $statuses[$index % count($statuses)],
                'created_at' => now()->subDays(6 * $index + 1),
            ]);
        }
    }

    private function seedPages(): void
    {
        $tree = [
            ['Главная', 'home', 'home', []],
            ['О компании', 'about', 'section', [
                ['Команда', 'team', 'page', []],
                ['Вакансии', 'careers', 'section', [
                    ['Бариста', 'barista', 'page', []],
                    ['Обжарщик', 'roaster', 'page', []],
                    ['Менеджер по закупкам', 'buyer', 'page', []],
                ]],
                ['История', 'history', 'page', [], PageStatus::Draft],
                ['Контакты', 'contacts', 'page', []],
            ]],
            ['Каталог', 'catalog', 'catalog', [
                ['Кофе', 'coffee', 'category', [
                    ['Эфиопия Иргачефф', 'ethiopia', 'product', []],
                    ['Колумбия Супремо', 'colombia', 'product', []],
                    ['Бразилия Сантос', 'brazil', 'product', []],
                    ['Кения АА', 'kenya', 'product', [], PageStatus::Draft],
                ]],
                ['Чай', 'tea', 'category', [
                    ['Пуэр', 'puer', 'product', []],
                    ['Сенча', 'sencha', 'product', []],
                ]],
            ]],
            ['Блог', 'blog', 'blog', []],
            ['Доставка и оплата', 'delivery', 'page', []],
            ['Политика конфиденциальности', 'privacy', 'page', [], PageStatus::Hidden],
        ];

        $create = function (array $nodes, ?int $parent) use (&$create): void {
            foreach ($nodes as $position => $node) {
                [$title, $slug, $template, $children] = $node;
                $page = Page::query()->create([
                    'content' => $slug === 'about'
                        ? '<h2>Как мы обжариваем кофе</h2><p>Покупаем зелёное зерно у фермеров напрямую и обжариваем <strong>небольшими партиями</strong> дважды в неделю.</p><ul><li><p>Светлая обжарка — для фильтра и воронки</p></li><li><p>Средняя — для эспрессо и молочных напитков</p></li></ul>'
                        : null,
                    'notes' => $slug === 'delivery' ? "## Доставка\nБесплатно от **3 000 ₽**.\n- Москва — 1–2 дня\n- Россия — 3–7 дней" : null,
                    'settings' => $slug === 'catalog' ? "{\n  \"currency\": \"RUB\",\n  \"freeShippingFrom\": 3000\n}" : null,
                    'parent_id' => $parent,
                    'position' => $position,
                    'title' => $title,
                    'slug' => $slug,
                    'template' => $template,
                    'status' => $node[4] ?? PageStatus::Published,
                ]);
                $create($children, $page->id);
            }
        };

        $create($tree, null);
    }

    /**
     * A few messages for the bell in the panel header.
     */
    private function seedNotifications(User $admin): void
    {
        $maria = User::query()->where('email', 'm.kovaleva@example.com')->first();
        $messages = [
            PanelMessage::make('Мария Ковалёва ещё не приняла приглашение')->message('Приглашение отправлено 3 дня назад.')->to(UserEditScreen::class, ['user' => $maria])->tone(Tone::Warning),
            PanelMessage::make('Страница «Доставка и оплата» обновлена')->message('Игорь Петров изменил текст и цены доставки.')->to(PageListScreen::class),
            PanelMessage::make('Импорт каталога завершён')->message('Добавлено 18 товаров, ошибок нет.')->tone(Tone::Success),
            PanelMessage::make('Не удалось отправить письмо')->message('Адрес s.lebedev@example.com не принимает почту.')->tone(Tone::Danger),
        ];

        foreach ($messages as $message) {
            $admin->notify($message);
        }

        // The oldest one is already read.
        $admin->notifications()->oldest()->first()?->markAsRead();
    }
}
