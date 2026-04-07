<?php
/**
 * Uninstall handler for SchemaPilot.
 *
 * @package SchemaPilot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'schemapilot_entries';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
