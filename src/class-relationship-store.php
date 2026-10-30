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
}
