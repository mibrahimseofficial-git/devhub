<?php
/**
 * Submissions list view.
 * Shows all quote submissions with status, filtering, and pagination.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_pages = ceil( $total_count / $per_page );

// Determine display status (handle NULL or empty status)
function tqb_get_display_status( $status ) {
	if ( empty( $status ) || $status === 'completed' ) {
		return array( 'label' => 'Completed', 'class' => 'green' );
	} elseif ( $status === 'in_progress' ) {
		return array( 'label' => 'In Progress', 'class' => 'yellow' );
	} elseif ( $status === 'abandoned' ) {
		return array( 'label' => 'Abandoned', 'class' => 'red' );
	} else {
		return array( 'label' => 'Unknown', 'class' => 'gray' );
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
	
	return $current_order === 'ASC' ? ' &#9650;' : ' &#9660;';
}
?>
<h2>Quote Submissions</h2>

<div style="background: #f0f6fc; border: 1px solid #c3d4e7; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
	<strong>Status Guide:</strong>
	<ul style="margin: 10px 0 0 20px; line-height: 1.8;">
		<li><span style="background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 3px;">Completed</span> — User finished and submitted the quote</li>
		<li><span style="background: #fff8e1; color: #f57f17; padding: 2px 8px; border-radius: 3px;">In Progress</span> — Started but not finished (will receive follow-up emails)</li>
		<li><span style="background: #ffebee; color: #c62828; padding: 2px 8px; border-radius: 3px;">Abandoned</span> — Finished all follow-up emails, no further action will be taken</li>
	</ul>
</div>

<!-- Filters & Search Row -->
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
	<!-- Left: Filters -->
	<div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
		<!-- Status Filter -->
		<div style="display: flex; gap: 5px; align-items: center;">
			<span style="font-size: 13px; color: #646970; font-weight: 500;">Status:</span>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'status' => '', 'paged' => 1 ) ) ); ?>" class="button <?php echo empty( $status_filter ) ? 'button-primary' : ''; ?>">All (<?php echo $counts['all']; ?>)</a>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'status' => 'completed', 'paged' => 1 ) ) ); ?>" class="button <?php echo 'completed' === $status_filter ? 'button-primary' : ''; ?>">Completed (<?php echo $counts['completed']; ?>)</a>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'status' => 'in_progress', 'paged' => 1 ) ) ); ?>" class="button <?php echo 'in_progress' === $status_filter ? 'button-primary' : ''; ?>">In Progress (<?php echo $counts['in_progress']; ?>)</a>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'status' => 'abandoned', 'paged' => 1 ) ) ); ?>" class="button <?php echo 'abandoned' === $status_filter ? 'button-primary' : ''; ?>">Abandoned (<?php echo $counts['abandoned']; ?>)</a>
		</div>

		<!-- Type Filter -->
		<div style="display: flex; gap: 5px; align-items: center;">
			<span style="font-size: 13px; color: #646970; font-weight: 500;">Type:</span>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'type' => '', 'paged' => 1 ) ) ); ?>" class="button <?php echo empty( $type_filter ) ? 'button-secondary' : ''; ?>">All</a>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'type' => 'individual', 'paged' => 1 ) ) ); ?>" class="button <?php echo 'individual' === $type_filter ? 'button-primary' : 'button-secondary'; ?>">Individual</a>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 'type' => 'business', 'paged' => 1 ) ) ); ?>" class="button <?php echo 'business' === $type_filter ? 'button-primary' : 'button-secondary'; ?>">Business</a>
		</div>
	</div>

	<!-- Right: Search -->
	<form method="get" action="" style="display: flex; gap: 8px; align-items: center;">
		<input type="hidden" name="page" value="tqb-settings" />
		<input type="hidden" name="tab" value="submissions" />
		<?php if ( $status_filter ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>" />
		<?php endif; ?>
		<?php if ( $type_filter ) : ?>
			<input type="hidden" name="type" value="<?php echo esc_attr( $type_filter ); ?>" />
		<?php endif; ?>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name, email, phone..." style="padding: 6px 10px; border-radius: 4px; border: 1px solid #ddd; min-width: 220px;" />
		<button type="submit" class="button">Search</button>
		<?php if ( ! empty( $search ) ) : ?>
			<a href="<?php echo esc_url( tqb_build_filter_url( array( 's' => '' ) ) ); ?>" class="button button-secondary">Clear</a>
		<?php endif; ?>
	</form>
</div>

<?php if ( empty( $submissions ) ) : ?>
	<p>No submissions found.</p>
<?php else : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'tqb_delete_submissions', 'tqb_delete_nonce' ); ?>
		<input type="hidden" name="action" value="tqb_delete_submissions" />
		
		<table class="widefat striped" style="max-width: 100%;">
			<thead>
				<tr>
					<th style="width: 40px;"><input type="checkbox" id="tqb-select-all" /></th>
					<th style="width: 60px;">
						<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'id', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'id' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>" style="color: inherit; text-decoration: none;">ID<?php echo tqb_get_sort_indicator( 'id' ); ?></a>
					</th>
					<th>
						<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'contact_name', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'contact_name' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>" style="color: inherit; text-decoration: none;">Name<?php echo tqb_get_sort_indicator( 'contact_name' ); ?></a>
					</th>
					<th>
						<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'contact_email', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'contact_email' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>" style="color: inherit; text-decoration: none;">Email<?php echo tqb_get_sort_indicator( 'contact_email' ); ?></a>
					</th>
					<th>
						<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'contact_phone', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'contact_phone' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>" style="color: inherit; text-decoration: none;">Phone<?php echo tqb_get_sort_indicator( 'contact_phone' ); ?></a>
					</th>
					<th style="width: 100px;">
						<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'quote_type', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'quote_type' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>" style="color: inherit; text-decoration: none;">Type<?php echo tqb_get_sort_indicator( 'quote_type' ); ?></a>
					</th>
					<th style="width: 110px;">
						<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'status', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'status' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>" style="color: inherit; text-decoration: none;">Status<?php echo tqb_get_sort_indicator( 'status' ); ?></a>
					</th>
					<th style="width: 100px;">
						<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'calculated_total', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'calculated_total' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>" style="color: inherit; text-decoration: none;">Quote<?php echo tqb_get_sort_indicator( 'calculated_total' ); ?></a>
					</th>
					<th style="width: 80px;">Step</th>
					<th style="width: 160px;">
						<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'created_at', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'created_at' && isset( $_GET['order'] ) && $_GET['order'] === 'DESC' ) ? 'ASC' : 'DESC', 'paged' => 1 ) ) ); ?>" style="color: inherit; text-decoration: none;">Created<?php echo tqb_get_sort_indicator( 'created_at' ); ?></a>
					</th>
					<th style="width: 80px;">Actions</th>
					<th style="width: 120px;">
						<a href="<?php echo esc_url( tqb_build_filter_url( array( 'orderby' => 'user_ip', 'order' => ( isset( $_GET['orderby'] ) && $_GET['orderby'] === 'user_ip' && isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'DESC' : 'ASC', 'paged' => 1 ) ) ); ?>" style="color: inherit; text-decoration: none;">IP Address<?php echo tqb_get_sort_indicator( 'user_ip' ); ?></a>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $submissions as $sub ) : 
					$status_info = tqb_get_display_status( $sub['status'] );
				?>
					<tr>
						<td><input type="checkbox" name="delete_ids[]" value="<?php echo esc_attr( $sub['id'] ); ?>" class="tqb-delete-checkbox" /></td>
						<td><?php echo esc_html( $sub['id'] ); ?></td>
						<td><?php echo esc_html( $sub['contact_name'] ); ?></td>
						<td><a href="mailto:<?php echo esc_attr( $sub['contact_email'] ); ?>"><?php echo esc_html( $sub['contact_email'] ); ?></a></td>
						<td><?php echo esc_html( $sub['contact_phone'] ); ?></td>
						<td>
							<span class="tqb-badge tqb-badge--<?php echo esc_attr( $sub['quote_type'] ); ?>">
								<?php echo esc_html( ucfirst( $sub['quote_type'] ) ); ?>
							</span>
						</td>
						<td>
							<span class="tqb-status tqb-status--<?php echo esc_attr( $status_info['class'] ); ?>">
								<?php echo esc_html( $status_info['label'] ); ?>
							</span>
						</td>
						<td>
							<?php if ( ! empty( $sub['is_custom_quote'] ) ) : ?>
								<span style="color: #c9a84c; font-weight: 600;">Custom</span>
							<?php elseif ( null !== $sub['calculated_total'] && $sub['calculated_total'] !== '' ) : ?>
								$<?php echo number_format( (float) $sub['calculated_total'], 2 ); ?>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<?php 
							$step_labels = array( '', 'Type', 'Contact', 'Questions', 'Review', 'Complete' );
							$step = isset( $sub['last_completed_step'] ) ? (int) $sub['last_completed_step'] : 0;
							echo esc_html( isset( $step_labels[ $step ] ) ? $step_labels[ $step ] : $step );
							?>
						</td>
						<td>
							<?php 
							$chicago_tz = new DateTimeZone( 'America/Chicago' );
							$created = new DateTime( $sub['created_at'], new DateTimeZone( 'UTC' ) );
							$created->setTimezone( $chicago_tz );
							echo esc_html( $created->format( 'M j, Y g:i A' ) . ' CT' );
							?>
						</td>
						<td>
							<button type="button" class="button button-small tqb-view-details" data-id="<?php echo esc_attr( $sub['id'] ); ?>">View</button>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tqb_delete_submission&id=' . $sub['id'] ), 'tqb_delete_sub_' . $sub['id'] ) ); ?>" class="button button-small" style="color: #b32d2e;" onclick="return confirm('Delete this submission? This cannot be undone.');">Delete</a>
						</td>
						<td>
							<?php echo esc_html( ! empty( $sub['user_ip'] ) ? $sub['user_ip'] : '-' ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Bulk Actions & Per Page (Below Table) -->
		<div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
			<!-- Bulk Actions (Left) -->
			<div style="display: flex; align-items: center; gap: 10px;">
				<select name="bulk_action" style="padding: 6px 10px; border-radius: 4px; border: 1px solid #ddd;">
					<option value="">Bulk Actions</option>
					<option value="delete">Delete Selected</option>
				</select>
				<button type="submit" class="button">Apply</button>
				<span style="color: #646970; font-size: 13px;">
					<?php echo esc_html( $total_count ); ?> <?php echo $total_count === 1 ? 'submission' : 'submissions'; ?>
					<?php if ( ! empty( $search ) ) : ?>
						(matching "<?php echo esc_html( $search ); ?>")
					<?php endif; ?>
				</span>
			</div>
			
			<!-- Per Page (Right) -->
			<div style="display: flex; align-items: center; gap: 10px;">
				<span style="color: #646970; font-size: 13px;">
					Showing <strong><?php echo esc_html( $offset + 1 ); ?></strong> to <strong><?php echo esc_html( min( $offset + $per_page, $total_count ) ); ?></strong> of <strong><?php echo esc_html( $total_count ); ?></strong>
				</span>
				<label for="per_page" style="font-size: 13px; color: #646970;">Per page:</label>
				<select id="per_page" name="per_page" onchange="window.location.href=this.value" style="padding: 6px 8px; border-radius: 4px; border: 1px solid #ddd;">
					<option value="<?php echo esc_url( tqb_build_filter_url( array( 'per_page' => 10, 'paged' => 1 ) ) ); ?>" <?php selected( $per_page, 10 ); ?>>10</option>
					<option value="<?php echo esc_url( tqb_build_filter_url( array( 'per_page' => 25, 'paged' => 1 ) ) ); ?>" <?php selected( $per_page, 25 ); ?>>25</option>
					<option value="<?php echo esc_url( tqb_build_filter_url( array( 'per_page' => 50, 'paged' => 1 ) ) ); ?>" <?php selected( $per_page, 50 ); ?>>50</option>
					<option value="<?php echo esc_url( tqb_build_filter_url( array( 'per_page' => 100, 'paged' => 1 ) ) ); ?>" <?php selected( $per_page, 100 ); ?>>100</option>
				</select>
			</div>
		</div>
	</form>

	<?php if ( $total_pages > 1 ) : ?>
	<!-- Pagination (Bottom) -->
	<div style="margin-top: 20px; padding: 15px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px;">
		<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
			<!-- Bulk Actions (Left) -->
			<div style="display: flex; align-items: center; gap: 10px;">
				<span style="color: #646970; font-size: 13px;">
					<?php echo esc_html( $total_count ); ?> <?php echo $total_count === 1 ? 'item' : 'items'; ?>
				</span>
			</div>
			
			<!-- Pagination (Right) -->
			<nav class="pagination-links" style="display: flex; gap: 5px; align-items: center;">
				<?php
				$range = 2;
				$start_page = max( 1, $current_page - $range );
				$end_page = min( $total_pages, $current_page + $range );
				
				if ( $current_page > 1 ) :
					echo '<a href="' . esc_url( tqb_build_filter_url( array( 'paged' => 1 ) ) ) . '" class="button button-sm" style="min-width: 32px; height: 32px; padding: 0 8px; display: flex; align-items: center; justify-content: center;">&laquo;</a>';
					echo '<a href="' . esc_url( tqb_build_filter_url( array( 'paged' => $current_page - 1 ) ) ) . '" class="button button-sm" style="min-width: 32px; height: 32px; padding: 0 8px; display: flex; align-items: center; justify-content: center;">&lsaquo;</a>';
				else :
					echo '<span class="button button-sm" style="min-width: 32px; height: 32px; padding: 0 8px; display: flex; align-items: center; justify-content: center; opacity: 0.5; cursor: not-allowed;">&laquo;</span>';
					echo '<span class="button button-sm" style="min-width: 32px; height: 32px; padding: 0 8px; display: flex; align-items: center; justify-content: center; opacity: 0.5; cursor: not-allowed;">&lsaquo;</span>';
				endif;
				
				if ( $start_page > 1 ) {
					echo '<span style="padding: 0 5px; color: #646970;">...</span>';
				}
				
				for ( $i = $start_page; $i <= $end_page; $i++ ) :
					if ( $i === $current_page ) :
						echo '<span class="button button-sm" style="min-width: 32px; height: 32px; padding: 0 8px; display: flex; align-items: center; justify-content: center; background: #2271b1; color: #fff; border-color: #2271b1;">' . esc_html( $i ) . '</span>';
					else :
						echo '<a href="' . esc_url( tqb_build_filter_url( array( 'paged' => $i ) ) ) . '" class="button button-sm" style="min-width: 32px; height: 32px; padding: 0 8px; display: flex; align-items: center; justify-content: center;">' . esc_html( $i ) . '</a>';
					endif;
				endfor;
				
				if ( $end_page < $total_pages ) {
					echo '<span style="padding: 0 5px; color: #646970;">...</span>';
				}
				
				if ( $current_page < $total_pages ) :
					echo '<a href="' . esc_url( tqb_build_filter_url( array( 'paged' => $current_page + 1 ) ) ) . '" class="button button-sm" style="min-width: 32px; height: 32px; padding: 0 8px; display: flex; align-items: center; justify-content: center;">&rsaquo;</a>';
					echo '<a href="' . esc_url( tqb_build_filter_url( array( 'paged' => $total_pages ) ) ) . '" class="button button-sm" style="min-width: 32px; height: 32px; padding: 0 8px; display: flex; align-items: center; justify-content: center;">&raquo;</a>';
				else :
					echo '<span class="button button-sm" style="min-width: 32px; height: 32px; padding: 0 8px; display: flex; align-items: center; justify-content: center; opacity: 0.5; cursor: not-allowed;">&rsaquo;</span>';
					echo '<span class="button button-sm" style="min-width: 32px; height: 32px; padding: 0 8px; display: flex; align-items: center; justify-content: center; opacity: 0.5; cursor: not-allowed;">&raquo;</span>';
				endif;
				?>
			</nav>
		</div>
	</div>
	<?php endif; ?>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
	$('#tqb-select-all').on('change', function() {
		$('.tqb-delete-checkbox').prop('checked', $(this).prop('checked'));
	});
});
</script>

<style>
.tqb-badge {
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}
.tqb-badge--individual {
	background: #e3f2fd;
	color: #1565c0;
}
.tqb-badge--business {
	background: #f3e5f5;
	color: #7b1fa2;
}
.tqb-status {
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
}
.tqb-status--green {
	background: #e8f5e9;
	color: #2e7d32;
}
.tqb-status--yellow {
	background: #fff8e1;
	color: #f57f17;
}
.tqb-status--red {
	background: #ffebee;
	color: #c62828;
}
.tqb-status--gray {
	background: #f5f5f5;
	color: #666;
}
.widefat thead th {
	background: #f1f1f1;
}
.widefat thead th a {
	font-weight: 600;
}

/* Modal Styles */
.tqb-modal-overlay {
	display: none;
	position: fixed;
	z-index: 100000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0, 0, 0, 0.5);
}
.tqb-modal-overlay.is-visible {
	display: flex;
	align-items: center;
	justify-content: center;
}
.tqb-modal {
	background: #fff;
	border-radius: 8px;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
	max-width: 800px;
	width: 95%;
	max-height: 90vh;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}
