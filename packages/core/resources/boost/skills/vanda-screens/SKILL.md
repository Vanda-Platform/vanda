---
name: vanda-screens
description: Build and change Vanda admin screens (vanda/core) in a Laravel app — screens, forms, fields, tables, trees, modals, uploads, dependent fields, actions, permissions, the panel's menu and search, and screen tests. Use it whenever a task touches a class that extends Vanda\Screen\Screen or the panel set up with Vanda\Panel.
---

# Vanda screens

Every page of the panel is a PHP class that extends `Vanda\Screen\Screen`. The server turns it into a typed page description and Vanda's prebuilt React renderer draws it, so a screen is only PHP. There is one way to do each thing; this file lists them.

## When to use this skill

Use it to add or change a screen, a form, a table, a tree, a modal, a field, an action, a permission, the menu or the ⌘K search, and to write tests for screens. After every change run `php artisan vanda:check --json` (see "Check your work").

## Anatomy of a screen

```php
#[Can('platform.users')]                       // who may open it (optional)
final class UserEditScreen extends Screen
{
    public User $user;                         // models are locked: never writable from the browser
    public UserForm $form;                     // editable state, validated by the form's rules

    public function query(User $user): array   // route parameters arrive typed; models are bound
    {
        $this->user = $user;
        $this->form = UserForm::fromArray($user->only(['name', 'email', 'status']));

        return [];                             // read-only props, if any: $this->prop('key')
    }

    public function title(): string { return $this->user->exists ? $this->user->name : __('New user'); }
    public function description(): string { return __('Profile and access'); }
    public function breadcrumbs(): iterable { return [Crumb::make(__('Users'))->to(UserListScreen::class), Crumb::make($this->title())]; }
    public function avatar(): ?Avatar { return $this->user->exists ? Avatar::make($this->user->name) : null; }   // record header
    public function badge(): ?Badge { return $this->user->exists ? Badge::make($this->user->status->label(), Tone::Success)->dot() : null; }

    public function commandBar(): iterable { /* buttons beside the title */ }
    public function layout(): iterable { /* blocks of the page */ }

    #[Action]
    public function save(): Result { /* … */ }
}
```

Routes (in a routes file):

```php
Route::vanda('admin', function () {
    Route::screen('users', UserListScreen::class)->name('users');
    Route::screen('users/create', UserEditScreen::class)->name('users.create');   // same screen, no model
    Route::screen('users/{user}/edit', UserEditScreen::class)->name('users.edit');
});
```

Link to a screen with `Panel::url(UserEditScreen::class, ['user' => $user])`, `Link::make(…)->to(…)` or `Crumb::make(…)->to(…)`; never hard-code URLs.

## State and forms

- Public typed properties are the screen's state. Scalars, arrays, enums, dates, models and `Vanda\Form\Form` objects are allowed; anything else fails with a message saying so.
- What the user edits goes into a Form; fields bind to it by dot path (`form.email`). A path the screen does not have fails the page check with "Did you mean …?".
- `#[Locked]` keeps a property out of the browser's reach (an id the screen remembers between requests). Models are always locked.
- `#[Secret]` on a Form property (a password): accepted from the browser, never sent back to it.
- Validation lives in the Form: `#[Validate('required|email')]` on a property and/or `rules(TheScreen $screen): array` for rules that need the screen. Fields add the values they accept by themselves (a Select only its options, a Relation only records of its query).

```php
final class UserForm extends Form
{
    #[Validate('required|max:255')]
    public string $name = '';

    #[Validate('required|email')]
    public string $email = '';

    public ?UserStatus $status = null;

    public function rules(UserEditScreen $screen): array
    {
        return ['email' => [Rule::unique('users')->ignore($screen->user)]];
    }
}
```

## Fields

Every field takes a path and has `->label()`, `->help()` (an «i» by the label with the text in a tooltip; `->help('…', inline: true)` keeps it as text under the field when it must not be missed), `->placeholder()`, `->required()`, `->readonly()` (looks like a value, not an input), `->disabled()`, `->rules([...])`, `->live()` (send each change to the server), and the expressions below.

