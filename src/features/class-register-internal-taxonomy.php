<?php
/**
 * Register_Internal_Taxonomy class file
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Features;

use Alley\WP\Types\Feature;
use Mantle\Database\Model\Relations\Relation;

/**
 * Registers Mantle's internal relationship taxonomy when no Mantle application has.
 */
final class Register_Internal_Taxonomy implements Feature {
	/**
	 * Boot the feature.
	 */
	public function boot(): void {
		add_action( 'init', $this->register( ... ), 5 );
	}

	/**
	 * Register the taxonomy with the same arguments as Mantle's model service provider.
	 */
	public function register(): void {
		if ( taxonomy_exists( Relation::RELATION_TAXONOMY ) ) {
			return;
		}

		register_taxonomy(
			Relation::RELATION_TAXONOMY,
			array_keys( get_post_types() ),
			[
				'public'       => false,
				'rewrite'      => false,
				'show_in_rest' => false,
			],
		);
	}
}