.tqb-modal-header {
	background: #1e3a5f;
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
	color: #fff;
}
.tqb-modal-close {
	background: none;
	border: none;
	color: #fff;
	font-size: 28px;
	cursor: pointer;
	padding: 0;
	line-height: 1;
	opacity: 0.8;
}
.tqb-modal-close:hover {
	opacity: 1;
}
.tqb-modal-body {
	padding: 24px;
	overflow-y: auto;
	max-height: calc(90vh - 70px);
}
.tqb-modal-section {
	margin-bottom: 24px;
}
.tqb-modal-section:last-child {
	margin-bottom: 0;
}
.tqb-modal-section h3 {
	margin: 0 0 12px;
	font-size: 14px;
	font-weight: 600;
	color: #1e3a5f;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	border-bottom: 2px solid #c9a84c;
	padding-bottom: 8px;
}
.tqb-modal-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 16px;
}
.tqb-modal-field {
	background: #f8f9fa;
	padding: 12px;
	border-radius: 6px;
	border-left: 3px solid #c9a84c;
}
.tqb-modal-field label {
	display: block;
	font-size: 11px;
	font-weight: 600;
	color: #666;
	text-transform: uppercase;
	margin-bottom: 4px;
}
.tqb-modal-field .value {
	font-size: 14px;
	color: #333;
	font-weight: 500;
}
.tqb-modal-answers table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
}
.tqb-modal-answers th,
.tqb-modal-answers td {
	padding: 10px 12px;
	text-align: left;
	border-bottom: 1px solid #eee;
}
.tqb-modal-answers th {
	background: #f1f1f1;
	font-weight: 600;
	color: #333;
}
.tqb-modal-answers tr:nth-child(even) {
	background: #f8f9fa;
}
.tqb-modal-answers .yes {
	color: #2e7d32;
	font-weight: 600;
}
.tqb-modal-answers .no {
	color: #888;
}
.tqb-modal-loading {
	text-align: center;
	padding: 40px;
	color: #666;
}
.tqb-status-badge {
	display: inline-block;
	padding: 3px 10px;
	border-radius: 4px;
	font-size: 12px;
	font-weight: 600;
}
.tqb-status-badge--completed {
	background: #e8f5e9;
	color: #2e7d32;
}
.tqb-status-badge--in_progress {
	background: #fff8e1;
	color: #f57f17;
}
.tqb-status-badge--abandoned {
	background: #ffebee;
	color: #c62828;
}
.tqb-hubspot-badge {
	display: inline-block;
	padding: 3px 10px;
	border-radius: 4px;
	font-size: 11px;
	font-weight: 600;
}
.tqb-hubspot-badge--synced {
	background: #e8f5e9;
	color: #2e7d32;
}
.tqb-hubspot-badge--pending {
	background: #fff8e1;
	color: #f57f17;
}
.tqb-hubspot-badge--failed {
	background: #ffebee;
	color: #c62828;
}
.tqb-email-status {
	display: flex;
	gap: 20px;
	flex-wrap: wrap;
}
.tqb-email-status-item {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 12px;
}
.tqb-email-status-item .icon {
	font-size: 16px;
}
.tqb-email-status-item.sent .icon {
	color: #2e7d32;
}
.tqb-email-status-item.pending .icon {
	color: #f57f17;
}
</style>

