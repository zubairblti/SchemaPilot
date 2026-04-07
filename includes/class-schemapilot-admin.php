<?php
/**
 * Admin UI and actions.
 *
 * @package SchemaPilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin admin pages.
 */
class SchemaPilot_Admin {

	/**
	 * Pending notice for page editor redirects.
	 *
	 * @var array<string, string>|null
	 */
	protected static $page_editor_notice = null;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes_page', array( __CLASS__, 'register_page_meta_box' ) );
		add_action( 'save_post_page', array( __CLASS__, 'handle_page_editor_save' ), 10, 2 );
		add_filter( 'redirect_post_location', array( __CLASS__, 'append_page_editor_notice' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'render_page_editor_notice' ) );
		add_action( 'admin_post_schemapilot_save_schema', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_schemapilot_delete_schema', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_schemapilot_bulk_delete', array( __CLASS__, 'handle_bulk_delete' ) );
	}

	/**
	 * Register plugin admin pages.
	 *
	 * @return void
	 */
	public static function register_admin_menu() {
		$capability = 'manage_options';

		add_menu_page(
			__( 'SchemaPilot', 'schemapilot' ),
			__( 'SchemaPilot', 'schemapilot' ),
			$capability,
			'schemapilot',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-media-code',
			58
		);

		add_submenu_page(
			'schemapilot',
			__( 'Dashboard', 'schemapilot' ),
			__( 'Dashboard', 'schemapilot' ),
			$capability,
			'schemapilot',
			array( __CLASS__, 'render_dashboard_page' )
		);

		add_submenu_page(
			'schemapilot',
			__( 'Schema List', 'schemapilot' ),
			__( 'Schema List', 'schemapilot' ),
			$capability,
			'schemapilot-list',
			array( __CLASS__, 'render_list_page' )
		);

		add_submenu_page(
			null,
			__( 'Add New Schema', 'schemapilot' ),
			__( 'Add New Schema', 'schemapilot' ),
			$capability,
			'schemapilot-add',
			array( __CLASS__, 'render_form_page' )
		);
	}

	/**
	 * Enqueue scoped admin styles.
	 *
	 * @param string $hook_suffix Current page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		$allowed_hooks = array(
			'toplevel_page_schemapilot',
			'schemapilot_page_schemapilot-list',
			'admin_page_schemapilot-add',
		);

		$is_page_editor = false;

		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) && function_exists( 'get_current_screen' ) ) {
			$screen         = get_current_screen();
			$is_page_editor = $screen && 'page' === $screen->post_type;
		}

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) && ! $is_page_editor ) {
			return;
		}

		wp_enqueue_style(
			'schemapilot-admin',
			SCHEMAPILOT_URL . 'assets/css/admin.css',
			array(),
			SCHEMAPILOT_VERSION
		);

		wp_enqueue_script(
			'schemapilot-admin',
			SCHEMAPILOT_URL . 'assets/js/admin.js',
			array(),
			SCHEMAPILOT_VERSION,
			true
		);
	}

	/**
	 * Register the page editor meta box.
	 *
	 * @return void
	 */
	public static function register_page_meta_box() {
		add_meta_box(
			'schemapilot-page-schema',
			__( 'SchemaPilot', 'schemapilot' ),
			array( __CLASS__, 'render_page_meta_box' ),
			'page',
			'normal',
			'default'
		);
	}

	/**
	 * Render the page editor meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_page_meta_box( $post ) {
		$entry     = SchemaPilot_Schema_Manager::get_entry_by_page_id( $post->ID );
		$form_data = self::get_page_editor_form_data( $post->ID, $entry );

		wp_nonce_field( 'schemapilot_page_editor_save', 'schemapilot_page_editor_nonce' );
		?>
		<div class="schemapilot-page-editor-fields">
			<p><?php esc_html_e( 'Add page-specific JSON-LD here without leaving the page editor. This uses the same schema storage as the Schema List screen.', 'schemapilot' ); ?></p>

			<p>
				<label for="schemapilot_page_editor_location"><strong><?php esc_html_e( 'Location', 'schemapilot' ); ?></strong></label>
			</p>
			<select id="schemapilot_page_editor_location" name="schemapilot_location">
				<option value="head" <?php selected( 'head', $form_data['location'] ); ?>><?php esc_html_e( 'Head', 'schemapilot' ); ?></option>
				<option value="footer" <?php selected( 'footer', $form_data['location'] ); ?>><?php esc_html_e( 'Footer', 'schemapilot' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Choose whether the JSON-LD script should render in wp_head or wp_footer.', 'schemapilot' ); ?></p>

			<p>
				<label for="schemapilot_page_editor_schema_json"><strong><?php esc_html_e( 'Schema JSON-LD', 'schemapilot' ); ?></strong></label>
			</p>
			<textarea id="schemapilot_page_editor_schema_json" name="schemapilot_schema_json" class="large-text code" rows="14" placeholder="{\n  &quot;@context&quot;: &quot;https://schema.org&quot;,\n  &quot;@type&quot;: &quot;WebPage&quot;\n}"><?php echo esc_textarea( $form_data['schema_json'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Paste valid JSON-LD. If this page already has a schema, updating the page will update that entry.', 'schemapilot' ); ?></p>

			<?php if ( $entry instanceof stdClass ) : ?>
				<label class="schemapilot-remove-toggle" for="schemapilot_remove_schema">
					<input id="schemapilot_remove_schema" type="checkbox" name="schemapilot_remove_schema" value="1" />
					<?php esc_html_e( 'Remove schema from this page on update', 'schemapilot' ); ?>
				</label>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render dashboard.
	 *
	 * @return void
	 */
	public static function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'schemapilot' ) );
		}
		?>
		<div class="wrap schemapilot-admin">
			<h1><?php esc_html_e( 'SchemaPilot Dashboard', 'schemapilot' ); ?></h1>

			<?php self::render_notices(); ?>

			<div class="schemapilot-grid">
				<div class="schemapilot-card schemapilot-hero-card">
					<span class="schemapilot-eyebrow"><?php esc_html_e( 'Structured Data Management', 'schemapilot' ); ?></span>
					<h2><?php esc_html_e( 'Add clean JSON-LD to the right page and the right location.', 'schemapilot' ); ?></h2>
					<p><?php esc_html_e( 'Schema markup helps search engines understand your content more precisely. SchemaPilot gives you a focused workflow for attaching JSON-LD to published WordPress pages without loading unnecessary frontend assets.', 'schemapilot' ); ?></p>
					<p>
						<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=schemapilot-list' ) ); ?>">
							<?php esc_html_e( 'View Schema List', 'schemapilot' ); ?>
						</a>
					</p>
				</div>

				<div class="schemapilot-card">
					<h3><?php esc_html_e( 'What is Schema?', 'schemapilot' ); ?></h3>
					<p><?php esc_html_e( 'Schema is structured data that describes your page content in a machine-readable format, commonly delivered as JSON-LD.', 'schemapilot' ); ?></p>
				</div>

				<div class="schemapilot-card">
					<h3><?php esc_html_e( 'Benefits of Schema', 'schemapilot' ); ?></h3>
					<ul class="schemapilot-list">
						<li><?php esc_html_e( 'Improves content understanding for search engines', 'schemapilot' ); ?></li>
						<li><?php esc_html_e( 'Can support rich results and stronger SERP visibility', 'schemapilot' ); ?></li>
						<li><?php esc_html_e( 'Keeps SEO markup organized per page', 'schemapilot' ); ?></li>
					</ul>
				</div>

				<div class="schemapilot-card">
					<h3><?php esc_html_e( 'Why it matters for SEO', 'schemapilot' ); ?></h3>
					<p><?php esc_html_e( 'Structured data gives additional context around entities, content types, and relationships, which can improve how your page is interpreted and presented in search.', 'schemapilot' ); ?></p>
				</div>

				<div class="schemapilot-card">
					<h3><?php esc_html_e( 'How to use this plugin', 'schemapilot' ); ?></h3>
					<ol class="schemapilot-steps">
						<li><?php esc_html_e( 'Open the schema list and create a new entry.', 'schemapilot' ); ?></li>
						<li><?php esc_html_e( 'Select a published page from the dropdown.', 'schemapilot' ); ?></li>
						<li><?php esc_html_e( 'Choose whether the JSON-LD should render in the head or footer.', 'schemapilot' ); ?></li>
						<li><?php esc_html_e( 'Paste valid JSON-LD, save, and test the selected page.', 'schemapilot' ); ?></li>
					</ol>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render schema list.
	 *
	 * @return void
	 */
	public static function render_list_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'schemapilot' ) );
		}

		$entries = SchemaPilot_Schema_Manager::get_entries();
		?>
		<div class="wrap schemapilot-admin">
			<div class="schemapilot-page-header">
				<div>
					<h1><?php esc_html_e( 'Schema List', 'schemapilot' ); ?></h1>
					<p><?php esc_html_e( 'Manage page-specific JSON-LD entries and where they render on the frontend.', 'schemapilot' ); ?></p>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=schemapilot-add' ) ); ?>">
					<?php esc_html_e( 'Add New Schema', 'schemapilot' ); ?>
				</a>
			</div>

			<?php self::render_notices(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="schemapilot-bulk-form">
				<input type="hidden" name="action" value="schemapilot_bulk_delete" />
				<?php wp_nonce_field( 'schemapilot_bulk_delete', 'schemapilot_bulk_nonce' ); ?>

				<div class="schemapilot-list-controls">
					<div class="schemapilot-search">
						<label class="screen-reader-text" for="schemapilot-search"><?php esc_html_e( 'Search schemas', 'schemapilot' ); ?></label>
						<input type="search" id="schemapilot-search" placeholder="<?php esc_attr_e( 'Search schemas...', 'schemapilot' ); ?>" />
					</div>
					<div class="schemapilot-controls-right">
						<div class="schemapilot-page-size">
							<label for="schemapilot-page-size"><?php esc_html_e( 'Rows', 'schemapilot' ); ?></label>
							<select id="schemapilot-page-size">
								<option value="10" selected><?php esc_html_e( '10', 'schemapilot' ); ?></option>
								<option value="25"><?php esc_html_e( '25', 'schemapilot' ); ?></option>
								<option value="50"><?php esc_html_e( '50', 'schemapilot' ); ?></option>
								<option value="100"><?php esc_html_e( '100', 'schemapilot' ); ?></option>
							</select>
						</div>
						<button type="submit" class="button button-secondary schemapilot-bulk-delete" disabled>
							<?php esc_html_e( 'Delete Selected', 'schemapilot' ); ?>
						</button>
					</div>
				</div>

				<div class="schemapilot-card schemapilot-table-card">
					<table class="widefat fixed striped schemapilot-table">
						<thead>
							<tr>
								<th class="schemapilot-col-checkbox">
									<input type="checkbox" class="schemapilot-check-all" aria-label="<?php esc_attr_e( 'Select all schemas', 'schemapilot' ); ?>" />
								</th>
								<th><?php esc_html_e( 'Page Title', 'schemapilot' ); ?></th>
								<th><?php esc_html_e( 'Page Slug', 'schemapilot' ); ?></th>
								<th><?php esc_html_e( 'Schema Description', 'schemapilot' ); ?></th>
								<th><?php esc_html_e( 'Location', 'schemapilot' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'schemapilot' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $entries ) ) : ?>
								<tr>
									<td colspan="6">
										<div class="schemapilot-empty">
											<strong><?php esc_html_e( 'No schemas added yet.', 'schemapilot' ); ?></strong>
											<p><?php esc_html_e( 'Create your first schema entry to start outputting JSON-LD on published pages.', 'schemapilot' ); ?></p>
										</div>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $entries as $entry ) : ?>
									<?php
									$page       = get_post( (int) $entry->page_id );
									$page_slug  = $page instanceof WP_Post ? $page->post_name : __( '(page unavailable)', 'schemapilot' );
									$page_title = $page instanceof WP_Post ? get_the_title( $page ) : __( '(page unavailable)', 'schemapilot' );
									$edit_url   = admin_url( 'admin.php?page=schemapilot-add&entry_id=' . absint( $entry->id ) );
									$delete_url = wp_nonce_url(
										admin_url( 'admin-post.php?action=schemapilot_delete_schema&entry_id=' . absint( $entry->id ) ),
										'schemapilot_delete_schema_' . absint( $entry->id )
									);
									?>
									<tr>
										<td class="schemapilot-col-checkbox">
											<input type="checkbox" class="schemapilot-row-checkbox" name="entry_ids[]" value="<?php echo esc_attr( $entry->id ); ?>" />
										</td>
										<td><?php echo esc_html( $page_title ); ?></td>
										<td><code><?php echo esc_html( $page_slug ); ?></code></td>
										<td><?php echo esc_html( $entry->schema_preview ); ?></td>
										<td>
											<span class="schemapilot-badge schemapilot-badge-<?php echo esc_attr( $entry->location ); ?>">
												<?php echo esc_html( ucfirst( $entry->location ) ); ?>
											</span>
										</td>
										<td>
											<a class="button button-secondary" href="<?php echo esc_url( $edit_url ); ?>">
												<?php esc_html_e( 'Edit', 'schemapilot' ); ?>
											</a>
											<a class="button button-link-delete schemapilot-delete" href="<?php echo esc_url( $delete_url ); ?>" data-title="<?php echo esc_attr__( 'Delete schema?', 'schemapilot' ); ?>" data-message="<?php echo esc_attr__( 'This action cannot be undone.', 'schemapilot' ); ?>">
												<?php esc_html_e( 'Delete', 'schemapilot' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<div class="schemapilot-pagination" id="schemapilot-pagination"></div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render add/edit form.
	 *
	 * @return void
	 */
	public static function render_form_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'schemapilot' ) );
		}

		$entry_id = isset( $_GET['entry_id'] ) ? absint( wp_unslash( $_GET['entry_id'] ) ) : 0;
		$entry    = $entry_id ? SchemaPilot_Schema_Manager::get_entry( $entry_id ) : null;
		$is_edit  = $entry instanceof stdClass;

		$form_data = self::get_form_data( $entry );
		$entries   = SchemaPilot_Schema_Manager::get_entries();
		$page_ids  = array();

		foreach ( $entries as $schema_entry ) {
			$page_ids[] = (int) $schema_entry->page_id;
		}

		$page_ids = array_values( array_unique( array_filter( $page_ids ) ) );

		if ( $is_edit && $entry ) {
			$page_ids = array_diff( $page_ids, array( (int) $entry->page_id ) );
		}

		$pages = get_pages(
			array(
				'sort_column' => 'post_title',
				'post_status' => 'publish',
			)
		);

		if ( ! empty( $page_ids ) ) {
			$pages = array_values(
				array_filter(
					$pages,
					function ( $page ) use ( $page_ids ) {
						return ! in_array( (int) $page->ID, $page_ids, true );
					}
				)
			);
		}
		?>
		<div class="wrap schemapilot-admin">
			<div class="schemapilot-page-header">
				<div>
					<h1><?php echo esc_html( $is_edit ? __( 'Edit Schema', 'schemapilot' ) : __( 'Add New Schema', 'schemapilot' ) ); ?></h1>
					<p><?php esc_html_e( 'Attach JSON-LD to a published page and choose where the markup should render.', 'schemapilot' ); ?></p>
				</div>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=schemapilot-list' ) ); ?>">
					<?php esc_html_e( 'Back to Schema List', 'schemapilot' ); ?>
				</a>
			</div>

			<?php self::render_notices(); ?>

			<div class="schemapilot-card schemapilot-form-card">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="schemapilot_save_schema" />
					<input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry_id ); ?>" />
					<?php wp_nonce_field( 'schemapilot_save_schema', 'schemapilot_nonce' ); ?>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="schemapilot_page_id"><?php esc_html_e( 'Page Selector', 'schemapilot' ); ?></label>
								</th>
								<td>
									<select id="schemapilot_page_id" name="page_id" required>
										<option value=""><?php esc_html_e( 'Select a page', 'schemapilot' ); ?></option>
										<?php foreach ( $pages as $page ) : ?>
											<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( (int) $form_data['page_id'], (int) $page->ID ); ?>>
												<?php echo esc_html( $page->post_title ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Only published pages are available for selection.', 'schemapilot' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="schemapilot_location"><?php esc_html_e( 'Location', 'schemapilot' ); ?></label>
								</th>
								<td>
									<select id="schemapilot_location" name="location" required>
										<option value="head" <?php selected( 'head', $form_data['location'] ); ?>><?php esc_html_e( 'Head', 'schemapilot' ); ?></option>
										<option value="footer" <?php selected( 'footer', $form_data['location'] ); ?>><?php esc_html_e( 'Footer', 'schemapilot' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Choose whether the JSON-LD script should print inside wp_head or wp_footer.', 'schemapilot' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="schemapilot_schema_json"><?php esc_html_e( 'Schema JSON-LD', 'schemapilot' ); ?></label>
								</th>
								<td>
									<textarea id="schemapilot_schema_json" name="schema_json" class="large-text code" rows="16" required><?php echo esc_textarea( $form_data['schema_json'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Paste a valid JSON-LD object or array. Invalid JSON will be rejected on save.', 'schemapilot' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>

					<?php submit_button( $is_edit ? __( 'Update Schema', 'schemapilot' ) : __( 'Save Schema', 'schemapilot' ) ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle save action.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'schemapilot' ) );
		}

		check_admin_referer( 'schemapilot_save_schema', 'schemapilot_nonce' );

		$entry_id    = isset( $_POST['entry_id'] ) ? absint( wp_unslash( $_POST['entry_id'] ) ) : 0;
		$page_id     = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0;
		$location    = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : 'head';
		$schema_json = isset( $_POST['schema_json'] ) ? trim( wp_unslash( $_POST['schema_json'] ) ) : '';

		$result = SchemaPilot_Schema_Manager::save_entry( $entry_id, $page_id, $location, $schema_json );

		if ( is_wp_error( $result ) ) {
			self::set_form_state(
				array(
					'page_id'     => $page_id,
					'location'    => SchemaPilot_Schema_Manager::sanitize_location( $location ),
					'schema_json' => $schema_json,
				)
			);

			$redirect_url = add_query_arg(
				array(
					'page'     => 'schemapilot-add',
					'entry_id' => $entry_id,
					'notice'   => 'error',
					'message'  => $result->get_error_message(),
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}

		self::clear_form_state();

		$redirect_url = add_query_arg(
			array(
				'page'    => 'schemapilot-list',
				'notice'  => 'success',
				'message' => $entry_id ? __( 'Schema updated successfully.', 'schemapilot' ) : __( 'Schema added successfully.', 'schemapilot' ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle delete action.
	 *
	 * @return void
	 */
	public static function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'schemapilot' ) );
		}

		$entry_id = isset( $_GET['entry_id'] ) ? absint( wp_unslash( $_GET['entry_id'] ) ) : 0;

		check_admin_referer( 'schemapilot_delete_schema_' . $entry_id );

		$deleted = SchemaPilot_Schema_Manager::delete_entry( $entry_id );

		$redirect_url = add_query_arg(
			array(
				'page'    => 'schemapilot-list',
				'notice'  => $deleted ? 'success' : 'error',
				'message' => $deleted ? __( 'Schema deleted successfully.', 'schemapilot' ) : __( 'Schema could not be deleted.', 'schemapilot' ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle bulk delete action.
	 *
	 * @return void
	 */
	public static function handle_bulk_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'schemapilot' ) );
		}

		check_admin_referer( 'schemapilot_bulk_delete', 'schemapilot_bulk_nonce' );

		$entry_ids = isset( $_POST['entry_ids'] ) ? (array) wp_unslash( $_POST['entry_ids'] ) : array();
		$entry_ids = array_values(
			array_filter(
				array_map( 'absint', $entry_ids )
			)
		);

		if ( empty( $entry_ids ) ) {
			$redirect_url = add_query_arg(
				array(
					'page'    => 'schemapilot-list',
					'notice'  => 'error',
					'message' => __( 'Please select at least one schema entry.', 'schemapilot' ),
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$deleted = 0;
		foreach ( $entry_ids as $entry_id ) {
			if ( SchemaPilot_Schema_Manager::delete_entry( $entry_id ) ) {
				$deleted++;
			}
		}

		$message = $deleted
			? sprintf(
				/* translators: %d: number of deleted schemas. */
				_n( '%d schema deleted successfully.', '%d schemas deleted successfully.', $deleted, 'schemapilot' ),
				$deleted
			)
			: __( 'No schemas were deleted.', 'schemapilot' );

		$redirect_url = add_query_arg(
			array(
				'page'    => 'schemapilot-list',
				'notice'  => $deleted ? 'success' : 'error',
				'message' => $message,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Save schema from the page editor.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function handle_page_editor_save( $post_id, $post ) {
		if ( ! isset( $_POST['schemapilot_page_editor_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['schemapilot_page_editor_nonce'] ) ), 'schemapilot_page_editor_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || 'page' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

		$entry         = SchemaPilot_Schema_Manager::get_entry_by_page_id( $post_id );
		$entry_id      = $entry instanceof stdClass ? (int) $entry->id : 0;
		$location      = isset( $_POST['schemapilot_location'] ) ? sanitize_text_field( wp_unslash( $_POST['schemapilot_location'] ) ) : 'head';
		$schema_json   = isset( $_POST['schemapilot_schema_json'] ) ? trim( wp_unslash( $_POST['schemapilot_schema_json'] ) ) : '';
		$remove_schema = ! empty( $_POST['schemapilot_remove_schema'] );

		if ( $remove_schema && $entry_id ) {
			$deleted = SchemaPilot_Schema_Manager::delete_entry( $entry_id );

			self::clear_page_editor_form_state( $post_id );
			self::set_page_editor_notice(
				$deleted ? 'success' : 'error',
				$deleted ? __( 'Schema removed from this page.', 'schemapilot' ) : __( 'Schema could not be removed from this page.', 'schemapilot' )
			);

			return;
		}

		if ( '' === $schema_json ) {
			self::clear_page_editor_form_state( $post_id );
			return;
		}

		$result = SchemaPilot_Schema_Manager::save_entry( $entry_id, $post_id, $location, $schema_json );

		if ( is_wp_error( $result ) ) {
			self::set_page_editor_form_state(
				$post_id,
				array(
					'location'    => SchemaPilot_Schema_Manager::sanitize_location( $location ),
					'schema_json' => $schema_json,
				)
			);
			self::set_page_editor_notice( 'error', $result->get_error_message() );

			return;
		}

		self::clear_page_editor_form_state( $post_id );
		self::set_page_editor_notice(
			'success',
			$entry_id ? __( 'Schema updated for this page.', 'schemapilot' ) : __( 'Schema added to this page.', 'schemapilot' )
		);
	}

	/**
	 * Append SchemaPilot notice arguments to the page editor redirect.
	 *
	 * @param string $location Redirect URL.
	 * @param int    $post_id  Post ID.
	 * @return string
	 */
	public static function append_page_editor_notice( $location, $post_id ) {
		if ( empty( self::$page_editor_notice ) || ! $post_id ) {
			return $location;
		}

		return add_query_arg(
			array(
				'schemapilot_notice'         => self::$page_editor_notice['type'],
				'schemapilot_notice_message' => self::$page_editor_notice['message'],
			),
			$location
		);
	}

	/**
	 * Render SchemaPilot notice on the page editor.
	 *
	 * @return void
	 */
	public static function render_page_editor_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'page' !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		$notice_type = isset( $_GET['schemapilot_notice'] ) ? sanitize_key( wp_unslash( $_GET['schemapilot_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message     = isset( $_GET['schemapilot_notice_message'] ) ? sanitize_text_field( wp_unslash( $_GET['schemapilot_notice_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $notice_type || ! $message ) {
			return;
		}
		?>
		<div class="schemapilot-toast-data" data-type="<?php echo esc_attr( $notice_type ); ?>" data-message="<?php echo esc_attr( $message ); ?>"></div>
		<?php
	}

	/**
	 * Render admin notices as toast data.
	 *
	 * @return void
	 */
	protected static function render_notices() {
		$notice_type = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message     = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $notice_type || ! $message ) {
			return;
		}
		?>
		<div class="schemapilot-toast-data" data-type="<?php echo esc_attr( $notice_type ); ?>" data-message="<?php echo esc_attr( $message ); ?>"></div>
		<?php
	}

	/**
	 * Build sticky form data from the current entry or transient state.
	 *
	 * @param object|null $entry Existing entry.
	 * @return array<string, mixed>
	 */
	protected static function get_form_data( $entry ) {
		$data = array(
			'page_id'     => $entry ? (int) $entry->page_id : 0,
			'location'    => $entry ? SchemaPilot_Schema_Manager::sanitize_location( $entry->location ) : 'head',
			'schema_json' => $entry ? (string) $entry->schema_json : '',
		);

		$form_state = self::get_form_state();

		if ( is_array( $form_state ) ) {
			$data['page_id']     = isset( $form_state['page_id'] ) ? absint( $form_state['page_id'] ) : $data['page_id'];
			$data['location']    = isset( $form_state['location'] ) ? SchemaPilot_Schema_Manager::sanitize_location( $form_state['location'] ) : $data['location'];
			$data['schema_json'] = isset( $form_state['schema_json'] ) ? (string) $form_state['schema_json'] : $data['schema_json'];
		}

		self::clear_form_state();

		return $data;
	}

	/**
	 * Persist transient form state for the current user.
	 *
	 * @param array<string, mixed> $state Form values.
	 * @return void
	 */
	protected static function set_form_state( $state ) {
		set_transient( self::get_form_state_key(), $state, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Retrieve transient form state for the current user.
	 *
	 * @return array<string, mixed>|false
	 */
	protected static function get_form_state() {
		return get_transient( self::get_form_state_key() );
	}

	/**
	 * Clear transient form state for the current user.
	 *
	 * @return void
	 */
	protected static function clear_form_state() {
		delete_transient( self::get_form_state_key() );
	}

	/**
	 * Get the transient key for the current user.
	 *
	 * @return string
	 */
	protected static function get_form_state_key() {
		return 'schemapilot_form_state_' . get_current_user_id();
	}

	/**
	 * Set a pending page editor notice.
	 *
	 * @param string $type    success|error.
	 * @param string $message Notice message.
	 * @return void
	 */
	protected static function set_page_editor_notice( $type, $message ) {
		self::$page_editor_notice = array(
			'type'    => sanitize_key( $type ),
			'message' => sanitize_text_field( $message ),
		);
	}

	/**
	 * Build page editor form data from entry or transient state.
	 *
	 * @param int         $page_id Page ID.
	 * @param object|null $entry   Existing entry.
	 * @return array<string, string>
	 */
	protected static function get_page_editor_form_data( $page_id, $entry ) {
		$data = array(
			'location'    => $entry ? SchemaPilot_Schema_Manager::sanitize_location( $entry->location ) : 'head',
			'schema_json' => $entry ? (string) $entry->schema_json : '',
		);

		$form_state = self::get_page_editor_form_state( $page_id );

		if ( is_array( $form_state ) ) {
			$data['location']    = isset( $form_state['location'] ) ? SchemaPilot_Schema_Manager::sanitize_location( $form_state['location'] ) : $data['location'];
			$data['schema_json'] = isset( $form_state['schema_json'] ) ? (string) $form_state['schema_json'] : $data['schema_json'];
		}

		self::clear_page_editor_form_state( $page_id );

		return $data;
	}

	/**
	 * Persist page editor form state for the current user and page.
	 *
	 * @param int                  $page_id Page ID.
	 * @param array<string, mixed> $state   Form values.
	 * @return void
	 */
	protected static function set_page_editor_form_state( $page_id, $state ) {
		set_transient( self::get_page_editor_form_state_key( $page_id ), $state, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Retrieve page editor form state.
	 *
	 * @param int $page_id Page ID.
	 * @return array<string, mixed>|false
	 */
	protected static function get_page_editor_form_state( $page_id ) {
		return get_transient( self::get_page_editor_form_state_key( $page_id ) );
	}

	/**
	 * Clear page editor form state.
	 *
	 * @param int $page_id Page ID.
	 * @return void
	 */
	protected static function clear_page_editor_form_state( $page_id ) {
		delete_transient( self::get_page_editor_form_state_key( $page_id ) );
	}

	/**
	 * Get the transient key for page editor state.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	protected static function get_page_editor_form_state_key( $page_id ) {
		return 'schemapilot_page_editor_state_' . get_current_user_id() . '_' . absint( $page_id );
	}
}
