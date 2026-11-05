# Vanda

A screen-centric admin framework for Laravel — Orchid rethought for 2026:
PHP-only screens, instant reactivity, and an API designed to be read and written by AI agents.

> Status: phase 1. Sign-in, roles and permissions (Orchid-compatible tables), Inertia navigation,
> tabs, modals, tables with search / sort / bulk actions, and most form fields work end to end.
> Rich editors, uploads, charts and the command palette come next. See `vanda-analysis.md`.

## Try it

```bash
cd apps/playground && composer install && php artisan migrate:fresh --seed && php artisan serve
```

Open http://localhost:8000/admin and sign in as `admin@example.com` / `password`
(or `i.petrov@example.com` / `password` — a manager without access to roles).
The "Витрина компонентов" screen shows every field and layout.

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
Vanda::permissions(fn () => [PermissionGroup::make('Access')->add('platform.users', 'Users')]);
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

In the browser, hold **Alt** and click any element to see the PHP line that created it and copy that context for an AI.

## Develop

See [AGENTS.md](AGENTS.md) for the layout, commands and rules.
