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
	const NONCE_ACTION_FILING_STATUS = 'tqb_save_filing_status';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_tqb_save_line_items', array( $this, 'handle_save_line_items' ) );
		add_action( 'admin_post_tqb_save_rate_bands', array( $this, 'handle_save_rate_bands' ) );
		add_action( 'admin_post_tqb_save_general_settings', array( $this, 'handle_save_general_settings' ) );
		add_action( 'admin_post_tqb_save_filing_status', array( $this, 'handle_save_filing_status' ) );
		add_action( 'admin_post_tqb_delete_submission', array( $this, 'handle_delete_submission' ) );
		add_action( 'admin_post_tqb_delete_submissions', array( $this, 'handle_bulk_delete_submissions' ) );
		add_action( 'wp_ajax_tqb_fetch_hubspot_pipelines', array( $this, 'handle_fetch_hubspot_pipelines' ) );
		add_action( 'wp_ajax_tqb_get_submission', array( $this, 'ajax_get_submission' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_saved_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		// Shared design system (cards, tables, badges, buttons, the topbar/nav).
		// This was defined in admin/css/tqb-admin.css but never actually
		// enqueued, so the Individual/Business Pricing and General Settings
		// tabs were rendering with no styling applied at all.
		wp_enqueue_style(
			'tqb-admin',
			TQB_PLUGIN_URL . 'admin/css/tqb-admin.css',
			array(),
			tqb_asset_version( 'admin/css/tqb-admin.css' )
		);

		// Admin JS (HubSpot integration)
		wp_enqueue_script(
			'tqb-admin',
			TQB_PLUGIN_URL . 'admin/js/tqb-admin.js',
			array( 'jquery' ),
			tqb_asset_version( 'admin/js/tqb-admin.js' ),
			true
		);

		wp_localize_script( 'tqb-admin', 'tqbAdminData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tqb_admin_nonce' ),
		) );

		// Submissions page JS (modal functionality)
		wp_enqueue_script(
			'tqb-submissions',
			TQB_PLUGIN_URL . 'admin/js/tqb-submissions.js',
			array( 'jquery' ),
			tqb_asset_version( 'admin/js/tqb-submissions.js' ),
			true
		);

		// The View Detail modal previously had no access to real question
		// labels at all — it faked one from the raw item_key ("rental_property"
		// -> "Rental Property") and had no way to show follow-up answers,
		// dollar values, or the follow-up's own charge, since it never had
		// this data in the first place. Pull ALL items (not just active
		// ones — a submission can reference an item that's since been
		// deactivated) for both quote types, keyed by [quote_type][item_key]
		// since the same item_key (e.g. "multi_state") exists under both
		// individual and business.
		global $wpdb;
		$items_table = $wpdb->prefix . TQB_TABLE_LINE_ITEMS;
		$all_items   = $wpdb->get_results( "SELECT * FROM {$items_table}", ARRAY_A );
		$line_items_by_type = array( 'individual' => array(), 'business' => array() );
		foreach ( (array) $all_items as $item ) {
			$type = $item['quote_type'];
			if ( ! isset( $line_items_by_type[ $type ] ) ) {
				$line_items_by_type[ $type ] = array();
			}
			$line_items_by_type[ $type ][ $item['item_key'] ] = array(
				'label'                    => $item['label'],
				'qty_label'                => $item['qty_label'],
				'pricing_pattern'          => $item['pricing_pattern'],
				'fee'                      => (float) $item['fee'],
				'followup_yesno_label'     => $item['followup_yesno_label'],
				'followup_pricing_pattern' => $item['followup_pricing_pattern'],
				'followup_fee'             => isset( $item['followup_fee'] ) ? (float) $item['followup_fee'] : 0,
			);
		}
		wp_localize_script( 'tqb-submissions', 'tqbSubmissionsData', array(
			'lineItems' => $line_items_by_type,
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

		// Previously a completely silent failure (see the fix in
		// TQB_DB::update_line_item()) — the redirect always fired and
		// claimed success regardless of whether the SQL update actually
		// worked. Now surfaces the count here, with the real per-item SQL
		// error logged via error_log() for whoever's debugging it.
		if ( isset( $_GET['tqb_save_failed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$failed_count = absint( $_GET['tqb_save_failed'] );
			echo '<div class="notice notice-error is-dismissible"><p>'
				. esc_html( sprintf( '%d item(s) failed to save. Check the PHP error log for the specific database error.', $failed_count ) )
				. '</p></div>';
		}
	}

	/**
	 * Best-effort lookup of a published page/post using the shortcode, so
	 * the dashboard header can link straight to the live form. Returns null
	 * if nothing is found — the header simply omits the link in that case.
	 */
	private function find_live_form_url() {
		global $wpdb;
		$post_id = $wpdb->get_var(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_status = 'publish'
			 AND post_content LIKE '%[tavola_quote_builder%'
			 ORDER BY post_type = 'page' DESC, ID ASC
			 LIMIT 1"
		);

		return $post_id ? get_permalink( $post_id ) : null;
	}

	/**
	 * Renders the page shell (branded header + nav) and delegates to the
	 * active tab's view.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tavola-quote-builder' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'submissions'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $active_tab, array( 'individual', 'business', 'general', 'submissions' ), true ) ) {
			$active_tab = 'submissions';
		}

		$nav_items = array(
			'submissions' => array( 'label' => 'Submissions', 'icon' => 'dashicons-chart-bar' ),
			'individual'  => array( 'label' => 'Individual Pricing', 'icon' => 'dashicons-admin-users' ),
			'business'    => array( 'label' => 'Business Pricing', 'icon' => 'dashicons-building' ),
			'general'     => array( 'label' => 'General Settings', 'icon' => 'dashicons-admin-generic' ),
		);

		$live_form_url = $this->find_live_form_url();

		?>
		<div class="wrap tqb-admin-wrap">
			<div class="tqb-shell">
				<header class="tqb-topbar">
					<div class="tqb-topbar__brand">
						<span class="tqb-topbar__mark" aria-hidden="true">
							<span class="dashicons dashicons-calculator"></span>
						</span>
						<div class="tqb-topbar__text">
							<h1 class="tqb-topbar__title">Tavola Quote Builder</h1>
							<p class="tqb-topbar__subtitle">Pricing, submissions, and settings for the self-service quote tool. Changes apply to the live form immediately.</p>
						</div>
					</div>
					<?php if ( $live_form_url ) : ?>
						<div class="tqb-topbar__actions">
							<a href="<?php echo esc_url( $live_form_url ); ?>" target="_blank" rel="noopener noreferrer" class="tqb-btn tqb-btn-secondary">
								<span class="dashicons dashicons-external"></span>
								View Live Form
							</a>
						</div>
					<?php endif; ?>
				</header>

				<nav class="tqb-nav" aria-label="Quote Builder sections">
					<?php foreach ( $nav_items as $tab_key => $tab ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $tab_key ) ); ?>"
							class="tqb-nav__item <?php echo $tab_key === $active_tab ? 'is-active' : ''; ?>">
							<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
							<?php echo esc_html( $tab['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="tqb-tab-content">
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
		$delete_data_on_uninstall = get_option( 'tqb_delete_data_on_uninstall', '0' );
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

		// Get counts by status (including NULL/empty as in_progress to match display logic)
		$counts = array(
			'all' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
			'completed' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed'" ),
			'in_progress' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'in_progress' OR status IS NULL OR status = ''" ),
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
		$failed_item_ids = array();

		foreach ( $items as $item_id => $fields ) {
			$item_id         = absint( $item_id );
			$label          = isset( $fields['label'] ) ? sanitize_text_field( wp_unslash( $fields['label'] ) ) : '';
			$tooltip        = isset( $fields['tooltip'] ) ? sanitize_textarea_field( wp_unslash( $fields['tooltip'] ) ) : '';
			$fee             = isset( $fields['fee'] ) ? (float) $fields['fee'] : 0;
			$pricing_pattern = isset( $fields['pricing_pattern'] ) ? sanitize_key( $fields['pricing_pattern'] ) : 'qty_times_fee';
			$is_active = isset( $fields['is_active'] ) ? 1 : 0;

			// Custom-quote-trigger checkbox (previously DB-only, no admin
			// control — client feedback email, Aug 2026, raised the gap
			// while confirming Issue #17 / audit_support).
			$is_custom_quote_trigger = isset( $fields['is_custom_quote_trigger'] ) ? 1 : 0;

			// New fields (Task 2)
			$reveal_followup = isset( $fields['reveal_followup'] ) ? 1 : 0;
			$sort_order      = isset( $fields['sort_order'] ) ? (int) $fields['sort_order'] : 0;
			$filing_status   = isset( $fields['filing_status'] ) ? sanitize_key( $fields['filing_status'] ) : null;
			if ( empty( $filing_status ) ) {
				$filing_status = null;
			}

			// Custom quantity-field wording + optional follow-up yes/no question
			// (client feedback email, Aug 2026 — Issues #3/#4/#5/#6/#9/#11).
			$qty_label = isset( $fields['qty_label'] ) ? sanitize_text_field( wp_unslash( $fields['qty_label'] ) ) : '';
			if ( '' === $qty_label ) {
				$qty_label = null;
			}
			$followup_yesno_label = isset( $fields['followup_yesno_label'] ) ? sanitize_text_field( wp_unslash( $fields['followup_yesno_label'] ) ) : '';
			if ( '' === $followup_yesno_label ) {
				$followup_yesno_label = null;
			}
			$followup_yesno_triggers_custom = isset( $fields['followup_yesno_triggers_custom'] ) ? 1 : 0;

			// Follow-up's own pricing — independent of the parent item's Fee/
			// Pattern/Threshold. Lets a follow-up (e.g. "different state?")
			// add its own charge, require its own quantity, or trigger custom
			// quote via its own threshold, separate from the parent question.
			$followup_pricing_pattern = isset( $fields['followup_pricing_pattern'] ) ? sanitize_key( $fields['followup_pricing_pattern'] ) : 'flat';
			$followup_fee             = isset( $fields['followup_fee'] ) ? (float) $fields['followup_fee'] : 0;

			$followup_threshold_rules = null;
			$followup_threshold_mode  = isset( $fields['followup_threshold_mode'] ) ? sanitize_key( $fields['followup_threshold_mode'] ) : 'none';
			if ( 'custom' === $followup_threshold_mode ) {
				$followup_threshold_type     = isset( $fields['followup_threshold_type'] ) ? sanitize_key( $fields['followup_threshold_type'] ) : 'qty';
				$followup_threshold_operator = isset( $fields['followup_threshold_operator'] ) ? sanitize_key( $fields['followup_threshold_operator'] ) : 'above';
				$followup_threshold_value    = isset( $fields['followup_threshold_value'] ) && '' !== $fields['followup_threshold_value'] ? (float) $fields['followup_threshold_value'] : null;

				if ( null !== $followup_threshold_value ) {
					$followup_threshold_rules = wp_json_encode( array(
						'logic'      => 'AND',
						'conditions' => array(
							array(
								'type'     => $followup_threshold_type,
								'operator' => $followup_threshold_operator,
								'value'    => $followup_threshold_value,
							),
						),
					) );
				}
			}

			// Parse threshold rules (inline single-condition format)
			$threshold_rules = null;
			$threshold_mode  = isset( $fields['threshold_mode'] ) ? sanitize_key( $fields['threshold_mode'] ) : 'none';

			if ( 'custom' === $threshold_mode ) {
				$threshold_type     = isset( $fields['threshold_type'] ) ? sanitize_key( $fields['threshold_type'] ) : 'qty';
				$threshold_operator = isset( $fields['threshold_operator'] ) ? sanitize_key( $fields['threshold_operator'] ) : 'above';
				$threshold_value    = isset( $fields['threshold_value'] ) && '' !== $fields['threshold_value'] ? (float) $fields['threshold_value'] : null;

				// Only create threshold rule if value is provided
				if ( null !== $threshold_value ) {
					$threshold_rules = wp_json_encode( array(
						'logic'      => 'AND',
						'conditions' => array(
							array(
								'type'     => $threshold_type,
								'operator' => $threshold_operator,
								'value'    => $threshold_value,
							),
						),
					) );
				}
			}

			// threshold_qty/threshold_trigger were the old pre-JSON threshold
			// format — replaced by threshold_rules and dropped from the schema
			// entirely by cleanup_deprecated_schema(). This handler kept
			// writing to them anyway (a stale leftover from before that
			// cleanup), which silently broke every single save on any site
			// where the drop migration had already run: $wpdb->update() fails
			// its entire query if even one column in the SET clause doesn't
			// exist, so this alone was enough to make every field on every
			// item fail to save, not just these two.

			$update_data = array(
				'label'                          => $label,
				'tooltip'                        => $tooltip,
				'fee'                            => $fee,
				'pricing_pattern'                => $pricing_pattern,
				'is_active'                      => $is_active,
				'is_custom_quote_trigger'        => $is_custom_quote_trigger,
				'reveal_followup'                => $reveal_followup,
				'sort_order'                     => $sort_order,
				'filing_status'                  => $filing_status,
				'qty_label'                      => $qty_label,
				'followup_yesno_label'           => $followup_yesno_label,
				'followup_yesno_triggers_custom' => $followup_yesno_triggers_custom,
				'followup_pricing_pattern'       => $followup_pricing_pattern,
				'followup_fee'                   => $followup_fee,
				'followup_threshold_rules'       => $followup_threshold_rules,
			);

			// The crypto item's threshold is a compound OR rule (transaction
			// count OR dollar value) that the single-condition inline editor
			// above can't represent — it only ever posts one condition, always
			// type "qty". Including threshold_rules here on every save would
			// silently flatten crypto's rule back down to qty-only. Until the
			// editor supports multiple/OR'd conditions, leave crypto's
			// threshold_rules column untouched; it's managed directly via the
			// seed data / DB instead.
			$item_key = TQB_DB::get_item_key( $item_id );
			if ( 'crypto' !== $item_key ) {
				$update_data['threshold_rules'] = $threshold_rules;
			}

			$save_ok = TQB_DB::update_line_item( $item_id, $update_data );
			if ( ! $save_ok ) {
				$failed_item_ids[] = $item_id;
			}
		}

		$redirect_tab = ( 'business' === $quote_type ) ? 'business' : 'individual';
		$redirect_url = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $redirect_tab . '&tqb_saved=1' );

		// Surface save failures in the UI instead of the previous silent
		// failure, where the redirect always fired regardless of whether
		// anything actually persisted — check the PHP error log for the
		// specific SQL error per item (logged in TQB_DB::update_line_item()).
		if ( ! empty( $failed_item_ids ) ) {
			$redirect_url = add_query_arg( array(
				'tqb_save_failed' => count( $failed_item_ids ),
			), $redirect_url );
		}

		wp_safe_redirect( $redirect_url );
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
		$delete_data_on_uninstall = isset( $_POST['delete_data_on_uninstall'] ) ? '1' : '0';

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
		update_option( 'tqb_delete_data_on_uninstall', $delete_data_on_uninstall );

		// Redirect back to the current tab (default to general if not set)
		$current_tab = isset( $_POST['tqb_current_tab'] ) ? sanitize_key( $_POST['tqb_current_tab'] ) : 'general';
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $current_tab . '&tqb_saved=1' ) );
		exit;
	}

	/**
	 * Handles the "Filing Status Configuration" panel on the Individual tab
	 * (base price + per-status label/surcharge). Split out from
	 * handle_save_general_settings() because the two forms don't share the
	 * same fields — reusing one handler for both meant saving one form
	 * silently reset the other's values to their fallback defaults.
	 */
	public function handle_save_filing_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tavola-quote-builder' ) );
		}

		check_admin_referer( self::NONCE_ACTION_FILING_STATUS, 'tqb_nonce' );

		$base_price = isset( $_POST['tqb_individual_base_price'] ) ? floatval( $_POST['tqb_individual_base_price'] ) : 500;
		update_option( 'tqb_individual_base_price', $base_price );

		$filing_statuses = array( 'single', 'mfj', 'mfs', 'hoh' );

		foreach ( $filing_statuses as $status ) {
			$label = isset( $_POST[ 'tqb_filing_status_label_' . $status ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'tqb_filing_status_label_' . $status ] ) ) : '';
			$price = isset( $_POST[ 'tqb_filing_status_price_' . $status ] ) ? floatval( $_POST[ 'tqb_filing_status_price_' . $status ] ) : 0;

			if ( $label ) {
				update_option( 'tqb_filing_status_label_' . $status, $label );
			}
			update_option( 'tqb_filing_status_price_' . $status, $price );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=individual&tqb_saved=1' ) );
		exit;
	}

	/**
	 * AJAX: Get submission details for modal view.
	 */
	public function ajax_get_submission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}
		check_ajax_referer( 'tqb_admin_nonce', 'nonce' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( empty( $id ) ) {
			wp_send_json_error( 'No ID provided.' );
		}

		// Use TQB_DB::get_submission() rather than a raw query — it JSON-decodes
		// the 'answers' column into a real array/object. Sending the raw string
		// here would cause wp_send_json_success() to encode it a second time,
		// leaving the client with a double-encoded JSON string.
		$submission = TQB_DB::get_submission( $id );

		if ( ! $submission ) {
			wp_send_json_error( 'Submission not found.' );
		}

		wp_send_json_success( $submission );
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

		check_ajax_referer( 'tqb_admin_nonce', 'nonce' );

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
