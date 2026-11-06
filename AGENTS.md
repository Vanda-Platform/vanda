# Working on Vanda

Vanda is a screen-centric admin framework for Laravel, a rethought Orchid. Screens are PHP
classes; the server sends a typed page description (IR, `vanda/v1`) and a prebuilt React
renderer draws it. Read `vanda-analysis.md` for the why; this file is the how.

## Layout

| Path | What |
|---|---|
| `packages/core` | PHP package `vanda/core`: Screen, nodes, IR, state snapshot, actions, reactions, validation, CLI, test helper |
| `packages/renderer` | TypeScript package `@vanda/renderer`: React 19 + React Aria + Tailwind 4. Builds into `packages/core/resources/dist` |
| `packages/schema/fixtures` | Data shared by PHP and TS tests (expression semantics) |
| `apps/playground` | Laravel 13 app wired to `packages/core` through a path repository (users, roles, pages tree, dashboard, showcase) |

## Commands

```bash
# PHP core
cd packages/core && composer install && vendor/bin/pest

# Renderer (from the repo root)
npm install
npm run typecheck && npm test
npm run build          # REQUIRED after any renderer change: PHP serves the built files

# Playground
cd apps/playground && php artisan migrate:fresh --seed && php artisan serve
php artisan vanda:screens
php artisan vanda:inspect /admin/users/1/edit        # add --json for the raw IR
```

## Rules

- **No magic.** No `__call`, no string references to methods. Every option is a typed method; variants are enums (`Variant`, `Tone`, `InputType`).
- **Actions** are screen methods with `#[Action]`; buttons point to them with first-class callables: `->action($this->save(...))`. Never expose a method any other way.
- **State** lives in public typed screen properties. It travels as signed JSON (`Snapshot`), never PHP `serialize`. Models and `#[Locked]` properties are never writable from the browser.
- **Browser input is whitelisted**: only paths of editable fields present on the screen are applied (`ScreenRuntime::applyClientState`), after `Field::sanitize()` for structured values.
- **Access**: `#[Can]` on a screen class or an action method; buttons, modal toggles and menu items that lead to forbidden things are hidden automatically, and the server checks again on every request.
- **Secrets** (`#[Secret]` on a Form property) are accepted from the browser but never written to page state or the snapshot.
- **Validation scope**: a button inside `Layout::modal()` validates only that modal's fields; a button in the `->commands()` of `Layout::section()` or `Layout::card()` validates only that block (the `ValidationScope` interface); any other button validates everything outside modals.
- **Blocks**: `Layout::section()` puts the title on the left and the fields on the right (settings pages); `Layout::card()` puts the title on top with its buttons beside it (lists, tabs). A button that works on one block goes in that block's `->commands()`, not in the screen's command bar.
- **Navigation** goes through Inertia (`router.visit`, `<Link>`); reactions and actions stay on Vanda's JSON protocol in `transport.ts`.
- **Saving stays on the screen**: a save action returns `Result::toast('Saved')`; a record created on a create screen opens at its edit URL with `Result::toast('Created')->redirect(self::class, ['item' => $this->item])`. Only deleting goes back to the list. A toast before a redirect is shown on the next page.
- **Values the browser decides** (where a tree row was dropped) reach an action only through `ActionCall::withInput($this->move(...), ['id', 'parent', 'position'])`: the token signs the argument names, the server keeps only those names and scalar values. Never sign a token for arbitrary client arguments.
- **Uploads** are multipart POSTs to the screen's own URL (`type=upload`, `field=<path>`, also `form.rows.2.image` inside a Repeater), so the screen's `#[Can]` and middleware apply. `Upload::store()` checks size and type (no `.php`, no SVG under `image/*`); `Upload::sanitize()` keeps only values already in the field or uploaded by the current user. The value is the attachment id or, with `->target(UploadTarget::RelativeUrl)`, the file's URL. `->crop(Crop::size(556, 322), ...)` crops in the browser (cropperjs) before the upload; the server never crops.
- **Options from the server**: a `Relation` field asks for its records with a JSON request `type=options` (`ScreenRuntime::options()`), after the browser's values are applied, so its query may read other fields.
- **Values a field accepts by itself** come from `Field::implicitRules()`: a Select only its options (unless `->allowCustom()`), a Relation only records of its query. They run with every validating action, next to `->rules()`.
- **Blade islands**: `Layout::view('name', fn () => [...])` renders a Blade view on the server into a `view` node, again on every update. It is static HTML (scripts do not run); anything interactive is a node type.
- **Rich text** is cleaned on the server by `Vanda\Support\Html::clean()` (tag and attribute allowlist, `<iframe>` only from `RichText::embeds()` hosts). Text the user did not change is saved as it was; markup the editor cannot keep opens in HTML mode. The Markdown preview escapes raw HTML and drops unsafe links.
- **Heavy renderer components** (dates, tree, editors, charts, map, cropper) load as separate chunks through `chunk()` in `NodeView.tsx`. Keep new heavy dependencies out of the main bundle the same way.
- **A record's screen** returns `avatar()` and `badge()` for its header; the renderer then draws the design's record header. The "unsaved changes" bar and the leave warning come for free on any screen whose command bar has a primary button with an action.
- **Impersonation** goes only through `Vanda\Access\Impersonation::loginAs()` inside an `#[Action]` guarded by `#[Can]`; the panel shows the banner and the way back by itself.
- **Notifications** for the bell are Laravel database notifications; send `Vanda\Notifications\PanelMessage` (Orchid's DashboardMessage data shape).
- **Assets** are hashed files listed in `resources/dist/.vite/manifest.json`; `Panel::entry()` reads it. Never load the entry with a query string: chunks import it by its plain URL, and a second URL means a second copy of React.
- **A new node type** needs three things in one change: the PHP class (extends `Vanda\Schema\Node`), a renderer component registered in `packages/renderer/src/nodes/NodeView.tsx`, and a test that checks its IR.
- **Expressions** (`Expr`) are evaluated in PHP and TS. Change both evaluators and add a case to `packages/schema/fixtures/expressions.json`; both test suites read it.
- **Errors explain the fix.** Throw `VandaException` with a message that says what is wrong, where, and what to write instead.
- **Tests first-class.** Screen behaviour is tested with `Panel::test(Screen::class, [...])->set(...)->call(...)` — no browser needed.
- PHP: `declare(strict_types=1)`, final classes unless meant for extension, docblocks on public API with a short example. TypeScript: strict mode, no `any` outside the node registry.
