<?php
/**
 * Editor_Relationship class file
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Attributes;

use Attribute;

/**
 * Marks a model relationship method as editable in the block editor.
 */
#[Attribute( Attribute::TARGET_METHOD )]
final readonly class Editor_Relationship {
	/**
	 * Constructor.
	 *
	 * @param string|null $label Panel label, defaults to a headline of the method name.
	 * @param int|null    $max   Maximum number of related items that can be selected.
	 */
	public function __construct(
		public ?string $label = null,
		public ?int $max = null,
	) {}
}
