# Vanda

A screen-centric admin framework for Laravel — Orchid rethought for 2026:
PHP-only screens, instant reactivity, and an API designed to be read and written by AI agents.

> Status: phase 0 (spike). The core pipeline works end to end; the full field and layout set
> from the design is not implemented yet. See `vanda-analysis.md` for the architecture and roadmap.

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
Route::screen('users/{user}/edit', UserEditScreen::class)->name('users.edit');
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
