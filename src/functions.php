<?php
/**
 * Public helper functions
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships;

use Mantle\Editor_Relationships\Attributes\Editor_Relationship;

/**
 * Register a model relationship as editable in the block editor.
 *
 * @param string                           $model  Model class.
 * @param string                           $method Relationship method name.
 * @param array{label?: string, max?: int} $args   Editor options.
 */
function register_relationship( string $model, string $method, array $args = [] ): void {
	Registry::instance()->register(
		$model,
		$method,
		new Editor_Relationship( label: $args['label'] ?? null, max: $args['max'] ?? null ),
	);
}

/**
 * Register every relationship on the given models marked with the Editor_Relationship attribute.
 *
 * @param string ...$models Model classes.
 */
function discover_relationships( string ...$models ): void {
	Registry::instance()->discover( ...$models );
}