<!-- View Details Modal -->
<div class="tqb-modal-overlay" id="tqb-details-modal">
	<div class="tqb-modal">
		<div class="tqb-modal-header">
			<h2>Submission Details <span id="tqb-modal-id"></span></h2>
			<button type="button" class="tqb-modal-close" aria-label="Close">&times;</button>
		</div>
		<div class="tqb-modal-body" id="tqb-modal-body">
			<div class="tqb-modal-loading">Loading...</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
// Submission data passed from PHP (answers already decoded by PHP)
var submissionsData = <?php echo json_encode(array_values($submissions)); ?>;

// Create lookup by ID
var submissionsById = {};
submissionsData.forEach(function(sub) {
submissionsById[sub.id] = sub;
});

// Open modal
$('.tqb-view-details').on('click', function() {
var id = $(this).data('id');
var sub = submissionsById[id];
if (!sub) return;

$('#tqb-modal-id').text('#' + id);

// Parse answers - already decoded by PHP
var answersHtml = '';
try {
// sub.answers is already an object from PHP
var answersData = sub.answers || {};

// Handle nested structure: answers.answers or direct answers
var answerItems = answersData.answers || answersData;

if (answerItems && typeof answerItems === 'object' && Object.keys(answerItems).length > 0) {
answersHtml = '<table><thead><tr><th>Item</th><th>Selected</th><th>Quantity</th><th>Details</th></tr></thead><tbody>';
for (var key in answerItems) {
if (answerItems.hasOwnProperty(key)) {
var item = answerItems[key];
var selected = item && item.selected ? 'Yes' : 'No';
var selectedClass = item && item.selected ? 'yes' : 'no';
var qty = item && item.qty ? item.qty : (item && item.volume ? item.volume : '-');
var details = item && item.thresholdNote ? item.thresholdNote : '-';
answersHtml += '<tr><td>' + key.replace(/_/g, ' ') + '</td><td class="' + selectedClass + '">' + selected + '</td><td>' + qty + '</td><td>' + details + '</td></tr>';
}
}
answersHtml += '</tbody></table>';
} else {
answersHtml = '<p style="color: #888;">No answers recorded for this submission.</p>';
}
} catch (e) {
answersHtml = '<p style="color: #888;">Unable to parse answers data.</p>';
}

// Handle business data if present
var businessHtml = '';
try {
var answersData = sub.answers || {};
var businesses = answersData.businesses;
if (businesses && businesses.length > 0) {
businessHtml = '<div class="tqb-modal-section"><h3>Business Information</h3>';
businesses.forEach(function(biz, idx) {
businessHtml += '<div style="margin-bottom: 16px; padding: 16px; background: #f8f9fa; border-radius: 6px;">';
businessHtml += '<strong style="color: #7b1fa2;">Business ' + (idx + 1) + '</strong><br/>';
businessHtml += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 10px;">';
businessHtml += '<div><label>Entity Type</label><div>' + (biz.entity_type || '-') + '</div></div>';
businessHtml += '<div><label>Asset Band</label><div>' + (biz.asset_band || '-') + '</div></div>';
businessHtml += '<div><label>Revenue Band</label><div>' + (biz.revenue_band || '-') + '</div></div>';
businessHtml += '</div>';
businessHtml += '</div>';
});
businessHtml += '</div>';
}
} catch (e) {
// No business data
}

// Quote result
var quoteResult = sub.is_custom_quote == 1 ?
'<span style="color: #c9a84c; font-weight: 600;">Custom Quote (' + (sub.custom_quote_reason || 'Required') + ')</span>' :
(sub.calculated_total ? '$' + parseFloat(sub.calculated_total).toLocaleString('en-US', {minimumFractionDigits: 2}) : '-');

// Status badge
var statusClass = sub.status === 'completed' ? 'completed' : (sub.status === 'abandoned' ? 'abandoned' : 'in_progress');
var statusLabel = sub.status === 'completed' ? 'Completed' : (sub.status === 'abandoned' ? 'Abandoned' : 'In Progress');

// HubSpot status
var hubspotHtml = '';
if (sub.hubspot_synced == 1) {
hubspotHtml = '<span class="tqb-hubspot-badge tqb-hubspot-badge--synced">&#10003; Synced</span>';
if (sub.hubspot_deal_id) {
hubspotHtml += ' <small>Deal ID: ' + sub.hubspot_deal_id + '</small>';
}
} else {
hubspotHtml = '<span class="tqb-hubspot-badge tqb-hubspot-badge--pending">&#8987; Not Synced</span>';
}

// Email statuses (default to 0 if column doesn't exist)
var confirmationSent = sub.confirmation_email_sent == 1;
var reminderSent = sub.reminder_email_sent == 1;
var followupSent = sub.followup_email_sent == 1;
var finalSent = sub.final_email_sent == 1;

var emailHtml = '<div class="tqb-email-status">';
emailHtml += '<div class="tqb-email-status-item ' + (confirmationSent ? 'sent' : 'pending') + '">';
emailHtml += '<span class="icon">' + (confirmationSent ? '&#10003;' : '&#9675;') + '</span> Confirmation Email';
emailHtml += '</div>';
emailHtml += '<div class="tqb-email-status-item ' + (reminderSent ? 'sent' : 'pending') + '">';
emailHtml += '<span class="icon">' + (reminderSent ? '&#10003;' : '&#9675;') + '</span> Reminder Email';
emailHtml += '</div>';
emailHtml += '<div class="tqb-email-status-item ' + (followupSent ? 'sent' : 'pending') + '">';
emailHtml += '<span class="icon">' + (followupSent ? '&#10003;' : '&#9675;') + '</span> Follow-up Email';
emailHtml += '</div>';
emailHtml += '<div class="tqb-email-status-item ' + (finalSent ? 'sent' : 'pending') + '">';
emailHtml += '<span class="icon">' + (finalSent ? '&#10003;' : '&#9675;') + '</span> Final Email';
emailHtml += '</div>';
emailHtml += '</div>';

// Timestamps
var createdDate = sub.created_at ? new Date(sub.created_at).toLocaleString('en-US', {timeZone: 'America/Chicago'}) + ' CT' : '-';
var updatedDate = sub.updated_at ? new Date(sub.updated_at).toLocaleString('en-US', {timeZone: 'America/Chicago'}) + ' CT' : '-';

var bodyContent = '';
bodyContent += '<div class="tqb-modal-section"><h3>Contact Information</h3>';
bodyContent += '<div class="tqb-modal-grid">';
bodyContent += '<div class="tqb-modal-field"><label>Full Name</label><div class="value">' + (sub.contact_name || '-') + '</div></div>';
bodyContent += '<div class="tqb-modal-field"><label>Email</label><div class="value"><a href="mailto:' + (sub.contact_email || '') + '">' + (sub.contact_email || '-') + '</a></div></div>';
bodyContent += '<div class="tqb-modal-field"><label>Phone</label><div class="value">' + (sub.contact_phone || '-') + '</div></div>';
bodyContent += '<div class="tqb-modal-field"><label>IP Address</label><div class="value">' + (sub.user_ip || '-') + '</div></div>';
bodyContent += '</div></div>';

bodyContent += '<div class="tqb-modal-section"><h3>Quote Information</h3>';
bodyContent += '<div class="tqb-modal-grid">';
bodyContent += '<div class="tqb-modal-field"><label>Quote Type</label><div class="value"><span class="tqb-badge tqb-badge--' + (sub.quote_type || '') + '">' + ((sub.quote_type || '').charAt(0).toUpperCase() + (sub.quote_type || '').slice(1)) + '</span></div></div>';
bodyContent += '<div class="tqb-modal-field"><label>Status</label><div class="value"><span class="tqb-status-badge tqb-status-badge--' + statusClass + '">' + statusLabel + '</span></div></div>';
bodyContent += '<div class="tqb-modal-field"><label>Quote Result</label><div class="value">' + quoteResult + '</div></div>';
bodyContent += '<div class="tqb-modal-field"><label>HubSpot</label><div class="value">' + hubspotHtml + '</div></div>';
bodyContent += '</div></div>';

bodyContent += businessHtml;

bodyContent += '<div class="tqb-modal-section"><h3>Email Status</h3>';
bodyContent += emailHtml;
bodyContent += '</div>';

bodyContent += '<div class="tqb-modal-section"><h3>Answers</h3>';
bodyContent += '<div class="tqb-modal-answers">' + answersHtml + '</div>';
bodyContent += '</div>';

bodyContent += '<div class="tqb-modal-section"><h3>Timestamps</h3>';
bodyContent += '<div class="tqb-modal-grid">';
bodyContent += '<div class="tqb-modal-field"><label>Created</label><div class="value">' + createdDate + '</div></div>';
bodyContent += '<div class="tqb-modal-field"><label>Last Updated</label><div class="value">' + updatedDate + '</div></div>';
if (sub.reminder_email_sent_at) {
bodyContent += '<div class="tqb-modal-field"><label>Reminder Sent</label><div class="value">' + new Date(sub.reminder_email_sent_at).toLocaleString('en-US', {timeZone: 'America/Chicago'}) + ' CT</div></div>';
}
if (sub.followup_email_sent_at) {
bodyContent += '<div class="tqb-modal-field"><label>Follow-up Sent</label><div class="value">' + new Date(sub.followup_email_sent_at).toLocaleString('en-US', {timeZone: 'America/Chicago'}) + ' CT</div></div>';
}
if (sub.final_email_sent_at) {
bodyContent += '<div class="tqb-modal-field"><label>Final Email Sent</label><div class="value">' + new Date(sub.final_email_sent_at).toLocaleString('en-US', {timeZone: 'America/Chicago'}) + ' CT</div></div>';
}
bodyContent += '</div></div>';

$('#tqb-modal-body').html(bodyContent);
$('#tqb-details-modal').addClass('is-visible');
});

// Close modal
$('.tqb-modal-close, .tqb-modal-overlay').on('click', function(e) {
if (e.target === this) {
$('#tqb-details-modal').removeClass('is-visible');
}
});

// Close on Escape key
$(document).on('keydown', function(e) {
if (e.key === 'Escape') {
$('#tqb-details-modal').removeClass('is-visible');
}
});
});
</script>
