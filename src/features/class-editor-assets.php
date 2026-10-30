<?php
/**
 * Editor_Assets class file
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Features;

use Alley\WP\Types\Feature;
use Mantle\Editor_Relationships\Registry;
use Mantle\Editor_Relationships\Relationship_Definition;

use function Mantle\Editor_Relationships\get_asset_dependency_array;
use function Mantle\Editor_Relationships\get_asset_version;
use function Mantle\Editor_Relationships\get_entry_asset_url;

/**
 * Loads the relationship panels in the block editor for post types that have them.
 */
final readonly class Editor_Assets implements Feature {
	/**
	 * Script handle.
	 */
	public const string HANDLE = 'mantle-editor-relationships-panel';

	/**
	 * Built entry name.
	 */
	private const string ENTRY = 'relationships-panel';

	/**
	 * Constructor.
	 *
	 * @param Registry $registry Relationship registry.
	 */
	public function __construct( private Registry $registry ) {}

	/**
	 * Boot the feature.
	 */
	public function boot(): void {
		add_action( 'enqueue_block_editor_assets', $this->enqueue( ... ) );
	}

	/**
	 * Enqueue the editor script with the current post type's relationships.
	 */
	public function enqueue(): void {
		$post_type = function_exists( 'get_current_screen' ) ? get_current_screen()?->post_type : null;

		if ( ! $post_type ) {
			return;
		}

		$definitions = $this->registry->for_post_type( $post_type );
		$url         = get_entry_asset_url( self::ENTRY );

		if ( [] === $definitions || '' === $url ) {
			return;
		}

		foreach ( $definitions as $definition ) {
			$this->warn_if_not_searchable( $definition );
		}

		wp_enqueue_script( self::HANDLE, $url, get_asset_dependency_array( self::ENTRY ), get_asset_version( self::ENTRY ), true );
		wp_set_script_translations( self::HANDLE, 'mantle-editor-relationships' );
		wp_add_inline_script(
			self::HANDLE,
			'window.mantleEditorRelationships = ' . wp_json_encode(
				[
					'postType'      => $post_type,
					'relationships' => array_values(
						array_map(
							fn ( Relationship_Definition $definition ): array => $definition->to_editor_config(),
							$definitions,
						),
					),
				],
			) . ';',
			'before',
		);
	}

	/**
	 * Warn when the editor cannot search a relationship's post type or taxonomy.
	 *
	 * @param Relationship_Definition $definition Relationship definition.
	 */
	private function warn_if_not_searchable( Relationship_Definition $definition ): void {
		$object = 'term' === $definition->kind ? get_taxonomy( $definition->subtype ) : get_post_type_object( $definition->subtype );

		if ( $object && $object->show_in_rest ) {
			return;
		}

		_doing_it_wrong(
			__METHOD__,
			esc_html( "{$definition->model}::{$definition->method}() relates to {$definition->subtype}, which must be registered with show_in_rest to be searchable in the editor." ),
			'0.1.0',
		);
	}
}
