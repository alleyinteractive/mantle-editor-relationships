<?php
/**
 * Editor asset tests.
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships\Tests\Feature;

use Mantle\Editor_Relationships\Features\Editor_Assets;
use Mantle\Editor_Relationships\Registry;
use Mantle\Editor_Relationships\Tests\Fixtures\Article;
use Mantle\Editor_Relationships\Tests\TestCase;
use Mantle\Testing\Concerns\Admin_Screen;

use function Mantle\Editor_Relationships\discover_relationships;

/**
 * Enqueuing the editor panel.
 */
class EditorAssetsTest extends TestCase {
	use Admin_Screen;

	protected function setUp(): void {
		parent::setUp();

		Registry::instance()->reset();
		discover_relationships( Article::class );
		wp_dequeue_script( Editor_Assets::HANDLE );
		wp_deregister_script( Editor_Assets::HANDLE );
		wp_dequeue_style( Editor_Assets::HANDLE );
		wp_deregister_style( Editor_Assets::HANDLE );
	}

	protected function tearDown(): void {
		Registry::instance()->reset();

		parent::tearDown();
	}

	public function test_enqueued_with_config_for_post_type_with_relationships(): void {
		set_current_screen( 'post' );

		do_action( 'enqueue_block_editor_assets' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$this->assertTrue( wp_script_is( Editor_Assets::HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( Editor_Assets::HANDLE, 'enqueued' ) );

		$inline = implode( "\n", (array) wp_scripts()->get_data( Editor_Assets::HANDLE, 'before' ) );

		$this->assertStringContainsString( 'window.mantleEditorRelationships', $inline );
		$this->assertStringContainsString( '"postType":"post"', $inline );
		$this->assertStringContainsString( '"name":"related_pages"', $inline );
	}

	public function test_not_enqueued_for_post_type_without_relationships(): void {
		set_current_screen( 'page' );

		do_action( 'enqueue_block_editor_assets' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$this->assertFalse( wp_script_is( Editor_Assets::HANDLE, 'enqueued' ) );
	}
}
