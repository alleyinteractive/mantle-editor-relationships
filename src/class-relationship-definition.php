<?php
/**
 * Relationship_Definition class file
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships;

use InvalidArgumentException;
use Mantle\Database\Model\Post;
use Mantle\Database\Model\Relations\Belongs_To;
use Mantle\Database\Model\Relations\Belongs_To_Many;
use Mantle\Database\Model\Relations\Has_Many;
use Mantle\Database\Model\Relations\Has_One;
use Mantle\Database\Model\Relations\Relation;
use Mantle\Database\Model\Term;
use Mantle\Editor_Relationships\Attributes\Editor_Relationship;
use Mantle\Support\Str;
use ReflectionMethod;
use Throwable;

/**
 * An editable relationship on a post model, derived from the relation its method returns.
 */
final readonly class Relationship_Definition {
	/**
	 * Relation classes the package can edit, most specific first.
	 *
	 * @var list<class-string<Relation>>
	 */
	private const array SUPPORTED = [
		Belongs_To_Many::class,
		Belongs_To::class,
		Has_Many::class,
		Has_One::class,
	];

	/**
	 * Constructor.
	 *
	 * @param string   $model     Parent model class.
	 * @param string   $method    Relationship method name.
	 * @param string   $label     Panel label.
	 * @param string   $relation  Relation class.
	 * @param bool     $multiple  Whether many items can be related.
	 * @param string   $kind      Kind of the related object, 'post' or 'term'.
	 * @param string   $subtype   Related post type or taxonomy.
	 * @param string   $post_type Parent post type.
	 * @param int|null $max       Maximum number of related items.
	 *
	 * @phpstan-param class-string<Post> $model
	 * @phpstan-param class-string<Relation> $relation
	 * @phpstan-param 'post'|'term' $kind
	 */
	private function __construct(
		public string $model,
		public string $method,
		public string $label,
		public string $relation,
		public bool $multiple,
		public string $kind,
		public string $subtype,
		public string $post_type,
		public ?int $max,
	) {}

	/**
	 * Build a definition from a model's relationship method.
	 *
	 * @param string                   $model   Parent model class.
	 * @param string                   $method  Relationship method name.
	 * @param Editor_Relationship|null $options Editor options.
	 *
	 * @throws InvalidArgumentException When the relationship cannot be edited.
	 */
	public static function from( string $model, string $method, ?Editor_Relationship $options = null ): self {
		if ( ! is_subclass_of( $model, Post::class ) ) {
			throw new InvalidArgumentException( "{$model} must extend " . Post::class . ' to have editable relationships.' );
		}

		if ( ! method_exists( $model, $method ) || ! new ReflectionMethod( $model, $method )->isPublic() ) {
			throw new InvalidArgumentException( "{$model}::{$method}() must be a public method." );
		}

		try {
			$relation = Relation::no_constraints( fn () => new $model()->{$method}() );
		} catch ( Throwable $e ) {
			throw new InvalidArgumentException( "{$model}::{$method}() could not be resolved: {$e->getMessage()}", 0, $e );
		}

		$relation_class = array_find( self::SUPPORTED, fn ( $supported ): bool => $relation instanceof $supported );

		if ( ! $relation instanceof Relation || null === $relation_class ) {
			throw new InvalidArgumentException( "{$model}::{$method}() must return a has_one, has_many, belongs_to, or belongs_to_many relation." );
		}

		// Meta-backed belongs_to_many only reads its first value when lazy loaded.
		if ( Belongs_To_Many::class === $relation_class && ! self::uses_terms( $relation ) ) {
			throw new InvalidArgumentException( "{$model}::{$method}() must chain ->uses_terms() to be editable." );
		}

		// Single relations save their term without appending, which wipes every other relationship term on the post.
		if ( in_array( $relation_class, [ Belongs_To::class, Has_One::class ], true ) && self::uses_terms( $relation ) ) {
			throw new InvalidArgumentException( "{$model}::{$method}() cannot be edited with ->uses_terms(); store it in meta instead." );
		}

		$related = $relation->get_query()->get_model();

		if ( ! is_string( $related ) || ( ! is_subclass_of( $related, Post::class ) && ! is_subclass_of( $related, Term::class ) ) ) {
			throw new InvalidArgumentException( "{$model}::{$method}() must relate to a post or term model." );
		}

		$subtype   = $related::get_object_name();
		$post_type = $model::get_object_name();

		if ( ! $subtype || ! $post_type ) {
			throw new InvalidArgumentException( "{$model}::{$method}() must relate models with an object name." );
		}

		return new self(
			model: $model,
			method: $method,
			label: $options->label ?? Str::headline( $method ),
			relation: $relation_class,
			multiple: in_array( $relation_class, [ Has_Many::class, Belongs_To_Many::class ], true ),
			kind: is_subclass_of( $related, Term::class ) ? 'term' : 'post',
			subtype: $subtype,
			post_type: $post_type,
			max: $options?->max,
		);
	}

	/**
	 * Retrieve the relation for a parent model.
	 *
	 * @param Post $parent Parent model.
	 *
	 * @throws InvalidArgumentException When the method no longer returns a relation.
	 */
	public function relation( Post $parent ): Relation {
		$relation = $parent->{$this->method}();

		if ( ! $relation instanceof Relation ) {
			throw new InvalidArgumentException( "{$this->model}::{$this->method}() did not return a relation." );
		}

		return $relation;
	}

	/**
	 * Configuration passed to the editor script.
	 *
	 * @return array{name: string, label: string, kind: string, subtype: string, multiple: bool, max: int|null}
	 */
	public function to_editor_config(): array {
		return [
			'name'     => $this->method,
			'label'    => $this->label,
			'kind'     => $this->kind,
			'subtype'  => $this->subtype,
			'multiple' => $this->multiple,
			'max'      => $this->max,
		];
	}

	/**
	 * Whether a relation stores its data in the internal relationship taxonomy.
	 *
	 * @param Relation $relation Relation instance.
	 */
	public static function uses_terms( Relation $relation ): bool {
		return (bool) ( fn (): ?bool => $this->uses_terms )->call( $relation );
	}
}
