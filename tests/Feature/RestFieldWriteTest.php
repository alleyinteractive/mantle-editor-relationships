<?php
/**
 * REST field write tests.
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Tests\Feature;

use Mantle\Editor_Relationships\Registry;
use Mantle\Editor_Relationships\Tests\Fixtures\Article;
use Mantle\Editor_Relationships\Tests\TestCase;
use Mantle\Testing\Test_Response;

use function Mantle\Editor_Relationships\discover_relationships;

/**
 * Writing relationships through the REST API.
 */
class RestFieldWriteTest extends TestCase {
	/**
	 * Article under test.
	 *
	 * @var int
	 */
	private int $article;

	/**
	 * Page IDs.
	 *
	 * @var list<int>
	 */
	private array $pages;

	protected function setUp(): void {
		parent::setUp();

		Registry::instance()->reset();
		discover_relationships( Article::class );

		$this->acting_as( 'administrator' );

		$this->article = static::factory()->post->create();
		$this->pages   = static::factory()->page->create_many( 3 );
	}

	protected function tearDown(): void {
		Registry::instance()->reset();

		parent::tearDown();
	}

	public function test_belongs_to(): void {
		[ $p1 ] = $this->pages;

		$this->save( [ 'featured_page' => $p1 ] )->assertOk()->assertJsonPath( 'mantle_relationships.featured_page', $p1 );
		$this->assertSame( $p1, $this->related( 'featured_page' ) );

		$this->save( [ 'featured_page' => null ] )->assertOk();
		$this->assertNull( $this->related( 'featured_page' ) );
	}

	public function test_belongs_to_many(): void {
		[ $p1, $p2 ] = $this->pages;

		$this->save( [ 'related_pages' => [ $p2, $p1 ] ] )->assertOk();
		$this->assertEqualsCanonicalizing( [ $p1, $p2 ], $this->related( 'related_pages' ) );

		$this->save( [ 'related_pages' => [] ] )->assertOk();
		$this->assertSame( [], $this->related( 'related_pages' ) );
	}

	public function test_has_many_meta(): void {
		[ $p1, $p2 ] = $this->pages;

		$this->save( [ 'child_pages' => [ $p1, $p2 ] ] )->assertOk();
		$this->assertEqualsCanonicalizing( [ $p1, $p2 ], $this->related( 'child_pages' ) );

		$this->save( [ 'child_pages' => [ $p2 ] ] )->assertOk();
		$this->assertSame( [ $p2 ], $this->related( 'child_pages' ) );
		$this->assertSame( '', get_post_meta( $p1, 'article_id', true ) );
	}

	public function test_has_many_terms(): void {
		[ $p1, $p2 ] = $this->pages;

		$this->save( [ 'sponsored_pages' => [ $p1, $p2 ] ] )->assertOk();
		$this->save( [ 'sponsored_pages' => [ $p1 ] ] )->assertOk();

		$this->assertSame( [ $p1 ], $this->related( 'sponsored_pages' ) );
	}

	public function test_has_one(): void {
		[ $p1, $p2 ] = $this->pages;

		$this->save( [ 'lead_page' => $p1 ] )->assertOk();
		$this->assertSame( $p1, $this->related( 'lead_page' ) );

		$this->save( [ 'lead_page' => $p2 ] )->assertOk();
		$this->assertSame( $p2, $this->related( 'lead_page' ) );
		$this->assertSame( '', get_post_meta( $p1, 'lead_article_id', true ) );

		$this->save( [ 'lead_page' => null ] )->assertOk();
		$this->assertNull( $this->related( 'lead_page' ) );
	}

	public function test_has_many_post_to_term(): void {
		[ $t1, $t2 ] = static::factory()->tag->create_many( 2 );

		$this->save( [ 'tags' => [ $t1, $t2 ] ] )->assertOk();
		$this->assertEqualsCanonicalizing( [ $t1, $t2 ], $this->related( 'tags' ) );

		$this->save( [ 'tags' => [ $t1 ] ] )->assertOk();
		$this->assertSame( [ $t1 ], $this->related( 'tags' ) );
	}

