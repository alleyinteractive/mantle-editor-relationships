<?php
/**
 * Relationship_Store class file
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships;

use Mantle\Database\Model\Model;
use Mantle\Database\Model\Post;
use Mantle\Database\Model\Relations\Belongs_To;
use Mantle\Database\Model\Relations\Belongs_To_Many;
use Mantle\Database\Model\Relations\Has_Many;
use Mantle\Database\Model\Relations\Has_One;
use Mantle\Database\Model\Relations\Has_One_Or_Many;
use WP_Error;
use WP_Term;

/**
 * Reads and writes relationship values through Mantle's relation APIs.
 */
final class Relationship_Store {
	/**
	 * Read the IDs related to a parent model.
	 *
	 * @param Relationship_Definition $definition Relationship definition.
	 * @param Post                    $parent     Parent model.
	 * @return int|list<int>|null A list of IDs for multiple relationships, otherwise an ID or null.
	 */
	public function read( Relationship_Definition $definition, Post $parent ): int|array|null {
		$relation = $definition->relation( $parent );

		// Post queries default to published posts, and a write would drop links to anything else.
		if ( 'post' === $definition->kind ) {
			$relation->get_query()->where( 'post_status', 'any' );
		}

		if ( $definition->multiple ) {
			return array_values(
				array_map(
					fn ( Model $model ): int => (int) $model->id(),
					$relation->get()->all(),
				),
			);
		}

		$model = $relation->first();

		return $model instanceof Model ? (int) $model->id() : null;
	}

	/**
	 * Check that a value can be written by the current user.
	 *
	 * @param Relationship_Definition $definition Relationship definition.
	 * @param Post                    $parent     Parent model.
	 * @param mixed                   $value      Incoming value.
	 */
	public function validate( Relationship_Definition $definition, Post $parent, mixed $value ): true|WP_Error {
		$ids = $this->to_ids( $definition, $value );

		if ( null === $ids ) {
			return new WP_Error(
				'mantle_relationships_invalid',
				/* translators: %s: relationship label */
				sprintf( __( '%s must be a post or term ID, or a list of them.', 'mantle-editor-relationships' ), $definition->label ),
				[ 'status' => 400 ],
			);
		}

		if ( null !== $definition->max && count( $ids ) > $definition->max ) {
			return new WP_Error(
				'mantle_relationships_invalid',
				/* translators: 1: relationship label, 2: maximum number of items */
				sprintf( __( '%1$s accepts at most %2$d items.', 'mantle-editor-relationships' ), $definition->label, $definition->max ),
				[ 'status' => 400 ],
			);
		}

		foreach ( $ids as $id ) {
			if ( ! $this->is_valid_target( $definition, $id ) ) {
				return new WP_Error(
					'mantle_relationships_invalid',
					/* translators: 1: object ID, 2: relationship label, 3: post type or taxonomy */
					sprintf( __( '%1$d cannot be related through %2$s: it is not a %3$s.', 'mantle-editor-relationships' ), $id, $definition->label, $definition->subtype ),
					[ 'status' => 400 ],
				);
			}
		}

		if ( ! $this->can_write( $definition, $parent, $ids ) ) {
			return new WP_Error(
				'mantle_relationships_forbidden',
				/* translators: %s: relationship label */
				sprintf( __( 'Sorry, you are not allowed to change %s.', 'mantle-editor-relationships' ), $definition->label ),
				[ 'status' => rest_authorization_required_code() ],
			);
		}

		return true;
	}

	/**
	 * Replace the related IDs for a parent model.
	 *
	 * @param Relationship_Definition $definition Relationship definition.
	 * @param Post                    $parent     Parent model.
	 * @param mixed                   $value      Validated value.
	 */
	public function write( Relationship_Definition $definition, Post $parent, mixed $value ): void {
		$next    = $this->to_ids( $definition, $value ) ?? [];
		$current = $this->current_ids( $definition, $parent );

		if ( $this->same_ids( $current, $next ) ) {
			return;
		}

		$relation = $definition->relation( $parent );

		if ( $relation instanceof Belongs_To_Many ) {
			$relation->dissociate();

			foreach ( $next as $id ) {
				$fresh = $definition->relation( $parent );

				if ( $fresh instanceof Belongs_To_Many ) {
					$fresh->associate( $id );
				}
			}
		} elseif ( $relation instanceof Belongs_To ) {
			[] === $next ? $relation->dissociate() : $relation->associate( $next[0] );
		} elseif ( $relation instanceof Has_Many || $relation instanceof Has_One ) {
			$this->write_has( $definition, $parent, $relation, $current, $next );
		}
	}

