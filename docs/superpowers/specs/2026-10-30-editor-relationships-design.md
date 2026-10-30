# mantle-editor-relationships — edit Mantle relationships in Gutenberg (issue #718)

## Context
Mantle relationships (`has_many()`, `belongs_to()`, …) can only be set from PHP. Issue alleyinteractive/mantle-framework#718 asks for a standalone package that lets editors set and change post↔post and post↔term relationships in the block editor. It should be opt-in, and opting in should take very little work. Developers opt in from PHP, ideally with an attribute on the model's relationship method; a registry call is also supported. The package writes through Mantle's own relation APIs, so a relationship set in the editor is exactly what `$post->related` returns. Nothing in the framework changes.

**Decided with you:** the package is named `mantle-editor-relationships` with namespace `Mantle\Editor_Relationships`. It's built from `alleyinteractive/create-wordpress-plugin` and lives only locally for now, at `wp-content/plugins/mantle-editor-relationships/`. I commit locally and don't create a GitHub repo.

## Scaffold
1. Shallow-clone the template into `../mantle-editor-relationships`. Delete its `.git`, run `git init`, then run `php configure.php` and pipe answers into stdin. It has no non-interactive mode.
   - Answers: vendor `alleyinteractive`, slug `mantle-editor-relationships`, namespace `Mantle\Editor_Relationships`, class `Editor_Relationships`, front-end build = yes, Composer = yes, PHPStan = yes, standalone = yes, SQLite tests = yes.
   - The script will warn about the non-`wp-` slug and non-`Alley\WP` namespace. That's expected; continue past it.
