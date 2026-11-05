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
| `apps/playground` | Laravel 13 app wired to `packages/core` through a path repository |

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
- **Browser input is whitelisted**: only paths of editable fields present on the screen are applied (`ScreenRuntime::applyClientState`).
- **A new node type** needs three things in one change: the PHP class (extends `Vanda\Schema\Node`), a renderer component registered in `packages/renderer/src/nodes/NodeView.tsx`, and a test that checks its IR.
- **Expressions** (`Expr`) are evaluated in PHP and TS. Change both evaluators and add a case to `packages/schema/fixtures/expressions.json`; both test suites read it.
- **Errors explain the fix.** Throw `VandaException` with a message that says what is wrong, where, and what to write instead.
- **Tests first-class.** Screen behaviour is tested with `Vanda::test(Screen::class, [...])->set(...)->call(...)` — no browser needed.
- PHP: `declare(strict_types=1)`, final classes unless meant for extension, docblocks on public API with a short example. TypeScript: strict mode, no `any` outside the node registry.
