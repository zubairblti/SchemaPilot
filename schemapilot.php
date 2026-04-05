<?php
/**
 * Plugin Name: SchemaPilot
 * Plugin URI:  https://github.com/zubairblti/SchemaPilot
 * Description: Add and manage page-specific JSON-LD schema markup with a modern WordPress admin interface.
 * Version:     1.0.0
 * Author:      Zubair Blti
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: schemapilot
 *
 * @package SchemaPilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCHEMAPILOT_VERSION', '1.0.0' );
define( 'SCHEMAPILOT_FILE', __FILE__ );
define( 'SCHEMAPILOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCHEMAPILOT_URL', plugin_dir_url( __FILE__ ) );

require_once SCHEMAPILOT_PATH . 'includes/class-schemapilot-schema-manager.php';
require_once SCHEMAPILOT_PATH . 'includes/class-schemapilot-admin.php';

/**
 * Run plugin activation tasks.
 *
 * @return void
 */
function schemapilot_activate() {
	SchemaPilot_Schema_Manager::create_table();
}

register_activation_hook( __FILE__, 'schemapilot_activate' );

/**
 * Bootstrap plugin services.
 *
 * @return void
 */
function schemapilot_init() {
	SchemaPilot_Schema_Manager::init();
	SchemaPilot_Admin::init();
}

add_action( 'plugins_loaded', 'schemapilot_init' );
