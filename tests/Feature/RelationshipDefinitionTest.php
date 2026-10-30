<?php
/**
 * Relationship_Definition tests.
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Tests\Feature;

use InvalidArgumentException;
use Mantle\Database\Model\Relations\Belongs_To;
use Mantle\Database\Model\Relations\Belongs_To_Many;
use Mantle\Database\Model\Relations\Has_Many;
use Mantle\Database\Model\Relations\Has_One;
use Mantle\Editor_Relationships\Attributes\Editor_Relationship;
use Mantle\Editor_Relationships\Relationship_Definition;
use Mantle\Editor_Relationships\Tests\Fixtures\Article;
use Mantle\Editor_Relationships\Tests\Fixtures\Broken_Article;
use Mantle\Editor_Relationships\Tests\Fixtures\Category_Model;
use Mantle\Editor_Relationships\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Relationship definition derivation.
 */
class RelationshipDefinitionTest extends TestCase {
	/**
	 * Relationship methods and the definition each should produce.
	 *
	 * @return array<string, array{string, string, bool, string, string}>
	 */
	public static function relationships(): array {
		return [
			'belongs_to'            => [ 'featured_page', Belongs_To::class, false, 'post', 'page' ],
			'belongs_to_many terms' => [ 'related_pages', Belongs_To_Many::class, true, 'post', 'page' ],
			'has_many meta'         => [ 'child_pages', Has_Many::class, true, 'post', 'page' ],
			'has_many terms'        => [ 'sponsored_pages', Has_Many::class, true, 'post', 'page' ],
			'has_one'               => [ 'lead_page', Has_One::class, false, 'post', 'page' ],
			'has_many post to term' => [ 'tags', Has_Many::class, true, 'term', 'post_tag' ],
		];
	}

	#[DataProvider( 'relationships' )]
	public function test_derives_definition( string $method, string $relation, bool $multiple, string $kind, string $subtype ): void {
		$definition = Relationship_Definition::from( Article::class, $method );

		$this->assertSame( Article::class, $definition->model );
		$this->assertSame( $method, $definition->method );
		$this->assertSame( $relation, $definition->relation );
		$this->assertSame( $multiple, $definition->multiple );
		$this->assertSame( $kind, $definition->kind );
		$this->assertSame( $subtype, $definition->subtype );
		$this->assertSame( 'post', $definition->post_type );
	}

	public function test_label_defaults_to_headline_of_method(): void {
		$this->assertSame( 'Lead Page', Relationship_Definition::from( Article::class, 'lead_page' )->label );
	}

	public function test_options_pass_through(): void {
		$definition = Relationship_Definition::from( Article::class, 'child_pages', new Editor_Relationship( label: 'Children', max: 2 ) );

		$this->assertSame( 'Children', $definition->label );
		$this->assertSame( 2, $definition->max );
	}

	public function test_term_parent_throws(): void {
		$this->expectException( InvalidArgumentException::class );

		Relationship_Definition::from( Category_Model::class, 'posts' );
	}

	public function test_non_relation_method_throws(): void {
		$this->expectException( InvalidArgumentException::class );

		Relationship_Definition::from( Broken_Article::class, 'not_a_relation' );
	}

	public function test_meta_belongs_to_many_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageIsOrContains( 'uses_terms()' );

		Relationship_Definition::from( Broken_Article::class, 'meta_pages' );
	}

	public function test_single_relations_using_terms_throw(): void {
		foreach ( [ 'term_featured_page', 'term_lead_page' ] as $method ) {
			try {
				Relationship_Definition::from( Broken_Article::class, $method );
				$this->fail( "{$method} should not be editable." );
			} catch ( InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'uses_terms()', $e->getMessage() );
			}
		}
	}

	public function test_missing_method_throws(): void {
		$this->expectException( InvalidArgumentException::class );

		Relationship_Definition::from( Article::class, 'does_not_exist' );
	}

	public function test_to_editor_config(): void {
		$definition = Relationship_Definition::from( Article::class, 'child_pages', new Editor_Relationship( max: 2 ) );

		$this->assertSame(
			[
				'name'     => 'child_pages',
				'label'    => 'Child Pages',
				'kind'     => 'post',
				'subtype'  => 'page',
				'multiple' => true,
				'max'      => 2,
			],
			$definition->to_editor_config(),
		);
	}
}