2. `composer require mantle-framework/database:^1.22`. Then `npm install @wordpress/core-data @wordpress/editor @wordpress/api-fetch` (the template doesn't include these).
3. Align the minimum WordPress version across `plugin.php`, the README and phpcs. Use 6.5, which the template's CLAUDE.md and phpcs already use. Keep the template's PHP `^8.4` and its `class-*.php` file names.
4. Make the initial commit: a clean scaffold with nothing else in it.

## PHP design (`src/`, Feature classes per the template's CLAUDE.md)
- **`src/attributes/class-editor-relationship.php`**: `#[Attribute( Attribute::TARGET_METHOD )] final class Editor_Relationship`. It takes `public readonly ?string $label = null`, and the label falls back to a title-cased method name. Only optional extras go here. For v1: `?int $max = null`, plus `?array $post_status = null` for the picker.
- **`src/class-relationship-definition.php`**: a value object built from `(model class, method, attribute args)`. It gets the `Relation` with `Relation::no_constraints( fn () => ( new $model() )->{$method}() )` (`Database/Model/Relations/Relation.php:99`), then derives:
  - **Target class:** `$relation->get_query()->get_model()`.
  - **Cardinality:** `Has_Many` and `Belongs_To_Many` are many. `Has_One` and `Belongs_To` are single.
  - **Kind:** `post` or `term`, plus the target's `get_object_name()`, which is the post type or taxonomy.
  - **Storage:** owning side (`Belongs_To*` → parent meta/term) or inverse side (`Has_*` → child meta/term, or core terms when post→term). It uses the protected `uses_terms` read through a bound closure, because there are no getters.
  - **Rejects** a parent that isn't a `Post` model, and a relation that isn't one of the 4 core types. In both cases it calls `_doing_it_wrong` and skips the relationship.
  - **REST key:** the method name.
- **`src/class-registry.php`**: a singleton.
  - `register( string $model, string $method, array $args = [] )` and `discover( string ...$models )`, which scans public methods with `Mantle\Support\Reflector::get_attributes_for_method()`, the same pattern as `Support/Traits/Hookable.php:185`.
  - `for_post_type( string ): Relationship_Definition[]`.
  - Model sources:
    - Mantle apps: `Model_Manifest::models()` plus `config('models.register')`, used only when `function_exists( 'Mantle\Framework\…' )` / the `app()` container is bound.
    - Otherwise: a `mantle_editor_relationships_models` filter.
    - Plus explicit `discover()` calls.
  - Helper functions `register_relationship()` and `discover_relationships()` in `src/functions.php`.
- **`src/features/class-register-internal-taxonomy.php`**: on `init` priority 5, registers `mantle_relationship` if `! taxonomy_exists()`, with the same args as `Database/Model_Service_Provider.php:76`. That way the package works without a full Mantle app.
- **`src/features/class-rest-field.php`**: on `rest_api_init`, for each post type that has definitions, registers one REST field `mantle_relationships` with `register_rest_field`. The field is an object keyed by method name, and each value is an int or an int array. Because it's a REST field, core's entity save sends it along with the post.
  - **Get:** `Model::find( $id )->{$method}` → IDs.
  - **Update:** compute the diff, then write through the relation:
    - `Belongs_To`: `associate()` / `dissociate()`.
    - `Belongs_To_Many`: `dissociate()`, then `associate()` each ID. There's no sync.
    - `Has_Many`: `save()` the added IDs, `remove()` the removed ones.
    - `Has_One`: `remove()` the old one, `save()` the new one.
  - **Validation:**
    - Each ID must exist and be the target type/taxonomy, otherwise return `WP_Error` 400.
    - `max` is enforced.
    - For inverse-side writes to other posts, require `current_user_can( 'edit_post', $child )`.
    - Assigning terms requires the taxonomy's `assign_terms` cap.
  - **Schema:** declared so the REST API validates the shape.
- **`src/features/class-editor-assets.php`**: on `enqueue_block_editor_assets`, enqueues the built `entries/relationships-panel` with the `src/assets.php` helpers. It passes the definitions for the current post type, via `wp_add_inline_script`, as `{ name, label, kind, subtype, multiple, max, postStatus }`. It only enqueues when the post type has definitions. Hooks are added in the Feature class, not in a procedural entry `index.php`, following the template's CLAUDE.md.
- **`src/main.php`**: add these features to the existing `Group`.

## Editor UI (`entries/relationships-panel/`, TypeScript)
- `index.tsx` calls `registerPlugin( 'mantle-editor-relationships', … )` and renders one `PluginDocumentSettingPanel` per definition.
- Read and write through `useEntityProp( 'postType', postType, 'mantle_relationships' )`. Edits mark the post dirty and save with it, with no custom fetch.
- Pickers come from `@alleyinteractive/block-editor-tools`, already a dependency: `PostPicker` / `PostSelector` for posts and `TermSelector` for terms. Single or multi depends on `multiple`, and `max` caps the selection. These pickers search through `/wp/v2/search` and the taxonomy REST endpoints, so target post types and taxonomies must have `show_in_rest`. I'll note that in the README and `_doing_it_wrong` when it's missing.
- A small typed `types.ts` for the definition payload, plus a pure helper for normalizing values, which gets a Jest test.

## Tests (PHPUnit with Mantle testkit, `tests/Feature/`)
Fixture models in `tests/Fixtures/` mirror `tests/Database/Model/Relations/HasOneOrManyTest.php:279+` and `BelongsToOneOrManyTest.php:93+`. Coverage:
- **Discovery:** the attribute finds the method and the label falls back. Registry `register()` works. Invalid relations are skipped with `_doing_it_wrong` (use `expectDoingItWrong`).
- **Definition derivation** for each relation type: cardinality, kind, subtype.
- **REST round-trip** (`$this->get_json()` / `->post_json()` against `/wp/v2/<type>/<id>`) for:
  - `belongs_to` meta
  - `belongs_to_many` meta
  - `has_many` post→post meta (writes child meta)
  - `has_many` with `uses_terms`
  - `has_many` post→term (core terms)
  - `has_one`
  
  Each asserts that `$model->{$method}` reflects the write afterwards.
- **Auth and validation:** wrong-type ID → 400. Over `max` → 400. A user without `edit_post` on the child → 403. Field missing from post types with no definitions.
- **Assets:** enqueued only on post types that have definitions.

Known framework quirk to cover in a test: `Has_One` with `uses_terms` calls `wp_set_object_terms( …, append: false )` on the child (`Has_One_Or_Many.php:180`). That can strip the child's other `mantle_relationship` terms. If the test confirms it, I'll file it as a separate framework issue and leave the framework untouched.

## Verification
- `composer test` runs phpcs, phpstan max, rector and phpunit, all green. `npm test` runs tsc, eslint, stylelint and jest. `npm run build` succeeds.
- End to end: mount the plugin in a test site with `wp-env`/`composer dev`, or symlink it into the local broadway site. Add an example `Article` model with `#[Editor_Relationship]` on a post→post and a post→term relationship. Open a post in Gutenberg with agent-browser: the panels appear, pick items, save, reload, and the selections persist. Then confirm with `wp eval` that `Article::find( $id )->related_articles` returns them.
- Write a README covering the attribute usage, the registry call, the `show_in_rest` requirement and the model-source filter.
- Commit locally on `main` in the new repo. Nothing is pushed.
