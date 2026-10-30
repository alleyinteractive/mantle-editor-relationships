<?php
/**
 * REST field read tests.
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Tests\Feature;

use Mantle\Editor_Relationships\Registry;
use Mantle\Editor_Relationships\Tests\Fixtures\Article;
use Mantle\Editor_Relationships\Tests\TestCase;

use function Mantle\Editor_Relationships\discover_relationships;

/**
 * Reading relationships through the REST API.
 */
class RestFieldReadTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Registry::instance()->reset();
		discover_relationships( Article::class );

		$this->acting_as( 'administrator' );
	}

	protected function tearDown(): void {
		Registry::instance()->reset();

		parent::tearDown();
	}

	public function test_internal_taxonomy_registered(): void {
		$this->assertTrue( taxonomy_exists( 'mantle_relationship' ) );
	}

	public function test_reads_each_relation_type(): void {
		$article          = Article::find( static::factory()->post->create() );
		[ $p1, $p2, $p3 ] = static::factory()->page->create_many( 3 );
		$tag              = static::factory()->tag->create();

		$article->featured_page()->associate( $p1 );
		$article->related_pages()->associate( $p1 );
		$article->related_pages()->associate( $p2 );
		$article->child_pages()->save( $p2 );
		$article->child_pages()->save( $p3 );
		$article->sponsored_pages()->save( $p3 );
		$article->lead_page()->save( $p1 );
		$article->tags()->save( $tag );

		$response = $this->get_json( $this->edit_url( $article->id() ) )->assertOk();
		$values   = $response->json( 'mantle_relationships' );

		$this->assertSame( $p1, $values['featured_page'] );
		$this->assertEqualsCanonicalizing( [ $p1, $p2 ], $values['related_pages'] );
		$this->assertEqualsCanonicalizing( [ $p2, $p3 ], $values['child_pages'] );
		$this->assertSame( [ $p3 ], $values['sponsored_pages'] );
		$this->assertSame( $p1, $values['lead_page'] );
		$this->assertSame( [ $tag ], $values['tags'] );
	}

	public function test_empty_relationships(): void {
		$article = static::factory()->post->create();

		$this->get_json( $this->edit_url( $article ) )
			->assertOk()
			->assertJsonPath( 'mantle_relationships.featured_page', null )
			->assertJsonPath( 'mantle_relationships.related_pages', [] )
			->assertJsonPath( 'mantle_relationships.lead_page', null );
	}

	public function test_view_context_omits_values(): void {
		$article = Article::find( static::factory()->post->create() );
		$article->featured_page()->associate( static::factory()->page->create() );

		$this->get_json( rest_url( "wp/v2/posts/{$article->id()}" ) )
			->assertOk()
			->assertJsonPathMissing( 'mantle_relationships.featured_page' );
	}

	public function test_field_absent_on_post_types_without_relationships(): void {
		$page = static::factory()->page->create();

		$this->get_json( $this->edit_url( $page, 'pages' ) )
			->assertOk()
			->assertJsonPathMissing( 'mantle_relationships' );
	}

	/**
	 * Build an edit-context REST URL for a post.
	 *
	 * @param int    $id   Post ID.
	 * @param string $base REST base.
	 */
	private function edit_url( int $id, string $base = 'posts' ): string {
		return add_query_arg( 'context', 'edit', rest_url( "wp/v2/{$base}/{$id}" ) );
	}
}
