<?php
/**
 * Tag fixture model.
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Tests\Fixtures;

use Mantle\Database\Model\Term;

/**
 * Tag model.
 */
class Tag extends Term {
	/**
	 * Object name.
	 *
	 * @var string
	 */
	public static $object_name = 'post_tag';
}
