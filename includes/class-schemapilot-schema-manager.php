<?php
/**
 * Schema storage and frontend output manager.
 *
 * @package SchemaPilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles schema persistence and rendering.
 */
class SchemaPilot_Schema_Manager {

	/**
	 * Cached page schemas keyed by page ID and location.
	 *
	 * @var array<int, array<string, array<int, object>>>
	 */
	protected static $page_schema_cache = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'render_head_schema' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'render_footer_schema' ), 20 );
	}

	/**
	 * Get the custom table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'schemapilot_entries';
	}

	/**
	 * Create or update the plugin table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			page_id bigint(20) unsigned NOT NULL,
			location varchar(10) NOT NULL DEFAULT 'head',
			schema_json longtext NOT NULL,
			schema_preview text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY page_location (page_id, location)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Get all schema entries.
	 *
	 * @return array<int, object>
	 */
	public static function get_entries() {
		global $wpdb;

		$table_name = self::get_table_name();
		$results    = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY updated_at DESC, id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get a single schema entry.
	 *
	 * @param int $entry_id Entry ID.
	 * @return object|null
	 */
	public static function get_entry( $entry_id ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$entry_id   = absint( $entry_id );

		if ( ! $entry_id ) {
			return null;
		}

		$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $entry_id );

		return $wpdb->get_row( $query );
	}

	/**
	 * Get a single schema entry by page ID.
	 *
	 * @param int $page_id Page ID.
	 * @return object|null
	 */
	public static function get_entry_by_page_id( $page_id ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$page_id    = absint( $page_id );

		if ( ! $page_id ) {
			return null;
		}

		$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE page_id = %d LIMIT 1", $page_id );

		return $wpdb->get_row( $query );
	}

	/**
	 * Insert or update an entry.
	 *
	 * @param int    $entry_id    Optional entry ID.
	 * @param int    $page_id     Page ID.
	 * @param string $location    head|footer.
	 * @param string $schema_json Valid JSON string.
	 * @return int|WP_Error
	 */
	public static function save_entry( $entry_id, $page_id, $location, $schema_json ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$entry_id   = absint( $entry_id );
		$page_id    = absint( $page_id );
		$location   = self::sanitize_location( $location );
		$schema_json = self::normalize_input( $schema_json );
		$decoded    = json_decode( $schema_json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) || empty( $decoded ) ) {
			return new WP_Error( 'invalid_json', __( 'Schema must be valid JSON-LD.', 'schemapilot' ) );
		}

		if ( ! self::is_supported_content( $page_id ) ) {
			return new WP_Error( 'invalid_page', __( 'Please select a valid published page or post.', 'schemapilot' ) );
		}

		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE page_id = %d LIMIT 1",
				$page_id
			)
		);

		if ( $existing_id && $existing_id !== $entry_id ) {
			return new WP_Error( 'duplicate_page', __( 'Schema already exists for this page.', 'schemapilot' ) );
		}

		$normalized_json = wp_json_encode(
			$decoded,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);

		if ( false === $normalized_json ) {
			return new WP_Error( 'json_encode_failed', __( 'The schema could not be normalized for storage.', 'schemapilot' ) );
		}

		$data = array(
			'page_id'        => $page_id,
			'location'       => $location,
			'schema_json'    => $normalized_json,
			'schema_preview' => self::build_preview( $decoded ),
			'updated_at'     => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s' );

		if ( $entry_id ) {
			$result = $wpdb->update(
				$table_name,
				$data,
				array( 'id' => $entry_id ),
				$formats,
				array( '%d' )
			);

			if ( false === $result ) {
				return new WP_Error( 'db_update_failed', __( 'The schema entry could not be updated.', 'schemapilot' ) );
			}

			self::$page_schema_cache = array();

			return $entry_id;
		}

		$data['created_at'] = current_time( 'mysql' );
		$formats[]          = '%s';

		$result = $wpdb->insert( $table_name, $data, $formats );

		if ( false === $result ) {
			return new WP_Error( 'db_insert_failed', __( 'The schema entry could not be created.', 'schemapilot' ) );
		}

		self::$page_schema_cache = array();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete an entry.
	 *
	 * @param int $entry_id Entry ID.
	 * @return bool
	 */
	public static function delete_entry( $entry_id ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$entry_id   = absint( $entry_id );

		if ( ! $entry_id ) {
			return false;
		}

		$result = $wpdb->delete( $table_name, array( 'id' => $entry_id ), array( '%d' ) );

		self::$page_schema_cache = array();

		return false !== $result;
	}

	/**
	 * Render schema in wp_head.
	 *
	 * @return void
	 */
	public static function render_head_schema() {
		self::render_schema_by_location( 'head' );
	}

	/**
	 * Render schema in wp_footer.
	 *
	 * @return void
	 */
	public static function render_footer_schema() {
		self::render_schema_by_location( 'footer' );
	}

	/**
	 * Render schemas for the active page and location.
	 *
	 * @param string $location head|footer.
	 * @return void
	 */
	protected static function render_schema_by_location( $location ) {
		if ( is_admin() || ! is_singular( array( 'page', 'post' ) ) ) {
			return;
		}

		$page_id = get_queried_object_id();

		if ( ! $page_id ) {
			return;
		}

		$entries = self::get_entries_for_page_and_location( $page_id, $location );

		if ( empty( $entries ) ) {
			return;
		}

		foreach ( $entries as $entry ) {
			$decoded = json_decode( $entry->schema_json );

			if ( JSON_ERROR_NONE !== json_last_error() || ( ! is_object( $decoded ) && ! is_array( $decoded ) ) ) {
				continue;
			}

			$encoded = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

			if ( false === $encoded ) {
				continue;
			}

			echo "\n" . '<script type="application/ld+json" class="schemapilot-schema">' . $encoded . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Fetch schemas for a given page and location.
	 *
	 * @param int    $page_id  Page ID.
	 * @param string $location head|footer.
	 * @return array<int, object>
	 */
	protected static function get_entries_for_page_and_location( $page_id, $location ) {
		global $wpdb;

		$page_id  = absint( $page_id );
		$location = self::sanitize_location( $location );

		if ( isset( self::$page_schema_cache[ $page_id ][ $location ] ) ) {
			return self::$page_schema_cache[ $page_id ][ $location ];
		}

		$table_name = self::get_table_name();
		$query      = $wpdb->prepare(
			"SELECT id, schema_json FROM {$table_name} WHERE page_id = %d AND location = %s ORDER BY id ASC",
			$page_id,
			$location
		);
		$results    = $wpdb->get_results( $query );

		if ( ! isset( self::$page_schema_cache[ $page_id ] ) ) {
			self::$page_schema_cache[ $page_id ] = array();
		}

		self::$page_schema_cache[ $page_id ][ $location ] = is_array( $results ) ? $results : array();

		return self::$page_schema_cache[ $page_id ][ $location ];
	}

	/**
	 * Build a short description from decoded schema data.
	 *
	 * @param array<string, mixed> $decoded_json Decoded schema.
	 * @return string
	 */
	protected static function build_preview( $decoded_json ) {
		$schema_root = self::extract_preview_root( $decoded_json );
		$parts       = array();

		if ( isset( $schema_root['@type'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: schema type. */
				__( 'Type: %s', 'schemapilot' ),
				sanitize_text_field( (string) $schema_root['@type'] )
			);
		}

		if ( isset( $schema_root['name'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: schema name. */
				__( 'Name: %s', 'schemapilot' ),
				sanitize_text_field( (string) $schema_root['name'] )
			);
		}

		if ( empty( $parts ) ) {
			$raw_json = wp_json_encode( $decoded_json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$preview  = is_string( $raw_json ) ? wp_strip_all_tags( $raw_json ) : '';

			return wp_html_excerpt( $preview, 120, '&hellip;' );
		}

		return wp_html_excerpt( implode( ' | ', $parts ), 120, '&hellip;' );
	}

	/**
	 * Extract a useful root item for preview text.
	 *
	 * @param array<string, mixed> $decoded_json Decoded schema.
	 * @return array<string, mixed>
	 */
	protected static function extract_preview_root( $decoded_json ) {
		if ( self::is_assoc_array( $decoded_json ) ) {
			return $decoded_json;
		}

		$first_item = reset( $decoded_json );

		if ( is_array( $first_item ) ) {
			return $first_item;
		}

		return array();
	}

	/**
	 * Check whether an array is associative.
	 *
	 * @param array<mixed> $value Array value.
	 * @return bool
	 */
	protected static function is_assoc_array( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		return array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}

	/**
	 * Normalize schema location value.
	 *
	 * @param string $location Raw location.
	 * @return string
	 */
	public static function sanitize_location( $location ) {
		return 'footer' === strtolower( (string) $location ) ? 'footer' : 'head';
	}

	/**
	 * Normalize input to raw JSON by stripping script tags if provided.
	 *
	 * @param string $schema_json Raw schema input.
	 * @return string
	 */
	protected static function normalize_input( $schema_json ) {
		$schema_json = trim( $schema_json );

		if ( '' === $schema_json ) {
			return $schema_json;
		}

		$has_script = preg_match( '/<script[^>]*>/i', $schema_json );
		if ( ! $has_script ) {
			return $schema_json;
		}

		$matches = array();
		preg_match_all( '/<script[^>]*type=(["\']?)application\/ld\+json\\1[^>]*>(.*?)<\/script>/is', $schema_json, $matches );

		if ( empty( $matches[2] ) ) {
			return $schema_json;
		}

		$json_blocks = array();
		foreach ( $matches[2] as $block ) {
			$block = trim( $block );
			if ( '' !== $block ) {
				$json_blocks[] = $block;
			}
		}

		if ( empty( $json_blocks ) ) {
			return $schema_json;
		}

		if ( 1 === count( $json_blocks ) ) {
			return $json_blocks[0];
		}

		return '[' . implode( ',', $json_blocks ) . ']';
	}

	/**
	 * Check whether the selected content is a supported published page or post.
	 *
	 * @param int $page_id Post ID.
	 * @return bool
	 */
	protected static function is_supported_content( $page_id ) {
		$post_type = get_post_type( $page_id );

		return in_array( $post_type, array( 'page', 'post' ), true ) && 'publish' === get_post_status( $page_id );
	}
}
