<?php
/**
 * Rest_Field class file
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Features;

use Alley\WP\Types\Feature;
use Mantle\Editor_Relationships\Registry;
use Mantle\Editor_Relationships\Relationship_Definition;
use Mantle\Editor_Relationships\Relationship_Store;
use WP_Error;
use WP_Post;
use WP_REST_Request;

/**
 * Exposes editable relationships as a REST field on each post type that has them.
 */
final readonly class Rest_Field implements Feature {
	/**
	 * REST field name.
	 */
	public const string FIELD = 'mantle_relationships';

	/**
	 * Constructor.
	 *
	 * @param Registry           $registry Relationship registry.
	 * @param Relationship_Store $store    Relationship store.
	 */
	public function __construct(
		private Registry $registry,
		private Relationship_Store $store,
	) {}

	/**
	 * Boot the feature.
	 */
	public function boot(): void {
		add_action( 'rest_api_init', $this->register( ... ) );
	}

	/**
	 * Register the field on every post type with editable relationships.
	 */
	public function register(): void {
		foreach ( $this->registry->post_types() as $post_type ) {
			register_rest_field(
				$post_type,
				self::FIELD,
				[
					'get_callback'    => fn ( array $post, string $field, WP_REST_Request $request ): array => $this->get_value( $post_type, $post, $request ),
					'update_callback' => $this->update_value( ... ),
					'schema'          => $this->schema( $post_type ),
				],
			);
		}
	}

	/**
	 * Read the relationship values for a post.
	 *
	 * @param string          $post_type Post type.
	 * @param array<mixed>    $post      Prepared post data.
	 * @param WP_REST_Request $request   REST request.
	 * @return array<string, int|list<int>|null>
	 *
	 * @phpstan-param WP_REST_Request<array<mixed>> $request
	 */
	public function get_value( string $post_type, array $post, WP_REST_Request $request ): array {
		if ( 'edit' !== $request['context'] || ! isset( $post['id'] ) || ! is_numeric( $post['id'] ) ) {
			return [];
		}

		$values = [];

		foreach ( $this->registry->for_post_type( $post_type ) as $method => $definition ) {
			$parent = $definition->model::find( (int) $post['id'] );

			$values[ $method ] = $parent ? $this->store->read( $definition, $parent ) : ( $definition->multiple ? [] : null );
		}

		return $values;
	}

	/**
	 * Write relationship values for a post.
	 *
	 * Every provided value is validated before any is written, so a rejected request changes no relationship.
	 *
	 * @param mixed   $value Field value keyed by relationship method.
	 * @param WP_Post $post  Post being updated.
	 */
	public function update_value( mixed $value, WP_Post $post ): true|WP_Error {
		if ( ! is_array( $value ) ) {
			return true;
		}

		$definitions = $this->registry->for_post_type( $post->post_type );
		$pending     = [];

		foreach ( $value as $method => $ids ) {
			$definition = $definitions[ $method ] ?? null;
			$parent     = $definition?->model::find( $post->ID );

			if ( ! $definition || ! $parent ) {
				continue;
			}

			$valid = $this->store->validate( $definition, $parent, $ids );

			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$pending[] = [ $definition, $parent, $ids ];
		}

		foreach ( $pending as [ $definition, $parent, $ids ] ) {
			$this->store->write( $definition, $parent, $ids );
		}

		return true;
	}

	/**
	 * Field schema for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return array<string, mixed>
	 */
	private function schema( string $post_type ): array {
		return [
			'description'          => __( 'Mantle relationships editable in the block editor.', 'mantle-editor-relationships' ),
			'type'                 => 'object',
			'context'              => [ 'edit' ],
			'additionalProperties' => false,
			'properties'           => array_map(
				$this->property_schema( ... ),
				$this->registry->for_post_type( $post_type ),
			),
		];
	}

	/**
	 * Schema for a single relationship value.
	 *
	 * @param Relationship_Definition $definition Relationship definition.
	 * @return array<string, mixed>
	 */
	private function property_schema( Relationship_Definition $definition ): array {
		if ( ! $definition->multiple ) {
			return [
				'description' => $definition->label,
				'type'        => [ 'integer', 'null' ],
			];
		}

		return array_filter(
			[
				'description' => $definition->label,
				'type'        => 'array',
				'items'       => [ 'type' => 'integer' ],
				'uniqueItems' => true,
				'maxItems'    => $definition->max,
			],
			fn ( mixed $value ): bool => null !== $value,
		);
	}
}
