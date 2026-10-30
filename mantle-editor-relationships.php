<?php
/**
 * Plugin Name: Mantle Editor Relationships
 * Plugin URI: https://github.com/alleyinteractive/mantle-editor-relationships
 * Description: Edit Mantle model relationships in the block editor.
 * Version: 0.0.0
 * Author: Sean Fisher
 * Author URI: https://github.com/alleyinteractive/mantle-editor-relationships
 * Requires at least: 6.6
 * Requires PHP: 8.4
 * Tested up to: 7.1
 *
 * Text Domain: mantle-editor-relationships
 * Domain Path: /languages/
 *
 * @package mantle-editor-relationships
 */

namespace Mantle\Editor_Relationships;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Root directory to this plugin.
 */
define( 'MANTLE_EDITOR_RELATIONSHIPS_DIR', __DIR__ );

// Check if Composer is installed (remove if Composer is not required for your plugin).
if ( ! file_exists( __DIR__ . '/vendor/wordpress-autoload.php' ) ) {
	// Will also check for the presence of an already loaded Composer autoloader
	// to see if the Composer dependencies have been installed in a parent
	// folder. This is useful for when the plugin is loaded as a Composer
	// dependency in a larger project.
	if ( ! class_exists( \Composer\InstalledVersions::class ) ) {
		\add_action(
			'admin_notices',
			function () {
				?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Composer is not installed and mantle-editor-relationships cannot load. Try using a `*-built` branch if the plugin is being loaded as a submodule.', 'mantle-editor-relationships' ); ?></p>
				</div>
				<?php
			}
		);

		return;
	}
} else {
	// Load Composer dependencies.
	require_once __DIR__ . '/vendor/wordpress-autoload.php';
}

// Load the plugin's main files.
require_once __DIR__ . '/src/assets.php';
require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/main.php';

main();