	public function test_unpublished_related_posts_are_read_and_kept(): void {
		[ $p1 ]    = $this->pages;
		$scheduled = static::factory()->page->create( [
			'post_status' => 'future',
			'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
		] );
		$draft     = static::factory()->page->create( [ 'post_status' => 'draft' ] );

		$article = Article::find( $this->article );
		$article->related_pages()->associate( $scheduled );
		$article->child_pages()->save( $draft );

		$this->get_json( add_query_arg( 'context', 'edit', rest_url( "wp/v2/posts/{$this->article}" ) ) )
			->assertJsonPath( 'mantle_relationships.related_pages', [ $scheduled ] )
			->assertJsonPath( 'mantle_relationships.child_pages', [ $draft ] );

		$this->save( [ 'related_pages' => [ $scheduled, $p1 ] ] )->assertOk();

		$this->assertEqualsCanonicalizing( [ $scheduled, $p1 ], $this->related_any_status( 'related_pages' ) );
	}

	public function test_write_failure_returns_error(): void {
		add_filter( 'pre_insert_term', fn (): \WP_Error => new \WP_Error( 'blocked', 'Blocked.' ) );

		$this->save( [ 'related_pages' => [ $this->pages[0] ] ] )->assertStatus( 500 );
	}

	public function test_unprovided_keys_are_untouched(): void {
		[ $p1, $p2 ] = $this->pages;

		$this->save( [ 'featured_page' => $p1 ] )->assertOk();
		$this->save( [ 'related_pages' => [ $p2 ] ] )->assertOk();

		$this->assertSame( $p1, $this->related( 'featured_page' ) );
	}

	public function test_wrong_type_is_rejected_without_partial_writes(): void {
		[ $p1 ] = $this->pages;

		$this->save(
			[
				'related_pages' => [ $p1 ],
				'featured_page' => static::factory()->post->create(),
			]
		)->assertStatus( 400 );

		$this->assertSame( [], $this->related( 'related_pages' ) );
		$this->assertNull( $this->related( 'featured_page' ) );
	}

	public function test_nonexistent_id_is_rejected(): void {
		$this->save( [ 'child_pages' => [ PHP_INT_MAX ] ] )->assertStatus( 400 );
	}

	public function test_wrong_taxonomy_is_rejected(): void {
		$this->save( [ 'tags' => [ static::factory()->category->create() ] ] )->assertStatus( 400 );
	}

	public function test_max_is_enforced(): void {
		$this->save( [ 'child_pages' => $this->pages ] )->assertStatus( 400 );
		$this->assertSame( [], $this->related( 'child_pages' ) );
	}

	public function test_unknown_key_is_rejected(): void {
		$this->save( [ 'nope' => 1 ] )->assertStatus( 400 );
	}

	public function test_inverse_side_requires_editing_the_child(): void {
		$author = $this->acting_as( 'author' );
		$post   = static::factory()->post->create( [ 'post_author' => $author->ID ] );

		$this->post_json(
			rest_url( "wp/v2/posts/{$post}" ),
			[ 'mantle_relationships' => [ 'child_pages' => [ $this->pages[0] ] ] ],
		)->assertStatus( 403 );

		$this->assertSame( '', get_post_meta( $this->pages[0], 'article_id', true ) );
	}

	/**
	 * Save relationship values to the article.
	 *
	 * @param array<string, mixed> $values Relationship values.
	 */
	private function save( array $values ): Test_Response {
		return $this->post_json(
			rest_url( "wp/v2/posts/{$this->article}" ),
			[ 'mantle_relationships' => $values ],
		);
	}

	/**
	 * Read a multiple relationship back through Mantle, including unpublished posts.
	 *
	 * @param string $method Relationship method.
	 * @return list<int>
	 */
	private function related_any_status( string $method ): array {
		$relation = Article::find( $this->article )->{$method}();
		$relation->get_query()->where( 'post_status', 'any' );

		return $relation->get()->map( fn ( $model ) => $model->id() )->values()->all();
	}

	/**
	 * Read a relationship back through Mantle.
	 *
	 * @param string $method Relationship method.
	 * @return int|list<int>|null
	 */
	private function related( string $method ): int|array|null {
		$article = Article::find( $this->article );
		$result  = $article->{$method}()->get();

		if ( in_array( $method, [ 'featured_page', 'lead_page' ], true ) ) {
			return $result->first()?->id();
		}

		return $result->map( fn ( $model ) => $model->id() )->values()->all();
	}
}
