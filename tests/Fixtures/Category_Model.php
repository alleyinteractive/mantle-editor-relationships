<?php
/**
 * Category fixture model.
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Tests\Fixtures;

use Mantle\Database\Model\Relations\Has_Many;
use Mantle\Database\Model\Term;
use Mantle\Editor_Relationships\Attributes\Editor_Relationship;

/**
 * Category model with an unsupported term-parent relationship.
 */
class Category_Model extends Term {
	/**
	 * Object name.
	 *
	 * @var string
	 */
	public static $object_name = 'category';

	#[Editor_Relationship]
	public function posts(): Has_Many {
		return $this->has_many( Article::class );
	}
}
