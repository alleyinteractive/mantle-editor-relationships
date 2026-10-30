<?php
/**
 * Registry tests.
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Tests\Feature;

use Mantle\Editor_Relationships\Registry;
use Mantle\Editor_Relationships\Tests\Fixtures\Article;
use Mantle\Editor_Relationships\Tests\Fixtures\Broken_Article;
use Mantle\Editor_Relationships\Tests\Fixtures\Category_Model;
use Mantle\Editor_Relationships\Tests\TestCase;

use function Mantle\Editor_Relationships\discover_relationships;
use function Mantle\Editor_Relationships\register_relationship;

/**
 * Relationship registry.
 */
class RegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Registry::instance()->reset();
	}

	protected function tearDown(): void {
		Registry::instance()->reset();

		parent::tearDown();
	}

	public function test_discover_finds_attributed_methods_only(): void {
		discover_relationships( Article::class );

		$this->assertSame(
			[ 'featured_page', 'related_pages', 'child_pages', 'sponsored_pages', 'lead_page', 'tags' ],
			array_keys( Registry::instance()->for_post_type( 'post' ) ),
		);
		$this->assertSame( 2, Registry::instance()->for_post_type( 'post' )['child_pages']->max );
	}

	public function test_register_without_attribute(): void {
		Registry::instance()->register( Article::class, 'not_editable' );

		$this->assertArrayHasKey( 'not_editable', Registry::instance()->for_post_type( 'post' ) );
	}

	public function test_register_relationship_helper_maps_args(): void {
		register_relationship( Article::class, 'not_editable', [
			'label' => 'Others',
			'max'   => 3,
		] );

		$definition = Registry::instance()->for_post_type( 'post' )['not_editable'];

		$this->assertSame( 'Others', $definition->label );
		$this->assertSame( 3, $definition->max );
	}

	public function test_invalid_registration_is_skipped(): void {
		$this->setExpectedIncorrectUsage( Registry::class . '::register' );

		Registry::instance()->register( Category_Model::class, 'posts' );

		$this->assertSame( [], Registry::instance()->post_types() );
	}

	public function test_discover_skips_invalid_methods(): void {
		$this->setExpectedIncorrectUsage( Registry::class . '::register' );

		discover_relationships( Broken_Article::class );

		$this->assertSame( [], Registry::instance()->for_post_type( 'post' ) );
	}

	public function test_models_filter_source(): void {
		add_filter( 'mantle_editor_relationships_models', fn (): array => [ Article::class ] );

		$this->assertTrue( Registry::instance()->has_post_type( 'post' ) );
		$this->assertSame( [ 'post' ], Registry::instance()->post_types() );
	}

	public function test_unknown_post_type_is_empty(): void {
		discover_relationships( Article::class );

		$this->assertSame( [], Registry::instance()->for_post_type( 'page' ) );
		$this->assertFalse( Registry::instance()->has_post_type( 'page' ) );
	}
}
