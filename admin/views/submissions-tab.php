<?php
/**
 * Submissions list view - Professional Dashboard Design.
 * Shows all quote submissions with stats, filtering, and pagination.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_pages = ceil( $total_count / $per_page );

// Determine display status
function tqb_get_display_status( $status ) {
	if ( $status === 'completed' ) {
		return array( 'label' => 'Completed', 'class' => 'completed' );
	} elseif ( $status === 'in_progress' || empty( $status ) ) {
		return array( 'label' => 'In Progress', 'class' => 'in_progress' );
	} elseif ( $status === 'abandoned' ) {
		return array( 'label' => 'Abandoned', 'class' => 'abandoned' );
	} else {
		return array( 'label' => 'Unknown', 'class' => 'unknown' );
	}
}

// Build base URL for filters
function tqb_build_filter_url( $params = array() ) {
	$base_url = admin_url( 'admin.php?page=tqb-settings&tab=submissions' );
	$current_params = array(
		'status' => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
		'type' => isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '',
		's' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		'orderby' => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '',
		'order' => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : '',
		'per_page' => isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 25,
	);
	
	$merged = array_merge( $current_params, $params );
	$query_args = array();
	
	foreach ( $merged as $key => $value ) {
		if ( ! empty( $value ) && ! ( $key === 'per_page' && $value == 25 ) ) {
			$query_args[ $key ] = $value;
		}
	}
	
	return add_query_arg( $query_args, $base_url );
}

// Get sort indicator
function tqb_get_sort_indicator( $column ) {
	$current_orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '';
	$current_order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';
	
	if ( $current_orderby !== $column ) {
		return '';
	}
	
	return $current_order === 'ASC' ? ' <span class="dashicons dashicons-arrow-up-alt" style="font-size:12px;"></span>' : ' <span class="dashicons dashicons-arrow-down-alt2" style="font-size:12px;"></span>';
}
?>

<style>
/* ============================================
   TQB Dashboard Styles - Professional Design
   ============================================ */

/* Page Header */
.tqb-page-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 24px;
	padding-bottom: 20px;
	border-bottom: 1px solid #e2e8f0;
}

.tqb-page-header h1 {
	margin: 0;
	font-size: 24px;
	font-weight: 600;
	color: #1e293b;
	display: flex;
	align-items: center;
	gap: 10px;
}

.tqb-page-header h1 .dashicons {
	font-size: 28px;
	width: 32px;
	height: 32px;
	color: #001a44;
}

/* Stats Cards */
.tqb-stats-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 20px;
	margin-bottom: 24px;
}

.tqb-stat-card {
	background: #fff;
	border-radius: 12px;
	padding: 20px 24px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
	border: 1px solid #e2e8f0;
	transition: transform 0.2s, box-shadow 0.2s;
}

.tqb-stat-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.tqb-stat-card .stat-label {
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #64748b;
	margin-bottom: 8px;
}

.tqb-stat-card .stat-value {
	font-size: 32px;
	font-weight: 700;
	line-height: 1;
}

