<?php
/**
 * Mantle Editor Relationships Tests: Bootstrap
 *
 * @package mantle-editor-relationships
 */

declare(strict_types=1);

/**
 * Visit {@see https://mantle.alley.com/testing/test-framework.html} to learn more.
 */
\Mantle\Testing\manager()
	// Rsync the plugin to plugins/mantle-editor-relationships when testing.
	->maybe_rsync_plugin()
	// Load the main file of the plugin.
	->loaded( fn () => require_once __DIR__ . '/../mantle-editor-relationships.php' )
	->install();
