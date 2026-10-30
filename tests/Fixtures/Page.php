<?php
/**
 * Page fixture model.
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Tests\Fixtures;

use Mantle\Database\Model\Post;

/**
 * Page model.
 */
class Page extends Post {
	/**
	 * Object name.
	 *
	 * @var string
	 */
	public static $object_name = 'page';
}
