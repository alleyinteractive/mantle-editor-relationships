<?php
/**
 * The main plugin function
 *
 * @package mantle-editor-relationships
 */

namespace Mantle\Editor_Relationships;

use Alley\WP\Features\Group;

/**
 * Instantiate the plugin.
 */
function main(): void {
	$plugin = new Group(
		new Features\Register_Internal_Taxonomy(),
		new Features\Rest_Field( Registry::instance(), new Relationship_Store() ),
		new Features\Editor_Assets( Registry::instance() ),
	);

	$plugin->boot();
}
