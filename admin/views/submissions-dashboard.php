<?php
/**
 * Professional Submissions Dashboard
 * Modern card-based design with bulk actions and status management.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define statuses
$statuses = array(
	'new' => array('label' => 'New', 'color' => '#3b82f6', 'bg' => '#eff6ff'),
	'contacted' => array('label' => 'Contacted', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'),
	'quoted' => array('label' => 'Quote Sent', 'color' => '#f59e0b', 'bg' => '#fffbeb'),
	'won' => array('label' => 'Won', 'color' => '#10b981', 'bg' => '#ecfdf5'),
	'lost' => array('label' => 'Lost', 'color' => '#ef4444', 'bg' => '#fef2f2'),
	'follow_up' => array('label' => 'Follow Up', 'color' => '#f97316', 'bg' => '#fff7ed'),
);

// Build filter URL helper
function tqb_dash_filter_url($params = array()) {
	$base = admin_url('admin.php?page=tqb-settings&tab=submissions');
	$current = array(
		'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
		'type' => isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '',
		's' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
		'paged' => isset($_GET['paged']) ? absint($_GET['paged']) : 1,
	);
	$merged = array_merge(array_filter($current), array_filter($params));
	foreach ($merged as $k => $v) {
		if ($v === '' || ($k === 'paged' && $v == 1)) unset($merged[$k]);
	}
	return add_query_arg($merged, $base);
}
?>

<div class="tqb-dashboard">
	<!-- Header -->
	<div class="tqb-dash-header">
		<div class="tqb-dash-title">
			<h1>Quote Submissions</h1>
			<p class="tqb-dash-subtitle">Manage and track all your quote requests</p>
		</div>
		<div class="tqb-dash-actions">
			<button type="button" class="tqb-btn tqb-btn-secondary" id="tqb-refresh-btn">
				<span class="dashicons dashicons-update"></span>
				Refresh
			</button>
		</div>
	</div>

	<!-- Stats Cards -->
	<div class="tqb-stats-grid">
		<div class="tqb-stat-card">
			<div class="tqb-stat-icon" style="background: #eff6ff; color: #3b82f6;">
				<span class="dashicons dashicons-clipboard"></span>
			</div>
			<div class="tqb-stat-content">
				<span class="tqb-stat-number"><?php echo $counts['all']; ?></span>
				<span class="tqb-stat-label">Total</span>
			</div>
		</div>
		<div class="tqb-stat-card">
			<div class="tqb-stat-icon" style="background: #ecfdf5; color: #10b981;">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="tqb-stat-content">
				<span class="tqb-stat-number"><?php echo $counts['completed']; ?></span>
				<span class="tqb-stat-label">Completed</span>
			</div>
		</div>
		<div class="tqb-stat-card">
			<div class="tqb-stat-icon" style="background: #fff8eb; color: #f59e0b;">
				<span class="dashicons dashicons-clock"></span>
			</div>
			<div class="tqb-stat-content">
				<span class="tqb-stat-number"><?php echo $counts['in_progress']; ?></span>
				<span class="tqb-stat-label">In Progress</span>
			</div>
		</div>
		<div class="tqb-stat-card">
			<div class="tqb-stat-icon" style="background: #fef2f2; color: #ef4444;">
				<span class="dashicons dashicons-dismiss"></span>
			</div>
			<div class="tqb-stat-content">
				<span class="tqb-stat-number"><?php echo $counts['abandoned']; ?></span>
				<span class="tqb-stat-label">Abandoned</span>
			</div>
		</div>
	</div>

	<!-- Filters Bar -->
	<div class="tqb-filters-bar">
		<div class="tqb-filters-left">
			<!-- Status Tabs -->
			<div class="tqb-status-tabs">
				<a href="<?php echo esc_url(tqb_dash_filter_url(array('status' => ''))); ?>" 
				   class="tqb-status-tab <?php echo empty($status_filter) ? 'active' : ''; ?>">
					All <span class="tqb-count"><?php echo $counts['all']; ?></span>
				</a>
				<?php foreach ($statuses as $key => $status) : ?>
					<?php 
					$status_count = isset($counts[$key]) ? $counts[$key] : 0;
					if ($status_count > 0) :
					?>
					<a href="<?php echo esc_url(tqb_dash_filter_url(array('status' => $key))); ?>" 
					   class="tqb-status-tab <?php echo $status_filter === $key ? 'active' : ''; ?>"
					   style="--status-color: <?php echo $status['color']; ?>;">
						<?php echo $status['label']; ?> <span class="tqb-count"><?php echo $status_count; ?></span>
					</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="tqb-filters-right">
			<!-- Type Filter -->
			<select class="tqb-select" onchange="window.location.href='<?php echo admin_url('admin.php?page=tqb-settings&tab=submissions'); ?>&status=<?php echo esc_attr($status_filter); ?>&type=' + this.value">
				<option value="">All Types</option>
				<option value="individual" <?php selected($type_filter, 'individual'); ?>>Individual</option>
				<option value="business" <?php selected($type_filter, 'business'); ?>>Business</option>
				<option value="combined" <?php selected($type_filter, 'combined'); ?>>Combined</option>
			</select>
			
			<!-- Search -->
			<form method="get" class="tqb-search-form">
				<input type="hidden" name="page" value="tqb-settings" />
				<input type="hidden" name="tab" value="submissions" />
				<?php if ($status_filter) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>" />
				<?php endif; ?>
				<input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name, email..." class="tqb-search-input" />
				<button type="submit" class="tqb-btn tqb-btn-icon">
					<span class="dashicons dashicons-search"></span>
				</button>
			</form>
		</div>
	</div>

	<!-- Bulk Actions Bar -->
	<div class="tqb-bulk-bar" id="tqb-bulk-bar" style="display: none;">
		<div class="tqb-bulk-selected">
			<span id="tqb-selected-count">0</span> selected
		</div>
		<div class="tqb-bulk-actions">
			<select id="tqb-bulk-status" class="tqb-select">
				<option value="">Change Status...</option>
				<?php foreach ($statuses as $key => $status) : ?>
					<option value="<?php echo esc_attr($key); ?>">→ <?php echo $status['label']; ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="tqb-btn tqb-btn-primary" id="tqb-bulk-email-btn">
				<span class="dashicons dashicons-email-alt"></span>
				Send Email
			</button>
			<button type="button" class="tqb-btn tqb-btn-danger" id="tqb-bulk-delete-btn">
				<span class="dashicons dashicons-trash"></span>
				Delete
			</button>
		</div>
	</div>

	<!-- Submissions Table -->
	<?php if (empty($submissions)) : ?>
		<div class="tqb-empty-state">
			<span class="dashicons dashicons-clipboard"></span>
			<h3>No submissions found</h3>
			<p>Try adjusting your filters or search criteria.</p>
		</div>
	<?php else : ?>
		<div class="tqb-table-wrapper">
			<table class="tqb-table" id="tqb-submissions-table">
				<thead>
					<tr>
						<th class="tqb-col-check">
							<input type="checkbox" id="tqb-select-all" />
						</th>
						<th class="tqb-col-id">ID</th>
						<th class="tqb-col-contact">Contact</th>
						<th class="tqb-col-type">Type</th>
						<th class="tqb-col-total">Total</th>
						<th class="tqb-col-status">Status</th>
						<th class="tqb-col-date">Date</th>
						<th class="tqb-col-actions">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($submissions as $sub) : 
						$submission_type = !empty($sub['quote_type']) ? ucfirst($sub['quote_type']) : 'Unknown';
						$total = !empty($sub['calculated_total']) ? '$' . number_format((float)$sub['calculated_total'], 2) : '-';
						$created = !empty($sub['created_at']) ? date('M j, Y g:i A', strtotime($sub['created_at'])) : '-';
						$current_status = !empty($sub['status']) ? $sub['status'] : 'new';
					?>
						<tr data-id="<?php echo esc_attr($sub['id']); ?>">
							<td class="tqb-col-check">
								<input type="checkbox" class="tqb-row-check" value="<?php echo esc_attr($sub['id']); ?>" />
							</td>
							<td class="tqb-col-id">
								<strong>#<?php echo $sub['id']; ?></strong>
							</td>
							<td class="tqb-col-contact">
								<div class="tqb-contact-cell">
									<strong><?php echo esc_html($sub['contact_name'] ?: '-'); ?></strong>
									<a href="mailto:<?php echo esc_attr($sub['contact_email']); ?>"><?php echo esc_html($sub['contact_email']); ?></a>
									<?php if (!empty($sub['contact_phone'])) : ?>
										<span class="tqb-phone"><?php echo esc_html($sub['contact_phone']); ?></span>
									<?php endif; ?>
								</div>
							</td>
							<td class="tqb-col-type">
								<span class="tqb-type-badge tqb-type-<?php echo esc_attr(strtolower($submission_type)); ?>">
									<?php echo $submission_type; ?>
								</span>
							</td>
							<td class="tqb-col-total">
								<strong><?php echo $total; ?></strong>
							</td>
							<td class="tqb-col-status">
								<select class="tqb-status-select tqb-status-<?php echo esc_attr($current_status); ?>" 
										data-id="<?php echo esc_attr($sub['id']); ?>">
									<option value="new" <?php selected($current_status, 'new'); ?>>New</option>
									<option value="contacted" <?php selected($current_status, 'contacted'); ?>>Contacted</option>
									<option value="quoted" <?php selected($current_status, 'quoted'); ?>>Quote Sent</option>
									<option value="won" <?php selected($current_status, 'won'); ?>>Won</option>
									<option value="lost" <?php selected($current_status, 'lost'); ?>>Lost</option>
									<option value="follow_up" <?php selected($current_status, 'follow_up'); ?>>Follow Up</option>
								</select>
							</td>
							<td class="tqb-col-date">
								<span class="tqb-date"><?php echo $created; ?></span>
							</td>
							<td class="tqb-col-actions">
								<div class="tqb-actions-cell">
									<button type="button" class="tqb-action-btn tqb-view-btn" 
											data-id="<?php echo esc_attr($sub['id']); ?>" title="View Details">
										<span class="dashicons dashicons-visibility"></span>
									</button>
									<button type="button" class="tqb-action-btn tqb-email-btn" 
											data-id="<?php echo esc_attr($sub['id']); ?>" title="Send Email">
										<span class="dashicons dashicons-email-alt"></span>
									</button>
									<button type="button" class="tqb-action-btn tqb-delete-btn" 
											data-id="<?php echo esc_attr($sub['id']); ?>" title="Delete">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Pagination -->
		<?php if ($total_pages > 1) : ?>
			<div class="tqb-pagination">
				<span class="tqb-pagination-info">
					Showing <?php echo (($current_page - 1) * $per_page) + 1; ?> - <?php echo min($current_page * $per_page, $total_count); ?> of <?php echo $total_count; ?>
				</span>
				<div class="tqb-pagination-links">
					<?php if ($current_page > 1) : ?>
						<a href="<?php echo esc_url(tqb_dash_filter_url(array('paged' => $current_page - 1))); ?>" class="button">&larr; Prev</a>
					<?php endif; ?>
					
					<?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) : ?>
						<a href="<?php echo esc_url(tqb_dash_filter_url(array('paged' => $i))); ?>" 
						   class="button <?php echo $i === $current_page ? 'button-primary' : ''; ?>">
							<?php echo $i; ?>
						</a>
					<?php endfor; ?>
					
					<?php if ($current_page < $total_pages) : ?>
						<a href="<?php echo esc_url(tqb_dash_filter_url(array('paged' => $current_page + 1))); ?>" class="button">Next &rarr;</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<!-- View Details Modal -->
<div class="tqb-modal-overlay" id="tqb-view-overlay"></div>
<div class="tqb-modal tqb-modal-lg" id="tqb-view-modal">
	<div class="tqb-modal-header">
		<h2><span class="dashicons dashicons-clipboard"></span> Submission Details <span id="tqb-modal-id"></span></h2>
		<div class="tqb-modal-header-actions">
			<button type="button" class="tqb-btn tqb-btn-secondary" id="tqb-modal-email-btn">
				<span class="dashicons dashicons-email-alt"></span>
				Send Email
			</button>
			<button type="button" class="tqb-modal-close" id="tqb-modal-close">&times;</button>
		</div>
	</div>
	<div class="tqb-modal-body" id="tqb-modal-body">
		<!-- Content loaded via AJAX -->
		<div class="tqb-loading">
			<span class="dashicons dashicons-update tqb-spin"></span>
			Loading...
		</div>
	</div>
</div>

<!-- Email Modal -->
<div class="tqb-modal-overlay" id="tqb-email-overlay"></div>
<div class="tqb-modal tqb-modal-md" id="tqb-email-modal">
	<div class="tqb-modal-header">
		<h2><span class="dashicons dashicons-email-alt"></span> Send Email</h2>
		<button type="button" class="tqb-modal-close" id="tqb-email-modal-close">&times;</button>
	</div>
	<div class="tqb-modal-body">
		<form id="tqb-email-form">
			<input type="hidden" name="action" value="tqb_send_email" />
			<input type="hidden" name="submission_ids" id="tqb-email-ids" value="" />
			
			<div class="tqb-form-group">
				<label>To</label>
				<input type="email" name="to_email" id="tqb-email-to" class="regular-text" required />
			</div>
			
			<div class="tqb-form-group">
				<label>Subject</label>
				<input type="text" name="subject" id="tqb-email-subject" class="regular-text" required />
			</div>
			
			<div class="tqb-form-group">
				<label>Template</label>
				<select name="template" id="tqb-email-template" class="regular-text">
					<option value="">-- Select Template --</option>
					<option value="quote_confirmation">Quote Confirmation</option>
					<option value="follow_up">Follow Up</option>
					<option value="custom_quote">Custom Quote Required</option>
					<option value="thank_you">Thank You</option>
					<option value="custom">Custom Message</option>
				</select>
			</div>
			
			<div class="tqb-form-group">
				<label>Message</label>
				<textarea name="message" id="tqb-email-message" rows="10" class="large-text" required></textarea>
				<p class="description">Use placeholders: {name}, {total}, {quote_type}, {email}, {phone}</p>
			</div>
			
			<div class="tqb-form-actions">
				<button type="submit" class="tqb-btn tqb-btn-primary">
					<span class="dashicons dashicons-email-alt"></span>
					Send Email
				</button>
			</div>
		</form>
	</div>
</div>

<style>
/* ===== Professional Dashboard Styles ===== */
.tqb-dashboard {
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
	padding: 20px;
	max-width: 1600px;
}

