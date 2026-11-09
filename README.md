# Vanda

A screen-centric admin framework for Laravel — Orchid rethought for 2026:
PHP-only screens, instant reactivity, and an API designed to be read and written by AI agents.

> Status: phase 3. Everything from phase 2 (tables with filters and views, trees, `Select::tree()`,
> uploads, editors, charts, ⌘K) plus record headers with avatar and status, an "unsaved changes" bar,
> signing in as another user, the notification bell, empty states and action rows. Next: maps,
> real-time updates, MCP for agents. See `vanda-analysis.md`.

## Try it

```bash
cd apps/playground && composer install && php artisan migrate:fresh --seed && php artisan serve
```

Open http://localhost:8000/admin and sign in as `admin@example.com` / `password`
(or `i.petrov@example.com` / `password` — a manager without access to roles).
The "Витрина компонентов" screen shows every field and layout; "Страницы" shows a tree
you can reorder by dragging or with Alt+arrows; ⌘K searches pages, actions and records.

## A screen

```php
final class UserEditScreen extends Screen
{
    public User $user;          // models are locked: the browser can't swap them
    public UserForm $form;      // typed form state with its validation rules

    public function query(User $user): array
    {
        $this->user = $user;
        $this->form = UserForm::fromArray($user->only(['name', 'email', 'country', 'city', 'vat']));

        return [];
    }

    public function commandBar(): iterable
    {
        return [Button::make('Save')->primary()->hotkey('mod+s')->action($this->save(...))];
    }

    public function layout(): iterable
    {
        $inEu = Expr::field('form.country')->in(['DE', 'FR']);

        return [
            Layout::section('Address')->schema([
                Select::make('form.country')->label('Country')->options(Country::class),
                Select::make('form.city')->label('City')
                    ->dependsOn('form.country')                       // re-resolved on the server, sent as a patch
                    ->options(fn (Get $get) => Country::tryFrom((string) $get('form.country'))?->cities() ?? []),
                Input::make('form.vat')->label('VAT ID')
                    ->visibleWhen($inEu)->requiredWhen($inEu),        // evaluated in the browser, no request
            ]),
        ];
    }

    #[Updated('form.country')]
    public function resetCity(): void
    {
        $this->form->city = null;
    }

    #[Action]
    public function save(): Result
    {
        $this->user->fill($this->form->toArray())->save();   // already validated

        return Result::toast('Saved')->redirect(UserListScreen::class);
    }
}
```

```php
// Sign-in, sign-out and your screens behind auth, under /admin
Route::vanda('admin', function () {
    Route::screen('users/{user}/edit', UserEditScreen::class)->name('users.edit');
});

// Permissions become Gate abilities; #[Can('platform.users')] guards a screen or an action
Panel::permissions(fn () => [PermissionGroup::make('Access')->add('platform.users', 'Users')]);

// The bell: Laravel database notifications in Orchid's DashboardMessage shape
$admin->notify(PanelMessage::make('Import finished')->message('18 products added')->tone(Tone::Success));

// Records for the ⌘K palette
Panel::search(fn (string $q) => User::where('name', 'like', "%{$q}%")->limit(5)->get()
    ->map(fn (User $user) => SearchResult::make($user->name)->to(UserEditScreen::class, ['user' => $user])));
```

```php
// A list with filters in the URL (?filter[status]=blocked, as in Orchid), views and a row menu
Layout::table()
    ->query(fn () => User::query())
    ->filters([Filter::select('status', 'Status')->options(UserStatus::class), Filter::dateRange('created', 'Created')->column('created_at')])
    ->views([View::make('All'), View::make('Blocked', ['status' => 'blocked'])])
    ->columns([TD::make('name', 'Name')->sortable(), TD::make('country', 'Country')->defaultHidden()])
    ->rowActions(fn (User $user) => [Link::make('Edit')->to(UserEditScreen::class, ['user' => $user])])
    ->exportable('users.csv');

// A tree; the action receives the dragged row's new place
Layout::tree()->query(fn () => Page::query())->orderBy('position')
    ->columns([TD::make('title', 'Title')])
    ->reorderable($this->move(...));      // #[Action] public function move(int $id, ?int $parent, int $position)
```

## How it works

1. `GET` runs `query()`, builds the node tree and sends the page IR (`vanda/v1`) with a signed state snapshot.
2. The React renderer draws it. `visibleWhen` / `requiredWhen` / `computed` run in the browser instantly.
3. Changing a field that others `dependsOn` (or a `live()` field) posts a **reaction**: the server applies the
   whitelisted values, runs `#[Updated]` hooks and returns JSON patches for the affected nodes only.
4. A button posts an **action**: values → hooks → validation → the `#[Action]` method → `Result`.

## For agents

```bash
php artisan vanda:screens                         # every screen, route and action
php artisan vanda:inspect /admin/users/1/edit     # node tree, rules, dependencies, PHP source lines
```

In development, in tests and in `vanda:inspect`, every page is checked before it leaves the server: a field path,
an `Expr` field or a `dependsOn` the screen's state does not have, a `ModalToggle` for a missing modal and an unknown
icon fail with one message that names each node, its `file:line` and what was probably meant.

In the browser, hold **Alt** and click any element to see the PHP line that created it and copy that context for an AI.

## Develop

See [AGENTS.md](AGENTS.md) for the layout, commands and rules.
