## Vanda

Vanda (`vanda/core`) is an admin panel for Laravel built from screens. Every page is a PHP class that extends `Vanda\Screen\Screen`; the server sends a typed description of the page and Vanda's prebuilt React renderer draws it. Write only PHP: no Blade, no JavaScript, no build step. Use the `vanda-screens` skill for tables, trees, modals, uploads, dependent fields and tests.

### Rules

- A screen loads in `query()`, describes itself in `commandBar()` and `layout()`, and acts in public methods marked `#[Action]`. Route it with `Route::screen('users/{user}/edit', UserEditScreen::class)` inside `Route::vanda('admin', fn () => …)`.
- State is the screen's public typed properties. Editable values live in a `Vanda\Form\Form` (`public UserForm $form`) and fields bind to them by path: `Input::make('form.email')`. Models are locked: the browser can never write them.
- Buttons point at actions with first-class callables: `->action($this->save(...))`. There are no string method names and no magic; variants are methods (`->primary()`, `->danger()`) or enums.
- Saving stays on the screen: `return Result::toast(__('Saved'));`. A new record opens at its edit screen: `Result::toast(__('Created'))->redirect(self::class, ['user' => $this->user])`. Only deleting goes back to the list.
- An action that cannot be undone asks first and names the record: `->confirm(__('The account is deleted for good.'), __('Delete user «:name»?', ['name' => $user->name]), __('Delete user'))`.
- Guard screens and actions with `#[Can('platform.users')]`; buttons, links and menu items that lead to forbidden things disappear by themselves (`->visible(true)` does not bring them back), and the server checks again.
- Search text a user typed with `Vanda\Support\Search::apply($query, ['name', 'email'], $text)`, never `where($column, 'like', …)`: it ignores case in every alphabet.
- Icons are names from the renderer's set (`'save'`, `'trash'`, `'users'`, `'plus'`); an unknown one fails the page check with the list.
- Extensions (`vanda/settings` and others) bring screens, permissions and search; mount their screens inside `Route::vanda()` with `Route::extension(SettingsExtension::class)` and configure them by registering them again in a service provider: `Panel::extension(SettingsExtension::make()->…)`. An extension's own rules come with its package.

### Check your work

- `php artisan vanda:check --json` builds every screen and lists each problem with its `file:line` and "Did you mean …?" (field paths, `dependsOn`, modals, icons, exceptions). The exit code is 1 when anything is wrong. Run it after every change to a screen.
- `php artisan vanda:inspect /admin/users/1/edit` prints the node tree a screen produces, with the PHP line of every node.
- In development every page runs the same checks, and the error page says what to write instead.
- Test screens without a browser; `set()` refuses a path that is not a field of the screen:

@verbatim
<code-snippet name="A screen test" lang="php">
Panel::test(UserEditScreen::class, ['user' => $user])
    ->set('form.email', 'anna@example.com')
    ->call('save')
    ->assertHasNoErrors()
    ->assertToast('Saved');
</code-snippet>
@endverbatim

### A screen

@verbatim
<code-snippet name="An edit screen" lang="php">
final class UserEditScreen extends Screen
{
    public User $user;          // a model: locked, the browser cannot swap it
    public UserForm $form;      // what the user edits, with its validation rules

    public function query(User $user): array
    {
        $this->user = $user;
        $this->form = UserForm::fromArray($user->only(['name', 'email']));

        return [];
    }

    public function title(): string
    {
        return $this->user->exists ? $this->user->name : __('New user');
    }

    public function commandBar(): iterable
    {
        return [Button::make(__('Save'))->icon('save')->primary()->hotkey('mod+s')->action($this->save(...))];
    }

    public function layout(): iterable
    {
        return [
            Layout::section(__('Profile'), __('Name and email for signing in.'))->schema([
                Layout::grid([
                    Input::make('form.name')->label(__('Name'))->required(),
                    Input::make('form.email')->label(__('Email'))->required(),
                ]),
            ]),
        ];
    }

    #[Action]
    public function save(): Result
    {
        $created = ! $this->user->exists;
        $this->user->fill($this->form->toArray())->save();   // the form is already validated

        return $created
            ? Result::toast(__('Created'))->redirect(self::class, ['user' => $this->user])
            : Result::toast(__('Saved'));
    }
}
</code-snippet>
@endverbatim