/* Header */
.tqb-dash-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 24px;
}
.tqb-dash-title h1 {
	margin: 0 0 4px 0;
	font-size: 24px;
	font-weight: 600;
	color: #1e293b;
}
.tqb-dash-subtitle {
	margin: 0;
	color: #64748b;
	font-size: 14px;
}
.tqb-dash-actions {
	display: flex;
	gap: 8px;
}

/* Stats Grid */
.tqb-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 16px;
	margin-bottom: 24px;
}
.tqb-stat-card {
	background: #fff;
	border-radius: 12px;
	padding: 20px;
	display: flex;
	align-items: center;
	gap: 16px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.tqb-stat-icon {
	width: 48px;
	height: 48px;
	border-radius: 12px;
	display: flex;
	align-items: center;
	justify-content: center;
}
.tqb-stat-icon .dashicons {
	font-size: 24px;
	width: 24px;
	height: 24px;
}
.tqb-stat-content {
	display: flex;
	flex-direction: column;
}
.tqb-stat-number {
	font-size: 28px;
	font-weight: 700;
	color: #1e293b;
	line-height: 1;
}
.tqb-stat-label {
	font-size: 13px;
	color: #64748b;
	margin-top: 4px;
}

/* Filters Bar */
.tqb-filters-bar {
	background: #fff;
	border-radius: 12px;
	padding: 16px 20px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
	flex-wrap: wrap;
	gap: 16px;
}
.tqb-status-tabs {
	display: flex;
	gap: 4px;
	flex-wrap: wrap;
}
.tqb-status-tab {
	padding: 8px 16px;
	border-radius: 8px;
	text-decoration: none;
	color: #64748b;
	font-size: 14px;
	font-weight: 500;
	transition: all 0.2s;
}
.tqb-status-tab:hover {
	background: #f1f5f9;
	color: #1e293b;
}
.tqb-status-tab.active {
	background: var(--status-color, #3b82f6);
	color: #fff;
}
.tqb-status-tab .tqb-count {
	background: rgba(255,255,255,0.2);
	padding: 2px 6px;
	border-radius: 10px;
	font-size: 12px;
	margin-left: 6px;
}
.tqb-status-tab:not(.active) .tqb-count {
	background: #e2e8f0;
	color: #64748b;
}
.tqb-filters-right {
	display: flex;
	gap: 12px;
	align-items: center;
}

/* Bulk Actions Bar */
.tqb-bulk-bar {
	background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
	color: #fff;
	border-radius: 12px;
	padding: 12px 20px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16px;
}
.tqb-bulk-selected {
	font-weight: 600;
}
.tqb-bulk-actions {
	display: flex;
	gap: 8px;
}

/* Table */
.tqb-table-wrapper {
	background: #fff;
	border-radius: 12px;
	overflow: hidden;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.tqb-table {
	width: 100%;
	border-collapse: collapse;
}
.tqb-table th {
	background: #f8fafc;
	padding: 14px 16px;
	text-align: left;
	font-size: 12px;
	font-weight: 600;
	color: #64748b;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	border-bottom: 1px solid #e2e8f0;
}
.tqb-table td {
	padding: 16px;
	border-bottom: 1px solid #f1f5f9;
	vertical-align: middle;
}
.tqb-table tr:hover {
	background: #f8fafc;
}
.tqb-table tr:last-child td {
	border-bottom: none;
}

/* Contact Cell */
.tqb-contact-cell {
	display: flex;
	flex-direction: column;
	gap: 2px;
}
.tqb-contact-cell strong {
	color: #1e293b;
	font-size: 14px;
}
.tqb-contact-cell a {
	color: #3b82f6;
	font-size: 13px;
	text-decoration: none;
}
.tqb-contact-cell a:hover {
	text-decoration: underline;
}
.tqb-phone {
	color: #64748b;
	font-size: 12px;
}

/* Type Badge */
.tqb-type-badge {
	display: inline-block;
	padding: 4px 10px;
	border-radius: 6px;
	font-size: 12px;
	font-weight: 600;
}
.tqb-type-individual {
	background: #eff6ff;
	color: #3b82f6;
}
.tqb-type-business {
	background: #f5f3ff;
	color: #8b5cf6;
}
.tqb-type-combined {
	background: #fff7ed;
	color: #f97316;
}

/* Status Select */
.tqb-status-select {
	padding: 6px 12px;
	border-radius: 6px;
	border: 1px solid #e2e8f0;
	font-size: 13px;
	font-weight: 500;
	cursor: pointer;
	background: #fff;
}
.tqb-status-select:focus {
	outline: none;
	border-color: #3b82f6;
	box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

/* Date */
.tqb-date {
	color: #64748b;
	font-size: 13px;
}

/* Actions */
.tqb-actions-cell {
	display: flex;
	gap: 4px;
}
.tqb-action-btn {
	width: 32px;
	height: 32px;
	border: none;
	background: transparent;
	border-radius: 6px;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #64748b;
	transition: all 0.2s;
}
.tqb-action-btn:hover {
	background: #f1f5f9;
	color: #1e293b;
}
.tqb-action-btn.tqb-delete-btn:hover {
	background: #fef2f2;
	color: #ef4444;
}

/* Empty State */
.tqb-empty-state {
	background: #fff;
	border-radius: 12px;
	padding: 60px 20px;
	text-align: center;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.tqb-empty-state .dashicons {
	font-size: 48px;
	width: 48px;
	height: 48px;
	color: #cbd5e1;
}
.tqb-empty-state h3 {
	margin: 16px 0 8px;
	color: #1e293b;
}
.tqb-empty-state p {
	margin: 0;
	color: #64748b;
}

/* Pagination */
.tqb-pagination {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-top: 20px;
	padding: 16px;
}
.tqb-pagination-info {
	color: #64748b;
	font-size: 14px;
}
.tqb-pagination-links {
	display: flex;
	gap: 4px;
}

/* Modal */
.tqb-modal-overlay {
	display: none;
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0,0,0,0.5);
	z-index: 100000;
}
.tqb-modal {
	display: none;
	position: fixed;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	background: #fff;
	border-radius: 16px;
	box-shadow: 0 25px 50px rgba(0,0,0,0.25);
	z-index: 100001;
	max-height: 90vh;
	overflow: hidden;
	flex-direction: column;
}
.tqb-modal-md {
	width: 95%;
	max-width: 600px;
}
.tqb-modal-lg {
	width: 95%;
	max-width: 900px;
}
.tqb-modal-header {
	background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
	color: #fff;
	padding: 20px 24px;
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.tqb-modal-header h2 {
	margin: 0;
	font-size: 18px;
	font-weight: 600;
	display: flex;
	align-items: center;
	gap: 10px;
}
.tqb-modal-header h2 .dashicons {
	font-size: 24px;
	width: 24px;
	height: 24px;
}
.tqb-modal-header-actions {
	display: flex;
	gap: 8px;
	align-items: center;
}
.tqb-modal-close {
	background: none;
	border: none;
	color: #fff;
	font-size: 32px;
	cursor: pointer;
	padding: 0;
	line-height: 1;
	opacity: 0.7;
}
.tqb-modal-close:hover {
	opacity: 1;
}
.tqb-modal-body {
	padding: 24px;
	overflow-y: auto;
	flex: 1;
}

/* Form Styles */
.tqb-form-group {
	margin-bottom: 20px;
}
.tqb-form-group label {
	display: block;
	font-weight: 600;
	color: #374151;
	margin-bottom: 8px;
}
.tqb-form-group input,
.tqb-form-group select,
.tqb-form-group textarea {
	width: 100%;
	padding: 10px 14px;
	border: 1px solid #d1d5db;
	border-radius: 8px;
	font-size: 14px;
}
.tqb-form-group input:focus,
.tqb-form-group select:focus,
.tqb-form-group textarea:focus {
	outline: none;
	border-color: #3b82f6;
	box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
.tqb-form-actions {
	padding-top: 16px;
	border-top: 1px solid #e5e7eb;
}

/* Buttons */
.tqb-btn {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 8px 16px;
	border-radius: 8px;
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
	border: none;
	transition: all 0.2s;
}
.tqb-btn-primary {
	background: #3b82f6;
	color: #fff;
}
.tqb-btn-primary:hover {
	background: #2563eb;
}
.tqb-btn-secondary {
	background: #f1f5f9;
	color: #475569;
}
.tqb-btn-secondary:hover {
	background: #e2e8f0;
}
.tqb-btn-danger {
	background: #fef2f2;
	color: #dc2626;
}
.tqb-btn-danger:hover {
	background: #fee2e2;
}
.tqb-btn-icon {
	padding: 8px;
}

/* Select */
.tqb-select {
	padding: 8px 12px;
	border: 1px solid #d1d5db;
	border-radius: 8px;
	font-size: 14px;
	background: #fff;
	cursor: pointer;
}
.tqb-select:focus {
	outline: none;
	border-color: #3b82f6;
}

/* Search */
.tqb-search-form {
	display: flex;
	gap: 8px;
}
.tqb-search-input {
	padding: 8px 12px;
	border: 1px solid #d1d5db;
	border-radius: 8px;
	font-size: 14px;
	min-width: 200px;
}
.tqb-search-input:focus {
	outline: none;
	border-color: #3b82f6;
}

/* Loading */
.tqb-loading {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 40px;
	color: #64748b;
}
.tqb-loading .dashicons {
	font-size: 32px;
	width: 32px;
	height: 32px;
	margin-bottom: 12px;
}
.tqb-spin {
	animation: tqb-spin 1s linear infinite;
}
@keyframes tqb-spin {
	from { transform: rotate(0deg); }
	to { transform: rotate(360deg); }
}

/* Checkbox */
input[type="checkbox"] {
	width: 18px;
	height: 18px;
	cursor: pointer;
}
</style>

<script>
(function($) {
	$(document).ready(function() {
		// Select all checkbox
		$('#tqb-select-all').on('change', function() {
			$('.tqb-row-check').prop('checked', $(this).prop('checked'));
			updateBulkBar();
		});
		
		// Row checkbox
		$('.tqb-row-check').on('change', function() {
			updateBulkBar();
		});
		
		// Update bulk bar
		function updateBulkBar() {
			var count = $('.tqb-row-check:checked').length;
			if (count > 0) {
				$('#tqb-bulk-bar').slideDown();
				$('#tqb-selected-count').text(count);
			} else {
				$('#tqb-bulk-bar').slideUp();
			}
		}
		
		// View details
		$('.tqb-view-btn').on('click', function() {
			var id = $(this).data('id');
			$('#tqb-modal-id').text('#' + id);
			$('#tqb-view-modal').fadeIn();
			$('#tqb-view-overlay').fadeIn();
			
			$.post(tqbAdminData.ajaxUrl, {
				action: 'tqb_get_submission',
				id: id,
				nonce: tqbAdminData.nonce
			}, function(response) {
				if (response.success) {
					$('#tqb-modal-body').html(response.data.html);
				} else {
					$('#tqb-modal-body').html('<p class="tqb-error">Error loading submission.</p>');
				}
			});
		});
		
		// Email modal
		$('.tqb-email-btn, #tqb-bulk-email-btn').on('click', function() {
			var ids = $(this).hasClass('tqb-bulk-email-btn') 
				? $('.tqb-row-check:checked').map(function() { return $(this).val(); }).get().join(',')
				: $(this).data('id');
			
			$('#tqb-email-ids').val(ids);
			$('#tqb-email-modal').fadeIn();
			$('#tqb-email-overlay').fadeIn();
			
			// Pre-fill email if single submission
			if (ids.indexOf(',') === -1) {
				$.post(tqbAdminData.ajaxUrl, {
					action: 'tqb_get_submission_email',
					id: ids,
					nonce: tqbAdminData.nonce
				}, function(response) {
					if (response.success) {
						$('#tqb-email-to').val(response.data.email);
					}
				});
			}
		});
		
		// Email template selection
		$('#tqb-email-template').on('change', function() {
			var template = $(this).val();
			var to = $('#tqb-email-to').val();
			var subject = '';
			var message = '';
			
			switch(template) {
				case 'quote_confirmation':
					subject = 'Your Quote from Tavola Tax';
					message = "Hi {name},\n\nThank you for your quote request. Based on your selections, your estimated total is {total}.\n\nPlease review your submission and let us know if you have any questions.\n\nBest regards,\nTavola Tax Team";
					break;
				case 'follow_up':
					subject = 'Following Up on Your Quote Request';
					message = "Hi {name},\n\nWe wanted to follow up on your quote request from {quote_type}. Have you had any questions or would you like to discuss next steps?\n\nWe're here to help!\n\nBest regards,\nTavola Tax Team";
					break;
				case 'custom_quote':
					subject = 'Your Quote Requires Custom Review';
					message = "Hi {name},\n\nThank you for your submission. After reviewing your details, your situation requires a custom quote.\n\nWe'll be in touch shortly to discuss your specific needs.\n\nBest regards,\nTavola Tax Team";
					break;
				case 'thank_you':
					subject = 'Thank You for Choosing Tavola Tax';
					message = "Hi {name},\n\nThank you for your trust in Tavola Tax. We're excited to work with you!\n\nYour total: {total}\n\nWe'll be in touch soon to get started.\n\nBest regards,\nTavola Tax Team";
					break;
			}
			
			$('#tqb-email-subject').val(subject);
			$('#tqb-email-message').val(message);
		});
		
		// Email form submit
		$('#tqb-email-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var originalText = $btn.html();
			
			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update tqb-spin"></span> Sending...');
			
			$.post(tqbAdminData.ajaxUrl, $form.serialize(), function(response) {
				if (response.success) {
					alert('Email sent successfully!');
					$('#tqb-email-modal').fadeOut();
					$('#tqb-email-overlay').fadeOut();
				} else {
					alert('Error: ' + (response.data || 'Unknown error'));
				}
				$btn.prop('disabled', false).html(originalText);
			});
		});
		
		// Bulk status change
		$('#tqb-bulk-status').on('change', function() {
			var status = $(this).val();
			var ids = $('.tqb-row-check:checked').map(function() { return $(this).val(); }).get();
			
			if (!status || ids.length === 0) return;
			
			if (!confirm('Change status for ' + ids.length + ' submission(s)?')) {
				$(this).val('');
				return;
			}
			
			$.post(tqbAdminData.ajaxUrl, {
				action: 'tqb_bulk_status',
				ids: ids.join(','),
				status: status,
				nonce: tqbAdminData.nonce
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert('Error updating status');
				}
			});
		});
		
		// Bulk delete
		$('#tqb-bulk-delete-btn').on('click', function() {
			var ids = $('.tqb-row-check:checked').map(function() { return $(this).val(); }).get();
			
			if (ids.length === 0) return;
			
			if (!confirm('Delete ' + ids.length + ' submission(s)? This cannot be undone!')) return;
			if (!confirm('Are you absolutely sure?')) return;
			
			$.post(tqbAdminData.ajaxUrl, {
				action: 'tqb_bulk_delete',
				ids: ids.join(','),
				nonce: tqbAdminData.nonce
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert('Error deleting submissions');
				}
			});
		});
		
		// Individual status change
		$('.tqb-status-select').on('change', function() {
			var id = $(this).data('id');
			var status = $(this).val();
			
			$.post(tqbAdminData.ajaxUrl, {
				action: 'tqb_update_status',
				id: id,
				status: status,
				nonce: tqbAdminData.nonce
			}, function(response) {
				if (response.success) {
					$(this).addClass('tqb-updated');
					setTimeout(() => $(this).removeClass('tqb-updated'), 1000);
				}
			}.bind(this));
		});
		
		// Individual delete
		$('.tqb-delete-btn').on('click', function() {
			var id = $(this).data('id');
			
			if (!confirm('Delete submission #' + id + '? This cannot be undone!')) return;
			
			$.post(tqbAdminData.ajaxUrl, {
				action: 'tqb_delete_submission',
				id: id,
				nonce: tqbAdminData.nonce
			}, function(response) {
				if (response.success) {
					$('tr[data-id="' + id + '"]').fadeOut(function() { $(this).remove(); });
				} else {
					alert('Error deleting submission');
				}
			});
		});
		
		// Close modals
		$('#tqb-modal-close, #tqb-view-overlay').on('click', function() {
			$('#tqb-view-modal').fadeOut();
			$('#tqb-view-overlay').fadeOut();
		});
		
		$('#tqb-email-modal-close, #tqb-email-overlay').on('click', function() {
			$('#tqb-email-modal').fadeOut();
			$('#tqb-email-overlay').fadeOut();
		});
		
		// Escape key closes modals
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape') {
				$('.tqb-modal').fadeOut();
				$('.tqb-modal-overlay').fadeOut();
			}
		});
	});
})(jQuery);
</script>
