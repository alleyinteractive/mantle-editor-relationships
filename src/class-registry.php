<?php
/**
 * Registry class file
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships;

use InvalidArgumentException;
use Mantle\Editor_Relationships\Attributes\Editor_Relationship;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Holds the relationships that are editable in the block editor, keyed by post type.
 */
final class Registry {
	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Definitions keyed by post type, then relationship method.
	 *
	 * @var array<string, array<string, Relationship_Definition>>
	 */
	private array $definitions = [];

	/**
	 * Whether the filter and application model sources have been discovered.
	 *
	 * @var bool
	 */
	private bool $sources_discovered = false;

	/**
	 * Retrieve the shared instance.
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Register a model relationship as editable.
	 *
	 * @param string                   $model   Model class.
	 * @param string                   $method  Relationship method name.
	 * @param Editor_Relationship|null $options Editor options.
	 */
	public function register( string $model, string $method, ?Editor_Relationship $options = null ): void {
		try {
			$definition = Relationship_Definition::from( $model, $method, $options );
		} catch ( InvalidArgumentException $e ) {
			_doing_it_wrong( __METHOD__, esc_html( $e->getMessage() ), '0.1.0' );

			return;
		}

		if ( isset( $this->definitions[ $definition->post_type ][ $method ] ) && $this->definitions[ $definition->post_type ][ $method ]->model !== $model ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html( "The {$method} relationship on the {$definition->post_type} post type was already registered by another model and has been replaced by {$model}." ),
				'0.1.0',
			);
		}

		$this->definitions[ $definition->post_type ][ $method ] = $definition;
	}

	/**
	 * Register every relationship method marked with the Editor_Relationship attribute.
	 *
	 * @param string ...$models Model classes.
	 */
	public function discover( string ...$models ): void {
		foreach ( $models as $model ) {
			if ( ! class_exists( $model ) ) {
				_doing_it_wrong( __METHOD__, esc_html( "{$model} does not exist." ), '0.1.0' );

				continue;
			}

			foreach ( new ReflectionClass( $model )->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
				if ( $method->isStatic() ) {
					continue;
				}

				foreach ( $method->getAttributes( Editor_Relationship::class ) as $attribute ) {
					$this->register( $model, $method->getName(), $attribute->newInstance() );
				}
			}
		}
	}

	/**
	 * Relationships editable on a post type, keyed by method name.
	 *
	 * @param string $post_type Post type.
	 * @return array<string, Relationship_Definition>
	 */
	public function for_post_type( string $post_type ): array {
		$this->discover_sources();

		return $this->definitions[ $post_type ] ?? [];
	}

	/**
	 * Whether a post type has any editable relationships.
	 *
	 * @param string $post_type Post type.
	 */
	public function has_post_type( string $post_type ): bool {
		return [] !== $this->for_post_type( $post_type );
	}

	/**
	 * Post types with editable relationships.
	 *
	 * @return list<string>
	 */
	public function post_types(): array {
		$this->discover_sources();

		return array_keys( array_filter( $this->definitions ) );
	}

	/**
	 * Clear every registered relationship.
	 */
	public function reset(): void {
		$this->definitions        = [];
		$this->sources_discovered = false;
	}

	/**
	 * Discover models from the filter and, in a Mantle application, the model manifest.
	 */
	private function discover_sources(): void {
		if ( $this->sources_discovered ) {
			return;
		}

		$this->sources_discovered = true;

		$models = [
			...$this->application_models(),
			...(array) apply_filters( 'mantle_editor_relationships_models', [] ),
		];

		$this->discover( ...array_unique( array_filter( $models, is_string( ... ) ) ) );
	}

	/**
	 * Models registered with a Mantle application, if one is running.
	 *
	 * @return array<mixed>
	 */
	private function application_models(): array {
		$manifest = 'Mantle\Framework\Manifest\Model_Manifest';

		if ( ! function_exists( 'app' ) || ! function_exists( 'config' ) || ! class_exists( $manifest ) ) {
			return [];
		}

		try {
			$app = app();

			if ( ! is_object( $app ) || ! method_exists( $app, 'bound' ) || ! $app->bound( $manifest ) ) {
				return [];
			}

			$manifest_models = app( $manifest );

			return [
				...( is_object( $manifest_models ) && method_exists( $manifest_models, 'models' ) ? (array) $manifest_models->models() : [] ),
				...(array) config( 'models.register', [] ),
			];
		} catch ( Throwable ) {
			return [];
		}
	}
}