	/**
	 * Write a relationship stored on the related side.
	 *
	 * @param Relationship_Definition $definition Relationship definition.
	 * @param Post                    $parent     Parent model.
	 * @param Has_One_Or_Many         $relation   Relation.
	 * @param int[]                   $current    Currently related IDs.
	 * @param int[]                   $next       IDs to relate.
	 *
	 * @phpstan-param list<int> $current
	 * @phpstan-param list<int> $next
	 */
	private function write_has( Relationship_Definition $definition, Post $parent, Has_One_Or_Many $relation, array $current, array $next ): void {
		$removed = array_diff( $current, $next );

		if ( [] !== $removed ) {
			$models = array_values(
				array_filter(
					$relation->get()->all(),
					fn ( Model $model ): bool => in_array( (int) $model->id(), $removed, true ),
				),
			);

			$relation->remove( $models );
		}

		foreach ( array_diff( $next, $current ) as $id ) {
			$fresh = $definition->relation( $parent );

			if ( $fresh instanceof Has_One_Or_Many ) {
				$fresh->save( $id );
			}
		}
	}

	/**
	 * Whether the current user may make a change.
	 *
	 * @param Relationship_Definition $definition Relationship definition.
	 * @param Post                    $parent     Parent model.
	 * @param int[]                   $next       IDs to relate.
	 */
	private function can_write( Relationship_Definition $definition, Post $parent, array $next ): bool {
		if ( 'term' === $definition->kind ) {
			$taxonomy = get_taxonomy( $definition->subtype );

			$capability = $taxonomy ? $taxonomy->cap->assign_terms : null;

			return is_string( $capability ) && current_user_can( $capability );
		}

		if ( ! in_array( $definition->relation, [ Has_One::class, Has_Many::class ], true ) ) {
			return true;
		}

		// Has relationships are stored on the related posts, so each changed post must be editable.
		$current = $this->current_ids( $definition, $parent );
		$changed = array_merge( array_diff( $current, $next ), array_diff( $next, $current ) );

		return array_all( $changed, fn ( int $id ): bool => current_user_can( 'edit_post', $id ) );
	}

	/**
	 * Whether an ID is an object of the relationship's post type or taxonomy.
	 *
	 * @param Relationship_Definition $definition Relationship definition.
	 * @param int                     $id         Object ID.
	 */
	private function is_valid_target( Relationship_Definition $definition, int $id ): bool {
		if ( 'term' === $definition->kind ) {
			return get_term( $id, $definition->subtype ) instanceof WP_Term;
		}

		return get_post_type( $id ) === $definition->subtype;
	}

	/**
	 * Currently related IDs as a list.
	 *
	 * @param Relationship_Definition $definition Relationship definition.
	 * @param Post                    $parent     Parent model.
	 * @return list<int>
	 */
	private function current_ids( Relationship_Definition $definition, Post $parent ): array {
		return $this->to_ids( $definition, $this->read( $definition, $parent ) ) ?? [];
	}

	/**
	 * Normalize a value to a list of unique positive IDs, or null when it has the wrong shape.
	 *
	 * @param Relationship_Definition $definition Relationship definition.
	 * @param mixed                   $value      Value.
	 * @return list<int>|null
	 */
	private function to_ids( Relationship_Definition $definition, mixed $value ): ?array {
		if ( ! $definition->multiple ) {
			if ( null === $value ) {
				return [];
			}

			return is_int( $value ) && $value > 0 ? [ $value ] : null;
		}

		if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
			return null;
		}

		foreach ( $value as $id ) {
			if ( ! is_int( $id ) || $id <= 0 ) {
				return null;
			}
		}

		return array_values( array_unique( $value ) );
	}

	/**
	 * Whether two ID lists hold the same IDs, ignoring order.
	 *
	 * @param int[] $a First list.
	 * @param int[] $b Second list.
	 */
	private function same_ids( array $a, array $b ): bool {
		sort( $a );
		sort( $b );

		return $a === $b;
	}
}
