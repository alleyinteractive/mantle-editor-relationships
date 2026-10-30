# Mantle Editor Relationships Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A standalone WordPress plugin, `mantle-editor-relationships`. A developer marks a Mantle model relationship with `#[Editor_Relationship]`, or registers it in code, and it becomes an editable panel in Gutenberg.

**Architecture:**
- PHP turns each opted-in relationship into a `Relationship_Definition`, using the relation instance the model method returns.
- One REST field, `mantle_relationships`, on each affected post type reads and writes the related IDs through Mantle's own relation APIs (`save`/`remove`/`associate`/`dissociate`).
- A TypeScript editor plugin renders one `PluginDocumentSettingPanel` per definition. It edits that field with `useEntityProp`, so changes save with the post.

**Tech Stack:**
- Base: `alleyinteractive/create-wordpress-plugin` (PHP 8.4, alley-coding-standards, PHPStan max, rector, Mantle testkit), plus `mantle-framework/database ^1.22`.
- Editor: TypeScript/React via `@alleyinteractive/build-tool`, with the `@alleyinteractive/block-editor-tools` `Selector` component.

**Spec:** `docs/superpowers/specs/2026-10-30-editor-relationships-design.md`. It's a copy of the approved plan `~/.claude/plans/starry-mapping-fog.md`, committed in Task 1.

## Global Constraints
- **Location:** `/Users/srtfisher/broadway/www/mantle/wp-content/plugins/mantle-editor-relationships`. Local git only: never push, never create a GitHub repo.
- **Names:**
  - Composer `alleyinteractive/mantle-editor-relationships`
  - Namespace `Mantle\Editor_Relationships`
  - Slug and text domain `mantle-editor-relationships`
  - Main file `mantle-editor-relationships.php`
- **Conventions:** follow the template's CLAUDE.md. Features are `final` classes implementing `Alley\WP\Types\Feature` in `src/features/`, registered in `src/main.php`. No hooks in procedural files. `class-*.php` file names. `declare( strict_types=1 );`. Full types. PHPStan max.
- **Versions:** WordPress minimum 6.5 everywhere (plugin header, README, phpcs `minimum_supported_wp_version`). PHP `^8.4`.
- **Framework:** nothing in `mantle-framework` changes.
- **Done means** `composer test` and `npm test` pass and `npm run build` succeeds.
- **Comments:** only where removing one would lead an editor to break something.

## Review Focus
1. **A post type with no definitions** must not get the REST field or the editor script. Covered in Tasks 4 and 6.
2. **Writes to inverse-side children (`Has_*`).** Writing to a child post the user can't edit must fail with 403 and change nothing. Covered in Task 5.
3. **Invalid IDs.** Nonexistent IDs, or IDs of the wrong post type or taxonomy, fail with 400 before any write, so there are no partial writes. Covered in Task 5.
4. **Clearing.** `null` for a single relationship and `[]` for a multiple one remove every existing link. Covered in Task 5.
5. **Unsupported relations** must not take down discovery or the editor. Examples: a non-`Post` parent, a `Term`→`Post` relation, a method that doesn't return a `Relation`. The definition is skipped with `_doing_it_wrong`. Covered in Task 2.

---

### Task 1: Scaffold the plugin from the template

**Files:** the whole new repo, generated. Modify the plugin header, README and `.phpcs.xml` (WordPress 6.5). Add `docs/superpowers/specs/…-design.md` and this plan to `docs/superpowers/plans/`.

- [ ] **Step 1:** Clone the template:
  ```
  git clone --depth 1 git@github.com:alleyinteractive/create-wordpress-plugin.git ../mantle-editor-relationships
  ```
  Then `rm -rf .git && git init -b main`.