.tqb-stat-card .stat-icon {
	float: right;
	width: 48px;
	height: 48px;
	border-radius: 10px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.tqb-stat-card .stat-icon .dashicons {
	font-size: 24px;
	width: 24px;
	height: 24px;
}

.tqb-stat-card.stat-total { border-left: 4px solid #001a44; }
.tqb-stat-card.stat-total .stat-value { color: #001a44; }
.tqb-stat-card.stat-total .stat-icon { background: #f0f6fc; color: #001a44; }

.tqb-stat-card.stat-completed { border-left: 4px solid #059669; }
.tqb-stat-card.stat-completed .stat-value { color: #059669; }
.tqb-stat-card.stat-completed .stat-icon { background: #ecfdf5; color: #059669; }

.tqb-stat-card.stat-progress { border-left: 4px solid #d97706; }
.tqb-stat-card.stat-progress .stat-value { color: #d97706; }
.tqb-stat-card.stat-progress .stat-icon { background: #fffbeb; color: #d97706; }

.tqb-stat-card.stat-abandoned { border-left: 4px solid #dc2626; }
.tqb-stat-card.stat-abandoned .stat-value { color: #dc2626; }
.tqb-stat-card.stat-abandoned .stat-icon { background: #fef2f2; color: #dc2626; }

/* Main Content Card */
.tqb-content-card {
	background: #fff;
	border-radius: 12px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
	border: 1px solid #e2e8f0;
	overflow: hidden;
}

/* Toolbar */
.tqb-toolbar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 20px 24px;
	background: #f8fafc;
	border-bottom: 1px solid #e2e8f0;
	flex-wrap: wrap;
	gap: 16px;
}

.tqb-filters {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	align-items: center;
}

.tqb-filter-btn {
	padding: 8px 16px;
	border-radius: 8px;
	font-size: 13px;
	font-weight: 500;
	text-decoration: none;
	color: #475569;
	background: #fff;
	border: 1px solid #e2e8f0;
	transition: all 0.2s;
	display: inline-flex;
	align-items: center;
	gap: 6px;
}

.tqb-filter-btn:hover {
	background: #f1f5f9;
	border-color: #cbd5e1;
	color: #1e293b;
}

.tqb-filter-btn.active {
	background: #001a44;
	color: #fff;
	border-color: #001a44;
}

.tqb-filter-btn .count {
	background: rgba(0,0,0,0.1);
	padding: 2px 6px;
	border-radius: 10px;
	font-size: 11px;
}

.tqb-filter-btn.active .count {
	background: rgba(255,255,255,0.2);
}

.tqb-filter-divider {
	width: 1px;
	height: 24px;
	background: #e2e8f0;
	margin: 0 8px;
}

.tqb-search-form {
	display: flex;
	gap: 8px;
}

.tqb-search-input {
	padding: 8px 14px;
	border-radius: 8px;
	border: 1px solid #e2e8f0;
	font-size: 13px;
	min-width: 240px;
	background: #fff;
	transition: border-color 0.2s;
}

.tqb-search-input:focus {
	outline: none;
	border-color: #001a44;
	box-shadow: 0 0 0 3px rgba(0,26,68,0.1);
}

/* Table */
.tqb-table-wrapper {
	overflow-x: auto;
}

.tqb-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 14px;
}

.tqb-table thead {
	background: #f8fafc;
}

.tqb-table th {
	padding: 14px 16px;
	text-align: left;
	font-weight: 600;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #64748b;
	border-bottom: 2px solid #e2e8f0;
	white-space: nowrap;
}

.tqb-table th a {
	color: #64748b;
	text-decoration: none;
	display: flex;
	align-items: center;
	gap: 4px;
}

.tqb-table th a:hover {
	color: #001a44;
}

.tqb-table td {
	padding: 16px;
	border-bottom: 1px solid #f1f5f9;
	vertical-align: middle;
}

.tqb-table tbody tr {
	transition: background-color 0.15s;
}

.tqb-table tbody tr:hover {
	background: #f8fafc;
}

.tqb-table tbody tr:last-child td {
	border-bottom: none;
}

/* Contact Cell */
.tqb-contact-cell {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.tqb-contact-name {
	font-weight: 600;
	color: #1e293b;
}

.tqb-contact-email {
	font-size: 12px;
	color: #64748b;
}

.tqb-contact-email a {
	color: #64748b;
	text-decoration: none;
}

.tqb-contact-email a:hover {
	color: #001a44;
	text-decoration: underline;
}

/* Type Badge */
.tqb-type-badge {
	display: inline-flex;
	align-items: center;
	padding: 4px 10px;
	border-radius: 20px;
	font-size: 11px;
	font-weight: 600;
	text-transform: capitalize;
}

.tqb-type-badge.individual {
	background: #ede9fe;
	color: #5b21b6;
}

.tqb-type-badge.business {
	background: #dbeafe;
	color: #1e40af;
}

/* Status Badge */
.tqb-status-badge {
	display: inline-flex;
	align-items: center;
	padding: 4px 10px;
	border-radius: 20px;
	font-size: 11px;
	font-weight: 600;
}

.tqb-status-badge.completed {
	background: #dcfce7;
	color: #166534;
}

.tqb-status-badge.in_progress {
	background: #fef3c7;
	color: #92400e;
}

.tqb-status-badge.abandoned {
	background: #fee2e2;
	color: #991b1b;
}

.tqb-status-badge .dot {
	width: 6px;
	height: 6px;
	border-radius: 50%;
	margin-right: 6px;
}

.tqb-status-badge.completed .dot { background: #22c55e; }
.tqb-status-badge.in_progress .dot { background: #f59e0b; }
.tqb-status-badge.abandoned .dot { background: #ef4444; }

/* Progress Badge */
.tqb-progress-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding: 4px 8px;
	border-radius: 6px;
	font-size: 11px;
	font-weight: 600;
	background: #f1f5f9;
	color: #475569;
	min-width: 70px;
}

/* Quote Amount */
.tqb-quote-amount {
	font-weight: 600;
	color: #001a44;
}

.tqb-quote-custom {
	color: #d97706;
	font-weight: 600;
}

/* Action Buttons */
.tqb-action-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	border-radius: 8px;
	background: #f1f5f9;
	color: #475569;
	text-decoration: none;
	transition: all 0.2s;
	border: none;
	cursor: pointer;
}

.tqb-action-btn:hover {
	background: #001a44;
	color: #fff;
}

.tqb-action-btn.view {
	background: #f0f6fc;
	color: #001a44;
}

.tqb-action-btn.view:hover {
	background: #001a44;
	color: #fff;
}

.tqb-action-btn.delete:hover {
	background: #dc2626;
	color: #fff;
}

/* Bulk Actions Bar */
.tqb-bulk-bar {
	display: none;
	justify-content: space-between;
	align-items: center;
	padding: 12px 24px;
	background: #fef2f2;
	border-top: 1px solid #fecaca;
}

.tqb-bulk-bar.visible {
	display: flex;
}

.tqb-bulk-bar-left {
	display: flex;
	align-items: center;
	gap: 10px;
}

.tqb-bulk-select-label {
	font-size: 13px;
	font-weight: 500;
	color: #475569;
}

.tqb-selected-count {
	font-size: 13px;
	font-weight: 600;
	color: #dc2626;
}

.tqb-bulk-bar-right {
	display: flex;
	align-items: center;
	gap: 8px;
}

.tqb-bulk-select {
	padding: 6px 12px;
	border-radius: 6px;
	border: 1px solid #e2e8f0;
	font-size: 13px;
	background: #fff;
	cursor: pointer;
	min-width: 140px;
}

.tqb-bulk-select:focus {
	outline: none;
	border-color: #001a44;
}

.tqb-bulk-apply-btn {
	padding: 6px 16px;
	border-radius: 6px;
	font-size: 13px;
	font-weight: 500;
	background: #dc2626;
	color: #fff;
	border: none;
	cursor: pointer;
	transition: background-color 0.2s;
}

.tqb-bulk-apply-btn:hover {
	background: #b91c1c;
}

/* Pagination */
.tqb-pagination {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 16px 24px;
	background: #f8fafc;
	border-top: 1px solid #e2e8f0;
}

.tqb-pagination-info {
	font-size: 13px;
	color: #64748b;
}

.tqb-pagination-pages {
	display: flex;
	gap: 4px;
	align-items: center;
}

.tqb-pagination-pages a,
.tqb-pagination-pages span {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 36px;
	height: 36px;
	padding: 0 12px;
	border-radius: 8px;
	font-size: 13px;
	font-weight: 500;
	text-decoration: none;
	color: #475569;
	background: #fff;
	border: 1px solid #e2e8f0;
	transition: all 0.2s;
}

.tqb-pagination-pages a:hover {
	background: #f1f5f9;
	border-color: #cbd5e1;
}

.tqb-pagination-pages .current {
	background: #001a44;
	color: #fff;
	border-color: #001a44;
}

.tqb-pagination-pages .dots {
	background: none;
	border: none;
	color: #64748b;
}

/* Empty State */
.tqb-empty-state {
	text-align: center;
	padding: 60px 40px;
}

.tqb-empty-state .dashicons {
	font-size: 64px;
	width: 64px;
	height: 64px;
	color: #cbd5e1;
	margin-bottom: 16px;
}

.tqb-empty-state h3 {
	margin: 0 0 8px;
	font-size: 18px;
	color: #475569;
}

.tqb-empty-state p {
	margin: 0;
	color: #94a3b8;
}

/* Legend */
.tqb-legend {
	display: flex;
	gap: 20px;
	padding: 16px 24px;
	background: #f8fafc;
	border-top: 1px solid #e2e8f0;
	font-size: 12px;
	color: #64748b;
	flex-wrap: wrap;
}

.tqb-legend-item {
	display: flex;
	align-items: center;
	gap: 8px;
}

/* ============================================
   Modal Styles
   ============================================ */
.tqb-modal-overlay {
	display: none;
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(15, 23, 42, 0.6);
	backdrop-filter: blur(4px);
	z-index: 100000;
}

.tqb-modal {
	display: none;
	position: fixed;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	background: #fff;
	width: 95%;
	max-width: 900px;
	max-height: 90vh;
	border-radius: 16px;
	box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
	z-index: 100001;
	overflow: hidden;
}

.tqb-modal-header {
	background: linear-gradient(135deg, #001a44 0%, #0f3d7a 100%);
	color: #fff;
	padding: 24px 28px;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.tqb-modal-header h2 {
	margin: 0;
	font-size: 20px;
	font-weight: 600;
	color: #fff;
	display: flex;
	align-items: center;
	gap: 12px;
}

.tqb-modal-header h2 .dashicons {
	font-size: 24px;
	width: 24px;
	height: 24px;
}

.tqb-modal-close {
	background: rgba(255,255,255,0.15);
	border: none;
	color: #fff;
	width: 40px;
	height: 40px;
	border-radius: 10px;
	font-size: 24px;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.2s;
}

.tqb-modal-close:hover {
	background: rgba(255,255,255,0.25);
	transform: scale(1.05);
}

.tqb-modal-body {
	padding: 28px;
	overflow-y: auto;
	max-height: calc(90vh - 88px);
}

/* Modal Sections */
.tqb-modal-section {
	margin-bottom: 28px;
}

.tqb-modal-section:last-child {
	margin-bottom: 0;
}

.tqb-modal-section-title {
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 1px;
	color: #001a44;
	margin-bottom: 16px;
	padding-bottom: 10px;
	border-bottom: 2px solid #001a44;
	display: flex;
	align-items: center;
	gap: 8px;
}

.tqb-modal-section-title .dashicons {
	font-size: 16px;
	width: 16px;
	height: 16px;
}

/* Info Grid */
.tqb-info-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 16px;
}

.tqb-info-card {
	background: #f8fafc;
	padding: 16px 20px;
	border-radius: 10px;
	border-left: 4px solid #001a44;
}

.tqb-info-card.full {
	grid-column: 1 / -1;
}

.tqb-info-label {
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #64748b;
	margin-bottom: 6px;
}

.tqb-info-value {
	font-size: 15px;
	font-weight: 600;
	color: #1e293b;
}

.tqb-info-value a {
	color: #001a44;
	text-decoration: none;
}

.tqb-info-value a:hover {
	text-decoration: underline;
}

/* Answers Table */
/* The table itself scrolls within a fixed-height wrapper instead of pushing
   the whole modal taller — sticky header stays visible while scrolling. */
.tqb-answers-table-wrap {
	max-height: 320px;
	overflow-y: auto;
	overflow-x: hidden;
	border-radius: 10px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.tqb-answers-table {
	width: 100%;
	border-collapse: collapse;
	background: #fff;
}

.tqb-answers-table th {
	position: sticky;
	top: 0;
	text-align: left;
	padding: 10px 16px;
	background: #f8fafc;
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #64748b;
}

.tqb-answers-table td {
	padding: 9px 16px;
	border-top: 1px solid #f1f5f9;
}

.tqb-answers-table tr:hover td {
	background: #f8fafc;
}

.tqb-question-cell {
	font-weight: 500;
	color: #475569;
}

.tqb-answer-cell {
	color: #1e293b;
	font-weight: 600;
}

.tqb-answer-yes {
	color: #059669;
}

.tqb-answer-no {
	color: #dc2626;
}

/* Collapsed summary for unselected items — keeps the modal from growing a
   full table row for every "No" answer, which is what made this section
   take up so much height. Also capped with its own scroll for the rare
   combined submission with a lot of unchecked items. */
.tqb-answers-not-selected {
	margin-top: 10px;
	padding: 10px 16px;
	background: #f8fafc;
	border-radius: 10px;
	font-size: 12px;
	line-height: 1.6;
	color: #94a3b8;
	max-height: 100px;
	overflow-y: auto;
}

.tqb-answers-not-selected__label {
	font-weight: 700;
	color: #64748b;
	text-transform: uppercase;
	font-size: 10px;
	letter-spacing: 0.5px;
	margin-right: 4px;
}

/* Loading */
.tqb-loading {
	text-align: center;
	padding: 60px 40px;
}

.tqb-loading-spinner {
	width: 48px;
	height: 48px;
	border: 4px solid #e2e8f0;
	border-top-color: #001a44;
	border-radius: 50%;
	animation: tqb-spin 1s linear infinite;
	margin: 0 auto 16px;
}

@keyframes tqb-spin {
	to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 1200px) {
	.tqb-stats-grid {
		grid-template-columns: repeat(2, 1fr);
	}
}

@media (max-width: 768px) {
	.tqb-stats-grid {
		grid-template-columns: 1fr;
	}
	
	.tqb-toolbar {
		flex-direction: column;
		align-items: stretch;
	}
	
	.tqb-filters {
		justify-content: center;
	}
	
	.tqb-search-form {
		flex-direction: column;
	}
	
	.tqb-search-input {
		min-width: auto;
	}
	
	.tqb-info-grid {
		grid-template-columns: 1fr;
	}
	
	.tqb-pagination {
		flex-direction: column;
		gap: 16px;
	}
}
</style>

<!-- Page Header -->
<div class="tqb-page-header">
	<h1>
		<span class="dashicons dashicons-chart-bar"></span>
		Quote Submissions
	</h1>
</div>

<!-- Stats Cards -->
<div class="tqb-stats-grid">
	<div class="tqb-stat-card stat-total">
		<div class="stat-label">Total Submissions</div>
		<div class="stat-value"><?php echo number_format( $counts['all'] ); ?></div>
		<div class="stat-icon"><span class="dashicons dashicons-database"></span></div>
	</div>
	<div class="tqb-stat-card stat-completed">
		<div class="stat-label">Completed</div>
		<div class="stat-value"><?php echo number_format( $counts['completed'] ); ?></div>
		<div class="stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
	</div>
	<div class="tqb-stat-card stat-progress">
		<div class="stat-label">In Progress</div>
		<div class="stat-value"><?php echo number_format( $counts['in_progress'] ); ?></div>
		<div class="stat-icon"><span class="dashicons dashicons-clock"></span></div>
	</div>
	<div class="tqb-stat-card stat-abandoned">
		<div class="stat-label">Abandoned</div>
		<div class="stat-value"><?php echo number_format( $counts['abandoned'] ); ?></div>
		<div class="stat-icon"><span class="dashicons dashicons-dismiss"></span></div>
	</div>
</div>

<!-- Main Content Card -->
<div class="tqb-content-card">
	<!-- Toolbar -->
	<div class="tqb-toolbar">
		<div class="tqb-toolbar-left">
		<div class="tqb-filters">
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'status' => '', 'paged' => 1 ) ) ); ?>" class="tqb-filter-btn <?php echo empty( $status_filter ) ? 'active' : ''; ?>">
				All <span class="count"><?php echo $counts['all']; ?></span>
			</a>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'status' => 'completed', 'paged' => 1 ) ) ); ?>" class="tqb-filter-btn <?php echo 'completed' === $status_filter ? 'active' : ''; ?>">
				Completed <span class="count"><?php echo $counts['completed']; ?></span>
			</a>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'status' => 'in_progress', 'paged' => 1 ) ) ); ?>" class="tqb-filter-btn <?php echo 'in_progress' === $status_filter ? 'active' : ''; ?>">
				In Progress <span class="count"><?php echo $counts['in_progress']; ?></span>
			</a>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'status' => 'abandoned', 'paged' => 1 ) ) ); ?>" class="tqb-filter-btn <?php echo 'abandoned' === $status_filter ? 'active' : ''; ?>">
				Abandoned <span class="count"><?php echo $counts['abandoned']; ?></span>
			</a>
			
			<div class="tqb-filter-divider"></div>
			
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'type' => 'individual', 'paged' => 1 ) ) ); ?>" class="tqb-filter-btn <?php echo 'individual' === $type_filter ? 'active' : ''; ?>">
				Individual
			</a>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'type' => 'business', 'paged' => 1 ) ) ); ?>" class="tqb-filter-btn <?php echo 'business' === $type_filter ? 'active' : ''; ?>">
				Business
			</a>
		</div>
		</div>

	<form method="get" action="" class="tqb-search-form">
			<input type="hidden" name="page" value="tqb-settings" />
			<input type="hidden" name="tab" value="submissions" />
			<?php if ( $status_filter ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>" />
			<?php endif; ?>
			<?php if ( $type_filter ) : ?>
				<input type="hidden" name="type" value="<?php echo esc_attr( $type_filter ); ?>" />
			<?php endif; ?>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by name, email, or phone..." class="tqb-search-input" />
			<button type="submit" class="tqb-filter-btn">
				<span class="dashicons dashicons-search" style="font-size: 16px; width: 16px; height: 16px;"></span>
				Search
			</button>
			<?php if ( ! empty( $search ) ) : ?>
				<a href="<?php echo esc_url( tqb_build_filter_url( array( 's' => '' ) ) ); ?>" class="tqb-filter-btn">
					<span class="dashicons dashicons-dismiss" style="font-size: 16px; width: 16px; height: 16px;"></span>
					Clear
				</a>
			<?php endif; ?>
		</form>
	</div>

	<?php if ( empty( $submissions ) ) : ?>
		<div class="tqb-empty-state">
			<span class="dashicons dashicons-clipboard"></span>
			<h3>No submissions found</h3>
			<p><?php echo ! empty( $search ) ? 'Try adjusting your search or filters.' : 'Submissions will appear here once users start filling out the quote form.'; ?></p>
		</div>
	<?php else : ?>
		<div class="tqb-table-wrapper">
			<table class="tqb-table">
				<thead>
					<tr>
						<th style="width: 50px;">
							<input type="checkbox" id="tqb-select-all" style="cursor: pointer;" />
						</th>
						<th style="width: 70px;">
							<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'id', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'id' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>">
								ID<?php echo tqb_get_sort_indicator( 'id' ); ?>
							</a>
						</th>
						<th>
							<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'contact_name', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'contact_name' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>">
								Contact<?php echo tqb_get_sort_indicator( 'contact_name' ); ?>
							</a>
						</th>
						<th style="width: 100px;">
							<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'quote_type', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'quote_type' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>">
								Type<?php echo tqb_get_sort_indicator( 'quote_type' ); ?>
							</a>
						</th>
						<th style="width: 120px;">
							<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'status', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'status' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>">
								Status<?php echo tqb_get_sort_indicator( 'status' ); ?>
							</a>
						</th>
						<th style="width: 90px;">Progress</th>
						<th style="width: 110px;">
							<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'calculated_total', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'calculated_total' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>">
								Quote<?php echo tqb_get_sort_indicator( 'calculated_total' ); ?>
							</a>
						</th>
						<th style="width: 130px;">
							<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'created_at', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'created_at' && isset( $_GET['order'] ) && $_GET['order'] === 'DESC' ) ? 'ASC' : 'DESC', 'paged' => 1 ) ) ); ?>">
								Created<?php echo tqb_get_sort_indicator( 'created_at' ); ?>
							</a>
						</th>
						<th style="width: 130px;">IP Address</th>
						<th style="width: 100px;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $submissions as $sub ) : ?>
						<?php
						$status_info = tqb_get_display_status( $sub['status'] ?? '' );
						$created = date_create_immutable( $sub['created_at'], new DateTimeZone( 'UTC' ) );
						$chicago_tz = new DateTimeZone( 'America/Chicago' );
						$created->setTimezone( $chicago_tz );
						?>
						<tr>
							<td>
								<input type="checkbox" name="delete_ids[]" value="<?php echo esc_attr( $sub['id'] ); ?>" class="tqb-delete-checkbox" />
							</td>
							<td>
								<span style="font-weight: 600; color: #64748b;">#<?php echo esc_html( $sub['id'] ); ?></span>
							</td>
							<td>
								<div class="tqb-contact-cell">
									<span class="tqb-contact-name"><?php echo esc_html( ! empty( $sub['contact_name'] ) ? $sub['contact_name'] : '—' ); ?></span>
									<span class="tqb-contact-email">
										<a href="mailto:<?php echo esc_attr( $sub['contact_email'] ?? '' ); ?>"><?php echo esc_html( ! empty( $sub['contact_email'] ) ? $sub['contact_email'] : '—' ); ?></a>
									</span>
									<?php if ( ! empty( $sub['contact_phone'] ) ) : ?>
										<span class="tqb-contact-email"><?php echo esc_html( $sub['contact_phone'] ); ?></span>
									<?php endif; ?>
								</div>
							</td>
							<td>
								<span class="tqb-type-badge <?php echo esc_attr( $sub['quote_type'] ); ?>">
									<?php echo esc_html( ucfirst( $sub['quote_type'] ) ); ?>
								</span>
							</td>
							<td>
								<span class="tqb-status-badge <?php echo esc_attr( $status_info['class'] ); ?>">
									<span class="dot"></span>
									<?php echo esc_html( $status_info['label'] ); ?>
								</span>
							</td>
							<td>
								<span class="tqb-progress-badge">
									<?php
									$step_labels = array( '', '1-Type', '2-Filing Status', '3-Contact', '4-Questions', '5-Review', '6-Result' );
									$step = isset( $sub['last_completed_step'] ) ? (int) $sub['last_completed_step'] : 0;
									$status = $sub['status'] ?? '';
									if ( $status === 'completed' ) {
										echo '<span style="color: #22c55e; font-weight: 500;">✓ Done</span>';
									} elseif ( $status === 'abandoned' ) {
										echo '<span style="color: #94a3b8;">Abandoned</span>';
									} else {
										echo esc_html( $step_labels[ $step ] ?? '—' );
									}
									?>
								</span>
							</td>
							<td>
								<?php if ( ! empty( $sub['is_custom_quote'] ) ) : ?>
									<span class="tqb-quote-custom">Custom</span>
								<?php elseif ( null !== $sub['calculated_total'] && $sub['calculated_total'] !== '' ) : ?>
									<span class="tqb-quote-amount">$<?php echo number_format( (float) $sub['calculated_total'], 2 ); ?></span>
								<?php else : ?>
									<span style="color: #94a3b8;">—</span>
								<?php endif; ?>
							</td>
							<td>
								<span style="color: #64748b; font-size: 13px;">
									<?php echo esc_html( $created->format( 'M j, Y' ) ); ?>
									<br />
									<span style="font-size: 11px;"><?php echo esc_html( $created->format( 'g:i A CT' ) ); ?></span>
								</span>
							</td>
							<td>
								<?php 
								$ip = isset( $sub['user_ip'] ) && ! empty( $sub['user_ip'] ) ? esc_html( $sub['user_ip'] ) : '<span style="color: #94a3b8;">—</span>';
								echo $ip;
								?>
							</td>
							<td>
								<button type="button" class="tqb-action-btn view" title="View Details" data-id="<?php echo esc_attr( $sub['id'] ); ?>">
									<span class="dashicons dashicons-visibility" style="font-size: 18px; width: 18px; height: 18px;"></span>
								</button>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tqb_delete_submission&id=' . $sub['id'] ), 'tqb_delete_sub_' . $sub['id'] ) ); ?>" class="tqb-action-btn delete" title="Delete" onclick="return confirm('Delete this submission? This cannot be undone.');">
									<span class="dashicons dashicons-trash" style="font-size: 18px; width: 18px; height: 18px;"></span>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Bulk Actions Bar -->
		<div class="tqb-bulk-bar" id="tqb-bulk-bar">
			<div class="tqb-bulk-bar-left">
				<span class="tqb-selected-count" id="tqb-selected-count" style="display: none;">(<span id="tqb-selected-number">0</span> selected)</span>
			</div>
			<div class="tqb-bulk-bar-right" id="tqb-bulk-bar-right" style="display: none;">
				<select name="bulk_action" id="tqb-bulk-action-select" class="tqb-bulk-select">
					<option value="">Bulk Actions</option>
					<option value="delete">Delete Selected</option>
				</select>
				<button type="button" class="tqb-bulk-apply-btn" id="tqb-bulk-apply-btn">
					Apply
				</button>
			</div>
		</div>

		<!-- Bulk Delete Form -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php?action=tqb_delete_submissions' ) ); ?>" id="tqb-bulk-delete-form" style="display: none;">
			<?php wp_nonce_field( 'tqb_delete_submissions', 'tqb_delete_nonce' ); ?>
			<div id="tqb-bulk-ids-container"></div>
		</form>

		<!-- Pagination -->
		<div class="tqb-pagination">
			<div class="tqb-pagination-info">
				Showing <?php echo number_format( ( ( $current_page - 1 ) * $per_page ) + 1 ); ?> - <?php echo number_format( min( $current_page * $per_page, $total_count ) ); ?> of <?php echo number_format( $total_count ); ?> submissions
			</div>
			<div class="tqb-pagination-pages">
				<?php if ( $current_page > 1 ) : ?>
					<a href="<?php echo esc_url( tqb_build_filter_url( array( 'paged' => 1 ) ) ); ?>">&laquo;</a>
					<a href="<?php echo esc_url( tqb_build_filter_url( array( 'paged' => $current_page - 1 ) ) ); ?>">&lsaquo;</a>
				<?php endif; ?>
				
				<?php
				$start = max( 1, $current_page - 2 );
				$end = min( $total_pages, $current_page + 2 );
				
				if ( $start > 1 ) :
					?>
					<a href="<?php echo esc_url( tqb_build_filter_url( array( 'paged' => 1 ) ) ); ?>">1</a>
					<?php if ( $start > 2 ) : ?>
						<span class="dots">...</span>
					<?php endif; ?>
				<?php endif; ?>
				
				<?php for ( $i = $start; $i <= $end; $i++ ) : ?>
					<?php if ( $i === $current_page ) : ?>
						<span class="current"><?php echo $i; ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( tqb_build_filter_url( array( 'paged' => $i ) ) ); ?>"><?php echo $i; ?></a>
					<?php endif; ?>
				<?php endfor; ?>
				
				<?php if ( $end < $total_pages ) : ?>
					<?php if ( $end < $total_pages - 1 ) : ?>
						<span class="dots">...</span>
					<?php endif; ?>
					<a href="<?php echo esc_url( tqb_build_filter_url( array( 'paged' => $total_pages ) ) ); ?>"><?php echo $total_pages; ?></a>
				<?php endif; ?>
				
				<?php if ( $current_page < $total_pages ) : ?>
					<a href="<?php echo esc_url( tqb_build_filter_url( array( 'paged' => $current_page + 1 ) ) ); ?>">&rsaquo;</a>
					<a href="<?php echo esc_url( tqb_build_filter_url( array( 'paged' => $total_pages ) ) ); ?>">&raquo;</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Legend -->
	<div class="tqb-legend">
		<div class="tqb-legend-item">
			<span class="tqb-status-badge completed"><span class="dot"></span>Completed</span>
			= Submitted quote
		</div>
		<div class="tqb-legend-item">
			<span class="tqb-status-badge in_progress"><span class="dot"></span>In Progress</span>
			= Started but not finished
		</div>
		<div class="tqb-legend-item">
			<span class="tqb-status-badge abandoned"><span class="dot"></span>Abandoned</span>
			= Follow-up emails sent
		</div>
	</div>
</div>

<!-- Modal -->
<div class="tqb-modal-overlay" id="tqb-modal-overlay"></div>
<div class="tqb-modal" id="tqb-modal">
	<div class="tqb-modal-header">
		<h2>
			<span class="dashicons dashicons-clipboard"></span>
			Submission Details
		</h2>
		<button class="tqb-modal-close" id="tqb-modal-close">&times;</button>
	</div>
	<div class="tqb-modal-body" id="tqb-modal-content">
		<div class="tqb-loading">
			<div class="tqb-loading-spinner"></div>
			Loading submission details...
		</div>
	</div>
</div>