| Field | For |
|---|---|
| `Input` | text; `->inputType(InputType::Email)`, `->prefix('/')`, `->suffix('₽')`, `->mask(…)`, `->maxLength(…)`, `->min/max/step` |
| `TextArea`, `Password`, `Markdown`, `RichText`, `Code` | longer text; `RichText::make(…)->uploads(5)->embeds(['youtube.com'])` (pictures go up as WebP; `->uploads(5, webp: null)` keeps their format) |
| `TinyMce` | HTML written in TinyMCE (Orchid's editor), its classes, styles and `data-*` kept: `TinyMce::make('form.body')->height(500)->uploads(5)->linkList(fn () => [['title' => 'Catalog', 'value' => '/catalog']])`; `->toolbar('bold italic \| link')`, `->plugins([...TinyMce::PLUGINS, 'autoresize'])`, `->menubar(false)`, `->embeds(['youtube.com'])`. It looks like the panel (toolbar, menus, dialogs, the text in its font). Prefer `RichText` for new texts |
| `Select` | a choice: `->options(UserStatus::class)` (an enum; `label()` and `description()` of its cases), `->options(['a' => 'A'])` or a closure; `->multiple()`, `->searchable()`, `->allowCustom()`, `->tree(fn () => …, label: 'title')`. When labels alone do not tell options apart, give each a grey second line: `->options(fn () => Product::with('category')->get()->map(fn (Product $product) => Option::make($product->id, $product->name)->description($product->category?->name)))` (the search finds it too; `->hint()` is muted text on the same line) |
| `Relation` | records of a model: `->model(User::class, 'name')->searchColumns('name', 'email')`, `->multiple()`, `->description(fn (Event $event) => $event->category?->name.' · '.$event->starts_at->format('d.m.Y'))` (the grey second line); the options come from the server as the user types |
| `CheckBox`, `Switcher`, `Radio`, `RadioButtons` | flags and short choices; a `Radio` option made with `Option::make()->description()` shows its line under the label |
| `DateTimer`, `DateRange`, `NumberRange`, `Range`, `Color`, `TimeZone`, `Map` | dates, ranges and the rest |
| `Upload` | files: `->image()`, `->multiple()->maxFiles(5)`, `->accept('image/*,.pdf')`, `->maxSize(5)`, `->crop(Crop::size(1200, 400))`, `->target(UploadTarget::RelativeUrl)`; pages and scripts (`.html`, `.svg`, `.xml`, `.js`) only when `->accept()` names them. A picture field (`->image()`, `->avatar()`, `->crop()`) re-encodes JPEG/PNG as WebP at quality 85 in the browser before sending (the cropper too; the size limit counts after that): `->webp(quality: 92)`, `->keepFormat()` to send files as they are, `->webp()` on a field without `accept()`. A field that also takes documents keeps its files |
| `Repeater` | rows of the same fields: `Repeater::make('form.specs')->fields([Input::make('name')->required(), Input::make('value')])`; child paths are relative to the row, `->required()` and `->rules()` of a child apply in every row. One field draws a list (Enter adds the next row), up to four short fields a table, the rest blocks: each block is named by `->itemLabel('День')` and its first text field («День 2 · Практика»), folds (`->collapsed()` opens them folded), and its «⋯» duplicates, adds below, moves. `->max(6)` shows «2 из 6» |
| `KeyValue`, `Matrix`, `Utm`, `PermissionsField` | pairs, grids, UTM tags, role permissions |

## Reacting to input

- In the browser, instantly: `->visibleWhen(Expr::field('form.country')->eq('DE'))`, `->requiredWhen(…)`, `->disabledWhen(…)`, `->computed(Expr::field('form.title')->slug())`. `Expr` has `eq`, `neq`, `in`, `gt`, `gte`, `lt`, `lte`, `isEmpty`, `isNotEmpty`, `not`, `and`, `or`, `Expr::all(...)`, `Expr::any(...)`.
- On the server, when another field changes: `->dependsOn('form.country')` re-resolves only that node (for example a Select whose options depend on the country).
- After a change, on the server: `#[Updated('form.country')] public function resetCity(): void { $this->form->city = null; }` (`form.*` matches every field of the form).

## Blocks of a page

- `Layout::section($title, $description)->schema([...])` — a form block: title above, fields in a card, its own buttons at the bottom of the card via `->commands([...])`. Such buttons validate only that block.
- `Layout::card($title, $description)->schema([...])` — lists, tables, tabs: the title inside the card, buttons beside it.
- `Layout::grid([...], columns: 2)` — fields side by side; columns follow the width of the block.
- `Layout::tabs([__('Main') => [...], __('Advanced') => [...]])`.
- `Layout::actions([ActionRow::make(__('Block access'))->description('…')->buttons([...])])` — rows of one action each (danger zone).
- `Layout::legend($record, [Sight::make('email', __('Email'))])`, `Layout::metrics([Metric::make(__('Orders'), 120)])`, `Layout::activity($record)` (the record's history), `Alert::make('…')`, `EmptyState::make('…')`.
- `Layout::files()->id('files')->folder($name, $href, meta: '12 files')->file($key, $name, url: $url, mime: $mime, size: $bytes, date: $date)->fileAction(fn (string $key) => ModalToggle::make(__('Open'))->modal('file')->load($this->loadFile(...), ['id' => (int) $key]))->selected($key)` — a file browser (tiles or a list, the user switches and the browser remembers per node id). A folder opens its address, a file runs its node like `rowAction()`. Search is a field of the screen on the line of the view switch: `->toolbar([Input::make('q')->label(__('Search'))->inputType(InputType::Search)->placeholder(…)])->dependsOn('q')`. `Files::size($bytes)` writes «3.4 MB».
- `Layout::view('blade.name', fn () => [...])` — static HTML from a Blade view (scripts do not run). Anything interactive is a Vanda node, not a Blade island.

A page made only of sections keeps to a readable width by itself; tables and charts take the full width. Do not add wrappers for spacing or width.

## Buttons and actions

```php
Button::make(__('Save'))->icon('save')->primary()->hotkey('mod+s')->action($this->save(...));
Button::make(__('Delete'))->icon('trash')->danger()
    ->confirm(__('The account is deleted for good.'), __('Delete user «:name»?', ['name' => $user->name]), __('Delete user'))
    ->action($this->delete(...), ['user' => $user]);
Link::make(__('Edit'))->icon('pencil')->to(UserEditScreen::class, ['user' => $user]);
DropDown::make()->icon('more')->list([...]);       // secondary actions
```

- `->hotkey('mod+s')`: modifiers `mod` (⌘ on macOS, Ctrl elsewhere), `ctrl`, `alt`, `shift` and one key (`mod+shift+p`, `alt+n`, `mod+enter`). It works on any keyboard layout and inside every field, the browser's own shortcut never runs instead; a modal's button wins while the modal is open; a key without a modifier never fires while the user types. Shortcuts the browser keeps (`mod+n`, `mod+t`, `mod+w`, `mod+q`…) and misspellings (`cmd+s`) fail at once with what to write instead. A button with a hotkey inside a `DropDown` works with the menu closed.
- Only public methods with `#[Action]` can be called from the browser; a button pointing at any other method fails at once. `#[Action(validate: false)]` skips validation (deleting, loading a modal).
- An action returns a `Result`: `Result::toast($message)`, `->redirect(Screen::class, $parameters)`, `Result::redirectTo(…)`, `Result::refresh()`, `->openModal('id')`. Returning nothing refreshes the screen.
- Saving stays on the screen (`Result::toast(__('Saved'))`); a created record opens at its edit screen (`->redirect(self::class, ['user' => $this->user])`); only deleting goes back to the list. Edit screens have no "Back" button: the breadcrumbs lead back.
- Ask before what cannot be undone, and name the record and what is lost; in a bulk action `:count` becomes the number of rows chosen. Prefer undo where the panel offers it (discarding changes, removing a row): it is built in.

## Tables

```php
Layout::table()
    ->query(fn () => User::query()->with('roles'))
    ->searchable(['name', 'email'], __('Search by name or email'))
    ->views([View::make(__('All')), View::make(__('Blocked'), ['status' => UserStatus::Blocked->value])])
    ->filters([
        Filter::select('status', __('Status'))->options(UserStatus::class),
        Filter::dateRange('created', __('Created'))->column('created_at'),
    ])
    ->defaultSort('id', 'desc')
    ->paginate(25, [50, 100])
    ->rowLink(fn (User $user) => Panel::url(UserEditScreen::class, ['user' => $user]))
    ->columns([
        TD::make('name', __('User'))->sortable()->render(fn (User $user) => Persona::make($user->name)->subtitle($user->email)),
        TD::make('status', __('Status'))->render(fn (User $user) => Badge::make($user->status->label(), $user->status->tone())->dot()),
        TD::make('notes', __('Notes'))->truncate('20rem')->hideOnMobile()->defaultHidden(),
    ])
    ->rowActions(fn (User $user) => [/* Link, ModalToggle, Button */])
    ->bulkActions([Button::make(__('Delete'))->danger()->confirm('…', __('Delete the chosen users (:count)?'), __('Delete (:count)'))->action($this->deleteMany(...))])
    ->exportable('users.csv');
```

A bulk action receives `array $ids`. The first column is the row's name and becomes a link by itself when `rowLink()` is set. Filters and views live in the URL, and each user's view (search, filters, sort, rows per page, page) is remembered in the database by itself: coming back through the menu or a breadcrumb restores it (the page only from the list's own screens). Turn it off with `->remember(false)`; never rebuild it yourself with the session. Columns the user hides (`->hideable()`, `->defaultHidden()`) are kept per user and table by Vanda too. Nested records (categories) filter with `Filter::select('category_id', __('Category'))->tree(fn () => Category::all())`: a tree with search, and a parent matches together with its subcategories.

A row that opens a modal (or runs a button) instead of a page takes `->rowAction()`: the closure returns a `ModalToggle`, `Button` or `Link` built for the row, and a click anywhere on the row (or Enter on its name) runs it. Use it instead of `->rowLink()`, never with it (that throws). A row whose node the user may not use is not clickable; return `null` for a row that opens nothing.

```php
->rowAction(fn (Page $page) => ModalToggle::make(__('SEO'))->modal('seo')->load($this->loadSeo(...), ['page' => $page]))
```

## Trees

```php
Layout::tree()
    ->items(fn () => Page::query()->get())       // or ->query(fn () => Page::query())
    ->orderBy('position')
    ->searchable()
    ->expanded(1)
    ->columns([TD::make('title', __('Title'))])
    ->rowLink(fn (Page $page) => Panel::url(PageEditScreen::class, ['page' => $page]))   // or ->rowAction(fn (Page $page) => ModalToggle::make(…)->…)
    ->reorderable($this->move(...));             // #[Action] public function move(int $id, ?int $parent, int $position)
```

A flat reorderable list (records without parents) is a tree too: it shows no expand buttons until a move nests a row.

## Modals

```php
ModalToggle::make(__('Quick edit'))->modal('quickEdit')->load($this->loadQuick(...), ['user' => $user]);

Layout::modal('quickEdit', __('Quick edit'))
    ->schema([Input::make('quick.name')->label(__('Name'))])
    ->buttons([Button::make(__('Save'))->primary()->action($this->saveQuick(...))]);

#[Action(validate: false)]
public function loadQuick(User $user): Result
{
    $this->quickUserId = $user->id;                // #[Locked] public ?int $quickUserId = null;
    $this->quick = QuickUserForm::fromArray($user->only(['name']));

    return Result::refresh()->openModal('quickEdit');
}
```

A button inside a modal validates only that modal's fields. The id in `->modal()` must be a modal of the same screen. «Отмена» is added to the footer by itself; a `->danger()` button there stands apart at the left edge.

## The panel

In a service provider's `boot()`:

```php
Panel::permissions(fn () => [PermissionGroup::make(__('Access'))->add('platform.users', __('Users'))]);

Panel::menu(fn () => [
    MenuItem::make(__('Users'))->icon('users')->group(__('Access'))->to(UserListScreen::class),
]);

Panel::search(fn (string $query) => Search::apply(User::query(), ['name', 'email'], $query)->limit(5)->get()
    ->map(fn (User $user) => SearchResult::make($user->name)->subtitle($user->email)->to(UserEditScreen::class, ['user' => $user])));
```

Menu items, search results and `Link::make(…)->to(…)` that lead to screens the user may not open are hidden, and so are buttons and modal toggles whose `#[Action]` is forbidden, whatever `->visible()` says. The server checks `#[Can]` of an action before anything else in the request, then refuses a call that no button on the page makes: `->visible($user->isNot(auth()->user()))` on a «Delete» button really stops the call on that record. Notifications for the bell are Laravel database notifications with `Vanda\Notifications\PanelMessage`. Signing in as another user goes only through `Vanda\Access\Impersonation::loginAs()` inside an `#[Action]` guarded by `#[Can]`.

## Check your work

- `php artisan vanda:check --json` builds every routed screen (as someone who may do everything) and reports, with `file:line` and "Did you mean …?": field paths, `Expr` fields and `dependsOn` the state does not have, modals that do not exist, unknown icons, and anything a screen throws. Exit code 1 when anything is wrong. `vanda:check users` checks only matching screens.
- `php artisan vanda:inspect /admin/users/1/edit` prints the node tree with the PHP line of every node (`--json` for the raw page, `--as=1` as a given user).
- `php artisan vanda:screens --json` lists screens, routes and actions.
- In development, Alt+click any element in the browser to see the PHP line that created it ("Copy for AI").

## Tests

```php
it('saves the profile and stays on the screen', function () {
    $user = User::factory()->create();

    Panel::test(UserEditScreen::class, ['user' => $user])
        ->set('form.email', 'anna@example.com')       // as the user would; refuses a path that is not a field here
        ->call('save')
        ->assertHasNoErrors()
        ->assertToast('Saved');

    expect($user->fresh()->email)->toBe('anna@example.com');
});
```

`call()` presses a button the page draws for the signed-in user (a row action or a modal toggle too) and validates what that button validates; it fails when no button makes the call, so give the test a user who sees it. `set()` accepts only paths the user can edit on the screen (a Repeater row inside one too); a typo fails with "Did you mean …?" instead of passing on an ignored value. To prove the server ignores a value the browser was not offered (a read-only field, a locked id, a field in a hidden section or hidden by `visibleWhen`), send it with `->tamper('form.role', 'admin')`. Pages are checked in tests as in development (`APP_ENV=testing`), so a wrong path or icon fails the test; `VANDA_DEV=false` turns that off.

Also: `->assertHasErrors(['form.email'])`, `->assertSet('form.city', null)`, `->assertVisible('form.vat')`, `->assertHidden(…)`, `->assertNode('id', fn (array $node) => …)`, `->assertRedirect(…)`, `->callBulk('deleteMany', [1, 2])`, `->options('form.manager', 'ann')` (what a Relation offers).

## Never

- Never refer to a method by name in a string, add `__call`, or expose a method without `#[Action]`.
- Never write HTML, Blade or JavaScript for something a node does; never style with your own CSS.
- Never write `where($column, 'like', …)` for text a user typed; use `Search::apply()`.
- Never put a "Back" button on an edit screen, or redirect to the list after saving.
- Never trust an id the browser could change: keep it in a `#[Locked]` property or pass the model to the action (`->action($this->delete(...), ['user' => $user])`, signed by Vanda).