- [ ] **Step 2:** Run `php configure.php` and answer through stdin with `printf`:
  - email `sean@alley.com`, username `srtfisher`, name `Sean Fisher`, vendor `alleyinteractive`
  - plugin name `Mantle Editor Relationships`, slug `mantle-editor-relationships`, namespace `Mantle\Editor_Relationships`, class `Editor_Relationships`
  - description `Edit Mantle model relationships in the block editor.`, main file `mantle-editor-relationships.php`
  - yes to modify files, front-end build, npm install/build, Composer, composer install, PHPStan, standalone, and SQLite
  - keep the built-release workflows, and delete `configure.php` and the `Makefile`

  The order of prompts after the namespace prompt can differ. Read `configure.php` first and match it. Continue through the Alley naming warning.

  Verify with `grep -rn "create_wordpress_plugin\|Create_WordPress_Plugin\|create-wordpress-plugin" --exclude-dir={vendor,node_modules,.git} .`, which should return nothing.
- [ ] **Step 3:**
  - Run `composer require mantle-framework/database:^1.22`.
  - Run `npm install @wordpress/core-data @wordpress/editor @wordpress/api-fetch`. Use exact versions matching the template's existing `@wordpress/*` dist-tag (`npm run packages-update` uses `wp-7.0`).
  - Set WordPress 6.5 in the plugin header "Requires at least", the README and phpcs.
- [ ] **Step 4:** Run `composer test && npm test && npm run build`. Expected: all pass on the empty scaffold.
- [ ] **Step 5:** Copy the spec and the plan into `docs/superpowers/`. Commit: `chore: scaffold from create-wordpress-plugin`.

### Task 2: Attribute and relationship definition

**Files:**
- Create `src/attributes/class-editor-relationship.php`
- Create `src/class-relationship-definition.php`
- Create `tests/Fixtures/` models
- Test: `tests/Feature/RelationshipDefinitionTest.php`

