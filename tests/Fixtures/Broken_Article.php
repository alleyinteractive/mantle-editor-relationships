<?php
/**
 * Broken article fixture model.
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Tests\Fixtures;

use Mantle\Database\Model\Post;
use Mantle\Database\Model\Relations\Belongs_To_Many;
use Mantle\Editor_Relationships\Attributes\Editor_Relationship;

/**
 * Article model with relationships the package cannot edit.
 */
class Broken_Article extends Post {
	/**
	 * Object name.
	 *
	 * @var string
	 */
	public static $object_name = 'post';

	#[Editor_Relationship]
	public function not_a_relation(): string {
		return 'nope';
	}

	#[Editor_Relationship]
	public function meta_pages(): Belongs_To_Many {
		return $this->belongs_to_many( Page::class, null, 'meta_page_ids' );
	}
}
