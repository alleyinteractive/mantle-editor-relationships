# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

A WordPress plugin that lets developers expose Mantle model relationships as editable panels in the block editor. It was scaffolded from `alleyinteractive/create-wordpress-plugin` and follows its conventions.

## Rules

- Add functionality via Feature classes in `src/features/`.
- Register features in `src/main.php`.
- Do NOT add hooks in procedural files.
- Do NOT modify placeholder tokens unless instructed.
- Code in `src/` should follow WordPress coding standards (via `alleyinteractive/alley-coding-standards`), WordPress file name formats, use strict types, and pass PHPStan level max.
- Code in `tests/` should extend `tests/TestCase.php`, use Mantle Testkit utilities, and following PSR-4 autoloading (`Mantle\Editor_Relationships\Tests\` → `tests/`).
- Prefer Mantle APIs over custom implementations.
- Ensure all PHP passes PHPStan level max.
- Ensure all linting checks pass (phpcs/phpstan/rector for PHP; eslint/tsc for JS).
- Post/term meta should be registered through `config/post-meta.json` and `config/term-meta.json` using `register_meta_from_file()`, not hard-coded in PHP.

## Common commands

**Local dev environment** (wp-env + webpack in parallel):
```sh
npm install
composer dev          # runs `wp-env start` + `npm run start` concurrently
```

**Build / watch front-end assets:**
```sh
npm run build         # production build via @alleyinteractive/build-tool
npm run start         # watch mode
npm run start:hot     # HMR
```

**Tests & lint:**
```sh
npm run test          # check-types + eslint + stylelint + jest
npm run jest          # jest only (passes with no tests)
npm run jest:watch    # jest in watch mode
npm run check-types   # tsc --noEmit
npm run eslint:fix    # auto-fix JS/TS lint issues
npm run stylelint:fix # auto-fix SCSS lint issues
npm run packages-update  # update @wordpress/* packages to latest (wp-7.0 dist-tag)
composer test         # runs @lint then @phpunit
composer phpunit      # PHPUnit only
composer phpstan      # PHPStan at level `max` (paths: entries/, src/, mantle-editor-relationships.php)
composer phpcs        # alley-coding-standards
composer rector       # dry-run; `composer rector:fix` to apply
composer lint:fix     # rector:fix + phpcbf
composer serve        # wp-env start only (no webpack)
```

Run a single PHPUnit test: `vendor/bin/phpunit --filter ExampleUnitTest`.
Run a test suite: `vendor/bin/phpunit --testsuite Feature` or `--testsuite Unit`.

**Scaffolding:**
```sh
npm run create-entry     # new directory under entries/
npm run create-slotfill  # new slotfill entry
npm run scaffold         # run @alleyinteractive/scaffolder (reads .scaffolder/)
```
`.scaffolder/plugin-feature/` generates a new `Feature` class in `src/features/` plus a matching test in `tests/Feature/`.

**Release:** `npm run release` bumps the version in `mantle-editor-relationships.php` and pushes; GitHub Actions (`built-release.yml`) compiles and tags a `*-built` branch containing the front-end assets.

## Architecture

### Bootstrap flow
`mantle-editor-relationships.php` (the WordPress entry file) loads Composer's `vendor/wordpress-autoload.php` (from `alleyinteractive/composer-wordpress-autoloader`, which maps `Mantle\Editor_Relationships\` → `src/`), then requires `src/assets.php`, `src/functions.php`, `src/main.php` and calls `main()`.

If `vendor/` is absent but a parent project has already loaded Composer (i.e. the plugin is a Composer dependency), bootstrap continues silently; otherwise an admin notice is shown.

### Feature-based composition
`src/main.php` constructs an `Alley\WP\Features\Group` (from `alleyinteractive/wp-type-extensions`) containing `Feature` implementations and calls `boot()`. Each feature is a class in `src/features/` implementing `Alley\WP\Types\Feature` with a `boot(): void` method. **To add plugin behavior, create a new `Feature` class (via `npm run scaffold`) and register it in `main()`** — don't add hooks directly in procedural files.

### How relationships flow
- `Attributes\Editor_Relationship` marks a model's relationship method. `Registry` (singleton) discovers attributed methods on models from the `mantle_editor_relationships_models` filter, the Mantle model manifest when a Mantle app is running, and explicit `discover_relationships()` / `register_relationship()` calls (`src/functions.php`).
- `Relationship_Definition::from()` resolves the relation with `Relation::no_constraints()` and derives the target model, single vs. multiple, and post vs. term. Unsupported relations throw `InvalidArgumentException`, which the registry turns into `_doing_it_wrong`.
- `Relationship_Store` reads and writes through Mantle's relation APIs (`associate`/`dissociate`/`save`/`remove`) and validates IDs and capabilities before writing.
- Features: `Register_Internal_Taxonomy` (registers `mantle_relationship` when no Mantle app has), `Rest_Field` (the `mantle_relationships` field, edit context only), `Editor_Assets` (enqueues `build/relationships-panel/` with the post type's config as `window.mantleEditorRelationships`).

### Front-end entries
`entries/relationships-panel/` is the only entry: a `registerPlugin` render of one `PluginDocumentSettingPanel` per relationship, using the `Selector` from `@alleyinteractive/block-editor-tools` and `useEntityProp` on the `mantle_relationships` field. It compiles to `build/relationships-panel/` with an `index.asset.php` map; helpers in `src/assets.php` resolve URLs. Name stylesheets `index.scss` — the build tool splits files named `style.scss` into a separate `style-*` chunk.

### Tests
- PSR-4: `Mantle\Editor_Relationships\Tests\` → `tests/` (note: this is `autoload-dev`, not the runtime autoloader).
- Base class: `tests/TestCase.php` extends `Mantle\Testkit\Test_Case` and uses `Prevent_Remote_Requests`. New tests should extend this, not `Test_Case` directly.
- `tests/bootstrap.php` uses `Mantle\Testing\manager()` with `maybe_rsync_plugin()` — the test runner rsyncs this plugin into a WordPress install before booting.
- Feature tests live in `tests/Feature/`; unit tests in `tests/Unit/`. The feature scaffolder generates tests into `tests/Feature/`.

## Conventions to respect

- PHP 8.4+, WordPress 6.6+ minimum. Strict types, PHPStan **level max** across `entries/`, `src/`, `mantle-editor-relationships.php`. New PHP code must pass level max.
- Namespace is `Mantle\Editor_Relationships\...` with feature classes under `...\Features\`.
- Coding standard: `alleyinteractive/alley-coding-standards` (WordPress-VIP-flavored). Inline `phpcs:ignore` is used sparingly for unavoidable VIP rules.
- Class files follow WordPress `class-{slug}.php` naming (not PSR-4 filename casing) — the wordpress-autoloader handles both.
- Node 24 / npm 11 (see `engines` and `.nvmrc`).

## Mantle Framework

This plugin uses [Mantle](https://mantle.alley.com/) — Alley's WordPress framework — for its helpers, testing utilities, and support libraries. Prefer Mantle's APIs over hand-rolled equivalents when adding functionality:

- **Testing:** Use Mantle Testkit (`Mantle\Testkit\Test_Case`) as the base, factories for fixture data (`static::factory()->post->create(...)`), HTTP testing helpers (`$this->get(...)`, `assertOk()`, etc.), request faking, and traits like `Prevent_Remote_Requests`, `Refresh_Database`, `Installs_Plugin`.
- **Helpers:** Use `mantle-framework/support` for collections (`collect()`), strings (`Str::*`), arrays (`Arr::*`), and meta registration (`register_meta_from_file()`).
- **Database / Models:** Mantle's Eloquent-style models and query builders are preferred over raw `WP_Query` when data access gets complex.
- **HTTP client, queues, events, scheduling:** Use Mantle's facades/services instead of re-implementing.

**Reference:** Always consult [`https://mantle.alley.com/llms.txt`](https://mantle.alley.com/llms.txt) (the LLM-optimized docs index) to discover the correct Mantle API, class, or trait before writing custom code. Fetch it with WebFetch when you need to verify a helper exists or find the right namespace.
