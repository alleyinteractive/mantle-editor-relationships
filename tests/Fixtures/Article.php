<?php
/**
 * Article fixture model.
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Tests\Fixtures;

use Mantle\Database\Model\Post;
use Mantle\Database\Model\Relations\Belongs_To;
use Mantle\Database\Model\Relations\Belongs_To_Many;
use Mantle\Database\Model\Relations\Has_Many;
use Mantle\Database\Model\Relations\Has_One;
use Mantle\Editor_Relationships\Attributes\Editor_Relationship;

/**
 * Article model with one relationship of each supported type.
 */
class Article extends Post {
	/**
	 * Object name.
	 *
	 * @var string
	 */
	public static $object_name = 'post';

	#[Editor_Relationship( label: 'Featured Page' )]
	public function featured_page(): Belongs_To {
		return $this->belongs_to( Page::class, null, 'featured_page_id' );
	}

	#[Editor_Relationship]
	public function related_pages(): Belongs_To_Many {
		return $this->belongs_to_many( Page::class, null, 'related_page_ids' )->uses_terms();
	}

	#[Editor_Relationship( max: 2 )]
	public function child_pages(): Has_Many {
		return $this->has_many( Page::class );
	}

	#[Editor_Relationship]
	public function sponsored_pages(): Has_Many {
		return $this->has_many( Page::class, 'sponsor_article_id' )->uses_terms();
	}

	#[Editor_Relationship]
	public function lead_page(): Has_One {
		return $this->has_one( Page::class, 'lead_article_id' );
	}

	#[Editor_Relationship]
	public function tags(): Has_Many {
		return $this->has_many( Tag::class );
	}

	public function not_editable(): Has_Many {
		return $this->has_many( Page::class, 'other_article_id' );
	}
}