**Interfaces (Produces):**
- `#[\Attribute( \Attribute::TARGET_METHOD )] final class Mantle\Editor_Relationships\Attributes\Editor_Relationship`. Constructor: `public readonly ?string $label = null, public readonly ?int $max = null, public readonly ?array $post_status = null` (the last is `list<string>|null`).
- `final class Mantle\Editor_Relationships\Relationship_Definition` with readonly public properties:
  - `string $model` (`class-string<Post>`)
  - `string $method`
  - `string $label`
  - `string $relation` (`class-string<Relation>`, one of the 4 core types)
  - `bool $multiple`
  - `string $kind` (`'post'|'term'`)
  - `string $subtype` (the target's `get_object_name()`)
  - `string $post_type` (the parent's `get_object_name()`)
  - `?int $max`
  - `?array $post_status`
- `public static function from( string $model, string $method, ?Editor_Relationship $options = null ): self`. It throws `\InvalidArgumentException` on an unsupported relation.
- `public function relation( Post $parent ): Relation`, which returns `$parent->{$this->method}()`.
- `public function to_editor_config(): array{name:string,label:string,kind:string,subtype:string,multiple:bool,max:?int,postStatus:?array}`.

**Fixtures** (`tests/Fixtures/`, namespace `Mantle\Editor_Relationships\Tests\Fixtures`). They use the core `post`, `page`, `post_tag` and `category` types, so nothing needs registering:
- `Article extends Post` with `$object_name = 'post'`. Methods:
  - `#[Editor_Relationship( label: 'Featured Page' )] featured_page(): Belongs_To` → `belongs_to( Page::class )`
  - `#[Editor_Relationship] related_pages(): Belongs_To_Many` → `belongs_to_many( Page::class )`
  - `#[Editor_Relationship( max: 2 )] child_pages(): Has_Many` → `has_many( Page::class )`
  - `#[Editor_Relationship] sponsored_pages(): Has_Many` → `has_many( Page::class )->uses_terms()`
  - `#[Editor_Relationship] lead_page(): Has_One` → `has_one( Page::class, 'lead_article_id' )`
  - `#[Editor_Relationship] tags(): Has_Many` → `has_many( Tag::class )`
  - `not_editable(): Has_Many`, with no attribute
- `Page extends Post` with `$object_name = 'page'`.
- `Tag extends Term` with `$object_name = 'post_tag'`.
- `Category_Model extends Term` with `$object_name = 'category'`. It has a method `#[Editor_Relationship] posts(): Has_Many` → `has_many( Article::class )`, which is an unsupported Term parent.
- `Broken_Article extends Post` with `$object_name = 'post'`. It has a method `#[Editor_Relationship] not_a_relation(): string`, which returns `'nope'`. It's kept separate from `Article` so that discovering `Article` stays clean.

- [ ] **Step 1: Write failing tests** in `RelationshipDefinitionTest`, using a data provider:

  | method | relation | multiple | kind | subtype |
  |---|---|---|---|---|
  | `featured_page` | `Belongs_To` | false | post | page |
  | `related_pages` | `Belongs_To_Many` | true | post | page |
  | `child_pages` | `Has_Many` | true | post | page |
  | `sponsored_pages` | `Has_Many` | true | post | page |
  | `lead_page` | `Has_One` | false | post | page |
  | `tags` | `Has_Many` | true | term | post_tag |

  Also:
  - `test_label_defaults_to_title_case_method`: `related_pages` gives `'Related Pages'`, `featured_page` gives `'Featured Page'`.
  - `test_max_and_post_status_pass_through`: `child_pages->max === 2`.
  - `test_post_type_is_parent_object_name`: `'post'`.
  - `test_term_parent_throws`: `Category_Model::posts`.
  - `test_non_relation_method_throws`: a fixture method returning a string.
  - `test_to_editor_config_shape`: an exact array for `child_pages`.
- [ ] **Step 2:** Run `vendor/bin/phpunit --filter RelationshipDefinitionTest`. Expected: FAIL, class not found.
- [ ] **Step 3: Implement.** Get the relation with `Relation::no_constraints( fn () => ( new $model() )->{$method}() )`. The target class is `$relation->get_query()->get_model()`. Multiple means `Has_Many` or `Belongs_To_Many`. Kind is `term` when the target is a subclass of `Mantle\Database\Model\Term`. Reject:
  - a parent that isn't a subclass of `Mantle\Database\Model\Post`
  - a return value that isn't one of `Has_One`, `Has_Many`, `Belongs_To`, `Belongs_To_Many` (check `Belongs_To_Many` before `Belongs_To`)
  - a target that isn't `Post` or `Term`

  Make the label with `Str::headline()` if it exists in `Mantle\Support\Str`, otherwise `ucwords( str_replace( '_', ' ', $method ) )`.
- [ ] **Step 4:** Run the test again. Expected: PASS. Then run `composer phpstan`.
- [ ] **Step 5:** Commit: `feat: relationship definition and attribute`.

### Task 3: Registry and discovery

**Files:**
- Create `src/class-registry.php`
- Create `src/functions.php` (required from the main plugin file)
- Test: `tests/Feature/RegistryTest.php`

**Interfaces:**
- Consumes `Relationship_Definition::from()` and `Editor_Relationship`.
- Produces `final class Registry`:
  - `public static function instance(): self`
  - `public function register( string $model, string $method, ?Editor_Relationship $options = null ): void`
  - `public function discover( string ...$models ): void`
  - `public function for_post_type( string $post_type ): array`, returning `array<string, Relationship_Definition>` keyed by method
  - `public function has_post_type( string $post_type ): bool`
  - `public function post_types(): list<string>`
  - `public function reset(): void`
- Produces these functions in the `Mantle\Editor_Relationships` namespace:
  - `register_relationship( string $model, string $method, array $args = [] ): void`. `$args` holds `label`, `max` and `post_status`, mapped onto an `Editor_Relationship`.
  - `discover_relationships( string ...$models ): void`

**Behavior:**
- `register()` and `discover()` catch `InvalidArgumentException`, call `_doing_it_wrong( __METHOD__, $message, '1.0.0' )`, and skip that definition.
- `discover()` uses `Mantle\Support\Reflector::get_attributes_for_method( $model, $method, Editor_Relationship::class )` for each public, non-static method declared on the model or its parents, excluding `Mantle\Database\Model\*` base classes. If an attribute exists, it calls `register()` with that attribute instance.
- When a post type already has a definition under the same method name, the later one wins and `_doing_it_wrong` fires.
- **Lazy sources.** On the first `for_post_type`, `has_post_type` or `post_types` call, discover:
  - `apply_filters( 'mantle_editor_relationships_models', [] )`
  - and, if `function_exists( 'app' ) && class_exists( \Mantle\Framework\Manifest\Model_Manifest::class )` and the container has it bound, `app( Model_Manifest::class )->models()` and `config( 'models.register', [] )`. Wrap this in try/catch `Throwable`, because a broken container must not stop the editor.

  `reset()` clears both the definitions and the "sources discovered" flag.

- [ ] **Step 1: Write failing tests:**
  - `test_discover_finds_attributed_methods_only`: discovering `Article` gives the 6 keys, without `not_editable`.
  - `test_register_without_attribute`: `register( Article::class, 'not_editable' )` → `for_post_type( 'post' )` has `not_editable`.
  - `test_register_relationship_helper_maps_args`: label and max carry through.
  - `test_invalid_registration_is_skipped`: `setExpectedIncorrectUsage( Registry::class . '::register' )`, `register( Category_Model::class, 'posts' )`, `post_types() === []`.
  - `test_models_filter_source`: `add_filter( 'mantle_editor_relationships_models', fn () => [ Article::class ] )`, then `reset()`, then `has_post_type( 'post' )` is true.
  - `test_unknown_post_type_empty`.

  `setUp` calls `Registry::instance()->reset()`.
- [ ] **Step 2:** Run the tests. Expected: FAIL.
- [ ] **Step 3:** Implement as described above.
- [ ] **Step 4:** Run the tests. Expected: PASS. Then run `composer phpstan`.
- [ ] **Step 5:** Commit: `feat: relationship registry with attribute discovery`.

### Task 4: Internal taxonomy and the REST field (read)

**Files:**
- Create `src/features/class-register-internal-taxonomy.php`
- Create `src/class-relationship-store.php`
- Create `src/features/class-rest-field.php`
- Modify `src/main.php`
- Test: `tests/Feature/RestFieldReadTest.php`

**Interfaces:**
- `Register_Internal_Taxonomy` hooks `init` at priority 5. If `! taxonomy_exists( Relation::RELATION_TAXONOMY )`, it registers it on `array_keys( get_post_types() )` with `[ 'public' => false, 'rewrite' => false, 'show_in_rest' => false ]`. These args mirror `mantle-framework` `Database/Model_Service_Provider.php:76`.
- `final class Relationship_Store`:
  - `public function read( Relationship_Definition $definition, Post $parent ): int|array|null` returns an int or null for single relationships, and `list<int>` for multiple ones. For multiple relationships it calls `$definition->relation( $parent )->get()` and maps the results to `->id()`. For single ones it uses `->first()`. It never eager-loads, because eager loading with `uses_terms` throws.
  - Task 5 adds `validate()` and `write()`.
- `Rest_Field`:
  - `public const FIELD = 'mantle_relationships'`.
  - On `rest_api_init`, for each post type in `Registry::instance()->post_types()`, it calls `register_rest_field( $type, self::FIELD, [ 'get_callback' => …, 'update_callback' => …, 'schema' => … ] )`.
  - `get_callback` returns `array<string, int|list<int>|null>`, keyed by method, for `$definition->model::find( $post['id'] )`. When the model can't find the post, each value is null or `[]`.
  - Schema: type `object`, `context` `[ 'view', 'edit' ]`, with properties per method. Single relationships are `[ 'integer', 'null' ]`. Multiple ones are an `array` of `integer`, plus `maxItems` when `max` is set. `additionalProperties` is false.
- Register `Register_Internal_Taxonomy` and `Rest_Field` in `src/main.php`.

**Test fixture boot:** in `setUp`, call `Registry::instance()->reset()`, `discover_relationships( Article::class )`, and `do_action( 'rest_api_init' )`, or rely on the testkit dispatching REST, whichever the testkit needs. Seed data through the Mantle relation APIs directly, e.g. `Article::find( $id )->related_pages()->associate( $page )`.

- [ ] **Step 1: Write failing tests:**
  - `test_field_present_on_post`: `acting_as( 'administrator' )`, `get_json( "/wp/v2/posts/{$id}?context=edit" )` → `assertJsonPath( 'mantle_relationships.related_pages', [ $p1, $p2 ] )`.
  - `test_reads_each_relation_type`: for each fixture method, seed with Mantle and assert the REST value: `featured_page` → int, `lead_page` → int, `child_pages`, `sponsored_pages` and `tags` → id lists.
  - `test_field_absent_on_page`: `get_json( "/wp/v2/pages/{$id}?context=edit" )` → `assertJsonMissingPath( 'mantle_relationships' )` (or the testkit's equivalent).
  - `test_internal_taxonomy_registered`: `taxonomy_exists( 'mantle_relationship' )`.
- [ ] **Step 2:** Run the tests. Expected: FAIL.
- [ ] **Step 3:** Implement as described above.
- [ ] **Step 4:** Run the tests. Expected: PASS. Then run `composer phpstan`.
- [ ] **Step 5:** Commit: `feat: expose relationships over the REST API`.

### Task 5: The REST field (write), with validation and authorization

**Files:**
- Modify `src/class-relationship-store.php` and `src/features/class-rest-field.php`
- Test: `tests/Feature/RestFieldWriteTest.php`

**Interfaces:**
- `Relationship_Store::validate( Relationship_Definition $definition, Post $parent, int|array|null $value ): true|\WP_Error`
- `Relationship_Store::write( Relationship_Definition $definition, Post $parent, int|array|null $value ): void`
- `update_callback( mixed $value, \WP_Post $post ): true|\WP_Error`. It ignores unknown keys (the schema already rejects them). It validates **every** provided key first, then writes. Keys that aren't provided are left alone.

**Validation, in this order:**
1. **Shape.** A single relationship needs an int or null. A multiple one needs a list of ints, deduplicated with order kept. Otherwise `WP_Error( 'mantle_relationships_invalid', …, [ 'status' => 400 ] )`.
2. **`max`.** Exceeding it gives the same error code, status 400.
3. **Existence and type.**
   - post kind: `get_post( $id )?->post_type === $definition->subtype`
   - term kind: `get_term( $id, $definition->subtype ) instanceof WP_Term`

   Failures return 400.
4. **Authorization**, all returning `WP_Error( 'mantle_relationships_forbidden', …, [ 'status' => 403 ] )`:
   - For `Has_One` and `Has_Many` post→post (inverse side), every child being added or removed needs `current_user_can( 'edit_post', $child )`.
   - For term kind, the user needs `current_user_can( get_taxonomy( $subtype )->cap->assign_terms )`.
   - Core already checks `edit_post` on the parent.

**Write semantics.** Compare against `read()`. When the value is unchanged, do nothing.

| Relation | Write |
|---|---|
| `Belongs_To` | null → `dissociate()`; otherwise `associate( $id )` |
| `Belongs_To_Many` | `dissociate()`, then `associate( $id )` for each ID in order |
| `Has_Many` | `remove( $removed_models )`, then `save( $id )` for each added ID. Removed models are loaded with `$related::find()` |
| `Has_One` | when it changes: `remove( old model )` if old, then `save( $new )` if not null |

- [ ] **Step 1: Write failing tests** (`acting_as( 'administrator' )` unless noted):
  - **Round trip per type.** `post_json( "/wp/v2/posts/{$id}", [ 'mantle_relationships' => [ $method => $value ] ] )->assertOk()`, then assert the Mantle read matches: `Article::find( $id )->{$method}` IDs.

    | method | sent | then sent |
    |---|---|---|
    | `featured_page` | `$p1` | `null` (expects a null relation) |
    | `related_pages` | `[ $p2, $p1 ]` (order kept) | `[]` |
    | `child_pages` | `[ $p1, $p2 ]` | `[ $p2 ]` (expects `$p1`'s `article_id` meta deleted, verified with `get_post_meta`) |
    | `sponsored_pages` | `[ $p1 ]` | |
    | `lead_page` | `$p1` | `$p2` (expects `$p1` no longer linked) |
    | `tags` | `[ $t1, $t2 ]` | `[ $t1 ]` |

  - `test_unprovided_keys_untouched`
  - `test_wrong_type_id_400_and_no_partial_write`: send `[ 'related_pages' => [ $p1 ], 'featured_page' => $some_post_id ]` (a post, not a page). Expect 400, and `related_pages` stays empty.
  - `test_nonexistent_id_400`
  - `test_max_exceeded_400`: `child_pages` with 3 IDs.
  - `test_child_not_editable_403`: an author edits their own article and adds a `child_pages` page owned by an admin. Expect 403, and the page meta is unchanged.
  - `test_unknown_key_rejected`: 400 from the schema.
- [ ] **Step 2:** Run the tests. Expected: FAIL.
- [ ] **Step 3:** Implement as described above.
- [ ] **Step 4:** Run the tests. Expected: PASS. Then run `composer phpunit && composer phpstan`.
- [ ] **Step 5:** Commit: `feat: write relationships from the REST API`.

### Task 6: Editor asset enqueue and config

**Files:**
- Create `src/features/class-editor-assets.php`
- Modify `src/main.php`
- Test: `tests/Feature/EditorAssetsTest.php`

**Interfaces:**
- Consumes the `src/assets.php` helpers `get_entry_asset_url( 'relationships-panel' )`, `get_asset_dependency_array()` and `get_asset_version()`.
- Produces the script handle `mantle-editor-relationships-panel`, enqueued on `enqueue_block_editor_assets`. It runs only when `get_current_screen()?->post_type` (or `get_post_type()`) has definitions.
- Inline before the script:
  ```
  window.mantleEditorRelationships = { postType, relationships: [ …to_editor_config() ] };
  ```
  Built with `wp_json_encode`. Uses `wp_set_script_translations`.
- Also calls `_doing_it_wrong` once per subtype that isn't `show_in_rest` (post type or taxonomy), because the picker can't search it.

- [ ] **Step 1: Write failing tests:**
  - `test_enqueued_for_post`: set the current screen to the `post` editor (`set_current_screen( 'post' )` with `$GLOBALS['post']` set), `do_action( 'enqueue_block_editor_assets' )` → `wp_script_is( 'mantle-editor-relationships-panel', 'enqueued' )`. The inline data contains `"name":"related_pages"`.
  - `test_not_enqueued_for_page`.
- [ ] **Step 2:** Run the tests. Expected: FAIL.
- [ ] **Step 3:** Implement. The tests can run before a build exists: guard the missing `index.asset.php` with the helpers' defaults.
- [ ] **Step 4:** Run the tests. Expected: PASS.
- [ ] **Step 5:** Commit: `feat: enqueue the relationships editor panel`.

### Task 7: Editor panel (TypeScript)

**Files:**
- Create `entries/relationships-panel/index.tsx`
- Create `entries/relationships-panel/panel.tsx`
- Create `entries/relationships-panel/types.ts`
- Create `entries/relationships-panel/value.ts`
- Test: `entries/relationships-panel/value.test.ts`

**Interfaces:**
- `types.ts`:
  ```
  type RelationshipConfig = { name: string; label: string; kind: 'post'|'term'; subtype: string; multiple: boolean; max: number|null; postStatus: string[]|null }
  type RelationshipValues = Record<string, number | number[] | null>
  declare global { interface Window { mantleEditorRelationships?: { postType: string; relationships: RelationshipConfig[] } } }
  ```
- `value.ts`:
  - `toIds( value: number | number[] | null | undefined ): number[]`
  - `fromIds( ids: number[], config: RelationshipConfig ): number | number[] | null`. For single relationships this is the first ID or null. For multiple ones it deduplicates and caps at `max`.
  - `setRelationship( values: RelationshipValues | undefined, name: string, next: number | number[] | null ): RelationshipValues`. It returns a new object and never mutates.
- `index.tsx`: `registerPlugin( 'mantle-editor-relationships', { render: Panels } )`. `Panels` reads `window.mantleEditorRelationships` and returns null when it's missing or for another post type.
- `panel.tsx`: one `PluginDocumentSettingPanel` (from `@wordpress/editor`) per config, with `name={ \`mantle-relationship-${ name }\` }` and `title={ label }`.
  - `const [ values, setValues ] = useEntityProp( 'postType', postType, 'mantle_relationships' )`.
  - Render the `block-editor-tools` `Selector` with `type={ kind }`, `subTypes={ [ subtype ] }`, `multiple`, `selected`, and `onSelect( items ) → setValues( setRelationship( values, name, fromIds( items.map( i => i.id ), config ) ) )`.
  - `selected` needs `{ id, title }[]`. Resolve titles with `useSelect` → `core.getEntityRecords( kind === 'post' ? 'postType' : 'taxonomy', subtype, { include: ids, per_page: ids.length || 1, context: 'view' } )`. Map `title.rendered` (posts) or `name` (terms), and fall back to `#<id>`.
  - When `max` is reached in multiple mode, show a short `__()` notice under the selector.

- [ ] **Step 1: Write a failing Jest test** `value.test.ts`:
  - `toIds( null ) → []`, `toIds( 5 ) → [5]`
  - `fromIds( [], single ) → null`, `fromIds( [3,4], single ) → 3`
  - `fromIds( [1,1,2,3], { multiple: true, max: 2 } ) → [1,2]`
  - `setRelationship( { a: 1 }, 'b', [2] ) → { a: 1, b: [2] }`, and the input is not mutated
- [ ] **Step 2:** Run `npm run jest`. Expected: FAIL.
- [ ] **Step 3:** Implement `value.ts`, then the panel files.
- [ ] **Step 4:** Run `npm test && npm run build`. Expected: PASS, and `build/relationships-panel/index.js` plus `index.asset.php` exist.
- [ ] **Step 5:** Commit: `feat: Gutenberg relationships panel`.

### Task 8: README and end-to-end verification

**Files:** modify `README.md`. An example model lives only in the scratchpad or a local mu-plugin; it is not committed.

- [ ] **Step 1: README.** Replace the template's boilerplate with:
  - install
  - attribute usage (the `Article` example)
  - `register_relationship()` / `discover_relationships()`
  - the `mantle_editor_relationships_models` filter, and Mantle-app automatic discovery
  - supported relation types, and how each is stored
  - `show_in_rest` required on target types
  - REST field shape
- [ ] **Step 2: End to end.** Bring up `wp-env` with `composer serve`, or activate the plugin in the local broadway site if `wp-env` isn't available. Add a local mu-plugin that discovers an `Article` model with a `Has_Many` page relationship and a tags relationship. With agent-browser: open a new post in the editor, check the two panels render, select items, save, reload, and check the selections persist. Then `wp eval` `Article::find( ID )->child_pages` and confirm the IDs match. Save a screenshot to the scratchpad.
- [ ] **Step 3:** Run `composer test && npm test`. Expected: all green.
- [ ] **Step 4:** Commit: `docs: README`.

---

## Amendments from plan review (override the tasks above)

1. **Minimum WordPress is 6.6, not 6.5.** `PluginDocumentSettingPanel` is only exported from `@wordpress/editor` starting in 6.6. Update the header, README and phpcs.
2. **Spike first: lazy-loaded `Belongs_To_Many` stored in meta.** At the start of Task 2, write a test confirming it. `Belongs_To::add_constraints` reads `get_meta( key )` with single = true. If it only returns one ID:
   - Change the `related_pages` fixture to `belongs_to_many( Page::class, null, 'related_page_ids' )->uses_terms()`.
   - Note in the README that meta-backed `belongs_to_many` isn't supported in v1.
   - Report the framework bug to the user. Don't file it.
3. **Distinct local keys:** `featured_page` → `belongs_to( Page::class, null, 'featured_page_id' )`; `related_pages` uses `'related_page_ids'`. The README notes that two `belongs_to*` relations to the same model need distinct keys.
4. **No ordering guarantee.** `write()` compares sets, so reordering is a no-op. Tests compare sorted IDs. Remove "(order kept)".
5. **Cut `post_status` everywhere** (attribute, definition, config, types, tests). `/wp/v2/search` only searches published content.
6. **Testkit URLs:** `rest_url( "wp/v2/posts/{$id}" )`, and `add_query_arg( 'context', 'edit', … )` for GETs. The assertion is `assertJsonPathMissing`. Don't call `do_action( 'rest_api_init' )` in setUp.
7. **REST validation lives in the schema** (type, `additionalProperties: false`, `maxItems`). The schema rejects bad shape and `max` before anything is saved. Existence, type and auth checks in `validate()` run after the post save but before any relationship write. Reword Review Focus 3 to say this.
8. **`get_callback` returns early** (an empty object) unless `$request['context'] === 'edit'`. It takes `$request` as its 4th argument.
9. **Editor `max`.** `fromIds` doesn't silently cap. When `ids.length >= max`, hide the Selector and show the notice. `selected` is `useMemo`'d on the ID list and titles. The Selector mirrors props into state, and a fresh array every render causes an infinite loop. Pass `key={ ids.join( ',' ) }` as well.
10. **Swap Tasks 6 and 7.** Build the UI first, so the enqueue test runs against a real `index.asset.php`. `Editor_Assets` returns early when the entry URL is empty. Use the `Mantle\Testing\Concerns\Admin_Screen` trait for the current screen.
11. **Task 1 `configure.php` answer order**, with no standalone prompt and no built-assets prompt:
    email → username → name → vendor → plugin name → slug → continue(y) → namespace → continue(y) → class → description → main file → modify(y) → front-end(y) → npm install+build(y) → composer(y) → composer install(y) → phpstan(y) → sqlite(y) → delete self(y).

    Install npm packages with `@wp-7.0` tags: `@wordpress/core-data`, `@wordpress/editor`. No `api-fetch`.
12. **Task 2 nits:** use `Str::headline()` directly. Test the default label with `lead_page` → `'Lead Page'`.
13. **Task 3:** pass `inherit_from_class: false` to `Reflector::get_attributes_for_method`.
14. **Task 4:** a priority-5 `init` attaching only to built-in types matches the framework and is intentional.
15. **Out of scope:** the framework quirk with `Has_One` + `uses_terms` and `append=false`. It's reported to the user and not tested. The README notes that autosaves and previews don't persist relationship edits.
16. **Task 8 now includes the demo the user asked for.** It's a browser-viewable editor showing the panels: wp-env or the local site, with an example `Article` model mu-plugin and seeded content. Screenshots go to the user, and the URL and login are handed over.
