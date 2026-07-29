<?php
/**
 * TQB_Admin
 *
 * Registers the plugin's admin dashboard: a top-level menu with Individual
 * and Business tabs, where James (or anyone with manage_options) can edit
 * line-item fees, toggle items on/off, and edit the Business rate bands —
 * all without touching code. See PROJECT_SPEC.md Section 2 (Architecture).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_Admin {

	const MENU_SLUG = 'tqb-settings';
	const NONCE_ACTION_LINE_ITEMS = 'tqb_save_line_items';
	const NONCE_ACTION_RATE_BANDS = 'tqb_save_rate_bands';
	const NONCE_ACTION_GENERAL = 'tqb_save_general_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_tqb_save_line_items', array( $this, 'handle_save_line_items' ) );
		add_action( 'admin_post_tqb_save_rate_bands', array( $this, 'handle_save_rate_bands' ) );
		add_action( 'admin_post_tqb_save_general_settings', array( $this, 'handle_save_general_settings' ) );
		add_action( 'admin_post_tqb_delete_submission', array( $this, 'handle_delete_submission' ) );
		add_action( 'admin_post_tqb_delete_submissions', array( $this, 'handle_bulk_delete_submissions' ) );
		add_action( 'wp_ajax_tqb_fetch_hubspot_pipelines', array( $this, 'handle_fetch_hubspot_pipelines' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_saved_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'tqb-admin',
			TQB_PLUGIN_URL . 'admin/js/tqb-admin.js',
			array(),
			tqb_asset_version( 'admin/js/tqb-admin.js' ),
			true
		);

		wp_localize_script( 'tqb-admin', 'tqbAdminData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tqb_fetch_pipelines' ),
		) );
	}

	public function register_menu() {
		add_menu_page(
			'Tavola Quote Builder',
			'Quote Builder',
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-calculator',
			30
		);
	}

	public function maybe_show_saved_notice() {
		if ( isset( $_GET['tqb_saved'] ) && '1' === $_GET['tqb_saved'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>Pricing settings saved.</p></div>';
		}
	}

	/**
	 * Renders the page shell (tab nav) and delegates to the active tab's view.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tavola-quote-builder' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'individual'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $active_tab, array( 'individual', 'business', 'general', 'submissions' ), true ) ) {
			$active_tab = 'individual';
		}

		?>
		<div class="wrap tqb-admin-wrap">
			<h1>Tavola Quote Builder</h1>
			<p>Manage pricing for the Individual and Business self-service quote tools. Changes here apply immediately to the live form — no code changes needed.</p>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=individual' ) ); ?>"
					class="nav-tab <?php echo 'individual' === $active_tab ? 'nav-tab-active' : ''; ?>">Individual Pricing</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=business' ) ); ?>"
					class="nav-tab <?php echo 'business' === $active_tab ? 'nav-tab-active' : ''; ?>">Business Pricing</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=submissions' ) ); ?>"
					class="nav-tab <?php echo 'submissions' === $active_tab ? 'nav-tab-active' : ''; ?>">Submissions</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=general' ) ); ?>"
					class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">General Settings</a>
			</h2>

			<div class="tqb-tab-content" style="margin-top: 20px;">
				<?php
				if ( 'individual' === $active_tab ) {
					$this->render_individual_tab();
				} elseif ( 'business' === $active_tab ) {
					$this->render_business_tab();
				} elseif ( 'submissions' === $active_tab ) {
					$this->render_submissions_tab();
				} else {
					$this->render_general_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_individual_tab() {
		$items      = TQB_DB::get_line_items( 'individual', false );
		$quote_type = 'individual';
		$heading    = 'Individual Return — Line Items';
		include TQB_PLUGIN_DIR . 'admin/views/line-items-tab.php';
	}

	private function render_business_tab() {
		$extra_items = TQB_DB::get_line_items( 'business', false );
		$asset_bands_c_s = TQB_DB::get_all_rate_bands( 'asset_band', 'c_s_corp' );
		$asset_bands_partnership = TQB_DB::get_all_rate_bands( 'asset_band', 'partnership' );
		$revenue_addons = TQB_DB::get_all_rate_bands( 'revenue_addon' );
		include TQB_PLUGIN_DIR . 'admin/views/business-tab.php';
	}

	private function render_general_tab() {
		$disclaimer_text  = get_option( 'tqb_disclaimer_text', '' );
		$scheduling_link  = get_option( 'tqb_scheduling_link', '' );
		$notification_email = get_option( 'tqb_team_notification_email', get_option( 'admin_email' ) );
		$hubspot_service_key = get_option( 'tqb_hubspot_service_key', '' );
		$hubspot_pipeline_id = get_option( 'tqb_hubspot_pipeline_id', '' );
		$hubspot_stage_new = get_option( 'tqb_hubspot_stage_new', '' );
		$hubspot_stage_custom = get_option( 'tqb_hubspot_stage_custom', '' );
		$enable_abandoned_emails = get_option( 'tqb_enable_abandoned_emails', '1' );
		$reminder_email_hours = get_option( 'tqb_reminder_email_hours', '24' );
		$followup_email_hours = get_option( 'tqb_followup_email_hours', '72' );
		$final_email_hours = get_option( 'tqb_final_email_hours', '168' );
		$office_address = get_option( 'tqb_office_address', "939 W North Ave, Suite 750,\nChicago, IL 60642" );
		include TQB_PLUGIN_DIR . 'admin/views/general-tab.php';
	}

	private function render_submissions_tab() {
		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';

		// Per page options
		$per_page_options = array( 10, 25, 50, 100 );
		$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 25;
		if ( ! in_array( $per_page, $per_page_options, true ) ) {
			$per_page = 25;
		}

		// Pagination
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset = ( $current_page - 1 ) * $per_page;

		// Filters
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$type_filter = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Sorting
		$allowed_columns = array( 'id', 'contact_name', 'contact_email', 'contact_phone', 'quote_type', 'status', 'calculated_total', 'created_at' );
		$orderby = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_columns, true ) ? $_GET['orderby'] : 'created_at';
		$order = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';

		// Build query
		$where = array( '1=1' );
		$where_args = array();

		if ( ! empty( $status_filter ) ) {
			$where[] = 'status = %s';
			$where_args[] = $status_filter;
		}

		if ( ! empty( $type_filter ) ) {
			$where[] = 'quote_type = %s';
			$where_args[] = $type_filter;
		}

		if ( ! empty( $search ) ) {
			$where[] = '(contact_name LIKE %s OR contact_email LIKE %s OR contact_phone LIKE %s)';
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$where_args[] = $search_like;
			$where_args[] = $search_like;
			$where_args[] = $search_like;
		}

		$where_clause = implode( ' AND ', $where );

		// Sanitize orderby for query
		$orderby = in_array( $orderby, $allowed_columns, true ) ? $orderby : 'created_at';
		$orderby = '`' . sanitize_key( $orderby ) . '`';

		// Get total count
		$total_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",
				$where_args
			)
		);

		// Get submissions
		$submissions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				array_merge( $where_args, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		// Decode answers JSON for each submission
		foreach ( $submissions as &$sub ) {
			if ( ! empty( $sub['answers'] ) ) {
				$decoded = json_decode( $sub['answers'], true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$sub['answers'] = $decoded;
				}
			}
		}

		// Get counts by status
		$counts = array(
			'all' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
			'completed' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed'" ),
			'in_progress' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'in_progress'" ),
			'abandoned' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'abandoned'" ),
		);

		include TQB_PLUGIN_DIR . 'admin/views/submissions-tab.php';
	}

	/**
	 * Handles the "Individual Pricing" and "Business extras" form submits.
	 * Both tabs post to the same handler since they share the same
	 * wp_tqb_line_items table structure — quote_type in the hidden field
	 * tells us which set of items was submitted.
	 */
	public function handle_save_line_items() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tavola-quote-builder' ) );
		}

		check_admin_referer( self::NONCE_ACTION_LINE_ITEMS, 'tqb_nonce' );

		$quote_type = isset( $_POST['quote_type'] ) ? sanitize_key( $_POST['quote_type'] ) : '';
		$items      = isset( $_POST['items'] ) ? (array) $_POST['items'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		foreach ( $items as $item_id => $fields ) {
			$item_id         = absint( $item_id );
			$label          = isset( $fields['label'] ) ? sanitize_text_field( wp_unslash( $fields['label'] ) ) : '';
			$tooltip        = isset( $fields['tooltip'] ) ? sanitize_textarea_field( wp_unslash( $fields['tooltip'] ) ) : '';
			$fee             = isset( $fields['fee'] ) ? (float) $fields['fee'] : 0;
			$pricing_pattern = isset( $fields['pricing_pattern'] ) ? sanitize_key( $fields['pricing_pattern'] ) : 'qty_times_fee';
			$hardcoded_value = ( isset( $fields['hardcoded_value'] ) && '' !== $fields['hardcoded_value'] )
				? (float) $fields['hardcoded_value']
				: null;
			$is_active = isset( $fields['is_active'] ) ? 1 : 0;

			// Threshold fields
			$threshold_qty = ( isset( $fields['threshold_qty'] ) && '' !== $fields['threshold_qty'] )
				? (float) $fields['threshold_qty']
				: null;
			$threshold_trigger = isset( $fields['threshold_trigger'] ) ? sanitize_key( $fields['threshold_trigger'] ) : null;
			if ( empty( $threshold_trigger ) ) {
				$threshold_trigger = null;
			}

			TQB_DB::update_line_item( $item_id, array(
				'label'             => $label,
				'tooltip'           => $tooltip,
				'fee'               => $fee,
				'pricing_pattern'   => $pricing_pattern,
				'hardcoded_value'   => $hardcoded_value,
				'is_active'         => $is_active,
				'threshold_qty'     => $threshold_qty,
				'threshold_trigger'  => $threshold_trigger,
			) );
		}

		$redirect_tab = ( 'business' === $quote_type ) ? 'business' : 'individual';
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $redirect_tab . '&tqb_saved=1' ) );
		exit;
	}

	/**
	 * Handles the Business "Rate Reference" grid save (asset bands + revenue add-ons).
	 */
	public function handle_save_rate_bands() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tavola-quote-builder' ) );
		}

		check_admin_referer( self::NONCE_ACTION_RATE_BANDS, 'tqb_nonce' );

		$bands = isset( $_POST['bands'] ) ? (array) $_POST['bands'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		foreach ( $bands as $band_id => $fields ) {
			$band_id = absint( $band_id );

			// Empty string / "Custom" checkbox means: store as NULL price
			// (the band stays marked is_custom via its seeded value — this
			// MVP admin UI edits prices only, not the is_custom flag itself,
			// to avoid accidentally breaking the Schedule L / custom-quote
			// routing logic from the dashboard).
			$price = ( isset( $fields['price'] ) && '' !== $fields['price'] ) ? (float) $fields['price'] : null;

			TQB_DB::update_rate_band_price( $band_id, $price );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=business&tqb_saved=1' ) );
		exit;
	}

	/**
	 * Handles saving the disclaimer text, scheduling link, and team
	 * notification email — the settings the front-end form and email
	 * handler (Phase 6) will read from.
	 */
	public function handle_save_general_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tavola-quote-builder' ) );
		}

		check_admin_referer( self::NONCE_ACTION_GENERAL, 'tqb_nonce' );

		$disclaimer_text = isset( $_POST['disclaimer_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['disclaimer_text'] ) ) : '';
		$scheduling_link = isset( $_POST['scheduling_link'] ) ? esc_url_raw( wp_unslash( $_POST['scheduling_link'] ) ) : '';
		$notification_email = isset( $_POST['notification_email'] ) ? sanitize_email( wp_unslash( $_POST['notification_email'] ) ) : '';
		$hubspot_service_key = isset( $_POST['hubspot_service_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hubspot_service_key'] ) ) : '';
		$hubspot_pipeline_id = isset( $_POST['hubspot_pipeline_id'] ) ? sanitize_text_field( wp_unslash( $_POST['hubspot_pipeline_id'] ) ) : '';
		$hubspot_stage_new = isset( $_POST['hubspot_stage_new'] ) ? sanitize_text_field( wp_unslash( $_POST['hubspot_stage_new'] ) ) : '';
		$hubspot_stage_custom = isset( $_POST['hubspot_stage_custom'] ) ? sanitize_text_field( wp_unslash( $_POST['hubspot_stage_custom'] ) ) : '';

		// Abandoned quote email settings
		$enable_abandoned_emails = isset( $_POST['enable_abandoned_emails'] ) ? '1' : '0';
		$reminder_email_hours = isset( $_POST['reminder_email_hours'] ) ? absint( $_POST['reminder_email_hours'] ) : 24;
		$followup_email_hours = isset( $_POST['followup_email_hours'] ) ? absint( $_POST['followup_email_hours'] ) : 72;
		$final_email_hours = isset( $_POST['final_email_hours'] ) ? absint( $_POST['final_email_hours'] ) : 168;
		$office_address = isset( $_POST['office_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['office_address'] ) ) : '';

		update_option( 'tqb_disclaimer_text', $disclaimer_text );
		update_option( 'tqb_scheduling_link', $scheduling_link );
		update_option( 'tqb_team_notification_email', $notification_email );
		update_option( 'tqb_hubspot_service_key', $hubspot_service_key );
		update_option( 'tqb_hubspot_pipeline_id', $hubspot_pipeline_id );
		update_option( 'tqb_hubspot_stage_new', $hubspot_stage_new );
		update_option( 'tqb_hubspot_stage_custom', $hubspot_stage_custom );

		// Save abandoned quote email settings
		update_option( 'tqb_enable_abandoned_emails', $enable_abandoned_emails );
		update_option( 'tqb_reminder_email_hours', $reminder_email_hours );
		update_option( 'tqb_followup_email_hours', $followup_email_hours );
		update_option( 'tqb_final_email_hours', $final_email_hours );
		update_option( 'tqb_office_address', $office_address );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=general&tqb_saved=1' ) );
		exit;
	}

	/**
	 * Delete a single submission.
	 */
	public function handle_delete_submission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}

		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( ! $id || ! check_admin_referer( 'tqb_delete_sub_' . $id ) ) {
			wp_die( 'Invalid request.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tqb_submissions';
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=submissions&deleted=1' ) );
		exit;
	}

	/**
	 * Bulk delete submissions.
	 */
	public function handle_bulk_delete_submissions() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['tqb_delete_nonce'] ) ) {
			wp_die( 'Permission denied.' );
		}

		check_admin_referer( 'tqb_delete_submissions', 'tqb_delete_nonce' );

		$ids = isset( $_POST['delete_ids'] ) ? array_map( 'absint', (array) $_POST['delete_ids'] ) : array();

		if ( ! empty( $ids ) && isset( $_POST['bulk_action'] ) && $_POST['bulk_action'] === 'delete' ) {
			global $wpdb;
			$table = $wpdb->prefix . 'tqb_submissions';
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ($placeholders)", $ids ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=submissions&deleted=' . count( $ids ) ) );
		exit;
	}

	/**
	 * AJAX endpoint the General Settings page calls to populate the
	 * Pipeline / Stage dropdowns live from HubSpot, using whichever
	 * Service Key is currently saved (not one from the form — the key
	 * must already be saved for this to work, since it reads via
	 * get_option, not from $_POST).
	 */
	public function handle_fetch_hubspot_pipelines() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		check_ajax_referer( 'tqb_fetch_pipelines', 'nonce' );

		$service_key = get_option( 'tqb_hubspot_service_key', '' );

		if ( empty( $service_key ) ) {
			wp_send_json_error( array( 'message' => 'Save a HubSpot Service Key first, then refresh.' ), 400 );
		}

		$pipelines = TQB_Hubspot::get_pipelines( $service_key );

		if ( is_wp_error( $pipelines ) ) {
			wp_send_json_error( array( 'message' => $pipelines->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'pipelines' => $pipelines ) );
	}
}
