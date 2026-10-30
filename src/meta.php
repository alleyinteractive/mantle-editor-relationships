<?php
/**
 * Contains functions for working with meta.
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

namespace Mantle\Editor_Relationships;

use function Mantle\Support\Helpers\register_meta_from_file;

/**
 * Reads the post meta definitions from config and registers them.
 */
function register_post_meta_from_defs(): void {
	register_meta_from_file( MANTLE_EDITOR_RELATIONSHIPS_DIR . '/config/post-meta.json', 'post', false );
}

/**
 * Reads the term meta definitions from config and registers them.
 */
function register_term_meta_from_defs(): void {
	register_meta_from_file( MANTLE_EDITOR_RELATIONSHIPS_DIR . '/config/term-meta.json', 'term', false );
}
