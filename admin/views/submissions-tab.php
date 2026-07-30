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
<li style="margin-top:8px;"><strong>Progress column:</strong> Shows the last step the user completed (1=Type, 2=Contact, 3=Questions, 4=Review, 5=Complete)</li>
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
					<th style="width: 90px;">Progress</th>
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
							<button type="button" class="button button-small tqb-view-btn" data-id="<?php echo esc_attr( $sub['id'] ); ?>">View</button>
<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tqb_delete_submission&id=' . $sub['id'] ), 'tqb_delete_sub_' . $sub['id'] ) ); ?>" class="button button-small" style="color: #b32d2e;" onclick="return confirm('Delete this submission? This cannot be undone.');">Delete</a>
						</td>
						<td>
							<?php echo esc_html( ! empty( $sub['user_ip'] ) ? $sub['user_ip'] : '-' ); ?>
							<td style="font-size:10px; max-width:150px; overflow:hidden;">
							<?php $a=$sub["answers"]??""; echo "<pre style='margin:0;white-space:pre-wrap;word-break:break-all;'>".esc_html($a)."</pre>"; ?>
							</td>
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
</style>

<!-- View Details Modal -->
<style>
#tqb-modal-overlay-v2{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:99998;}
#tqb-modal-v2{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;width:90%;max-width:850px;max-height:85vh;border-radius:12px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);z-index:99999;overflow:hidden;}
.tqb-modal-header{background:linear-gradient(135deg,#001a44 0%,#002d6e 100%);color:#fff;padding:20px 25px;display:flex;justify-content:space-between;align-items:center;}
.tqb-modal-header h2{margin:0;font-size:18px;font-weight:600;color:#fff;}
.tqb-modal-body{padding:25px;overflow-y:auto;max-height:calc(85vh - 70px);}
.tqb-section{margin-bottom:25px;}
.tqb-section-title{font-size:13px;font-weight:600;color:#001a44;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid #DCE6EE;}
.tqb-info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;}
.tqb-info-item{background:#F9FCFF;padding:12px 15px;border-radius:8px;border-left:3px solid #001a44;}
.tqb-info-item.full-width,.tqb-info-item.wide{grid-column:1 / -1;}
.tqb-info-label{font-size:11px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;}
.tqb-info-value{font-size:14px;color:#001a44;font-weight:500;}
.tqb-status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;text-transform:capitalize;}
.tqb-status-completed{background:#E8F5E9;color:#198754;}
.tqb-status-in_progress{background:#fff3cd;color:#856404;}
.tqb-status-abandoned{background:#f8d7da;color:#721c24;}
.tqb-answers-table{width:100%;border-collapse:collapse;font-size:13px;}
.tqb-answers-table th{text-align:left;padding:10px 12px;background:#F9FCFF;border-bottom:2px solid #DCE6EE;font-weight:600;color:#001a44;}
.tqb-answers-table td{padding:10px 12px;border-bottom:1px solid #E5E7EB;}
.tqb-answers-table tr:hover td{background:#F9FCFF;}
.tqb-answer-q{font-weight:500;color:#475569;}
.tqb-answer-a{color:#001a44;}
.tqb-close-btn{background:rgba(255,255,255,0.2);border:none;color:#fff;width:32px;height:32px;border-radius:6px;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.2s;}
.tqb-close-btn:hover{background:rgba(255,255,255,0.3);}
.tqb-loading{text-align:center;padding:40px;color:#475569;}
.tqb-loading-spinner{width:40px;height:40px;border:3px solid #DCE6EE;border-top-color:#001a44;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 15px;}
@keyframes spin{to{transform:rotate(360deg)}}
@media (max-width:600px){.tqb-info-grid{grid-template-columns:1fr;}}
</style>

<div id="tqb-modal-overlay-v2"></div>
<div id="tqb-modal-v2">
	<div class="tqb-modal-header">
		<h2>Submission Details</h2>
		<button class="tqb-close-btn" id="tqb-close-modal">&times;</button>
	</div>
	<div class="tqb-modal-body" id="tqb-modal-content">
		<div class="tqb-loading"><div class="tqb-loading-spinner"></div>Loading...</div>
	</div>
</div>

<script>
jQuery(document).ready(function($){
	var $modal=$('#tqb-modal-v2'),$overlay=$('#tqb-modal-overlay-v2'),$content=$('#tqb-modal-content');

	function fmtDate(s){
		if(!s)return'<span style="color:#999">N/A</span>';
		var d=new Date(s);
		return d.toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'});
	}
	function badge(s){
		var c='completed',l='Completed';
		if(s==='in_progress'){c='in_progress';l='In Progress';}
		else if(s==='abandoned'){c='abandoned';l='Abandoned';}
		return'<span class="tqb-status-badge tqb-status-'+c+'">'+l+'</span>';
	}
	function render(d){
		var ans={};
		try{ans=JSON.parse(d.answers||'{}');}catch(e){}
		var h='';

		// Contact Info
		h+='<div class="tqb-section"><div class="tqb-section-title">Contact Information</div><div class="tqb-info-grid">';
		h+='<div class="tqb-info-item"><div class="tqb-info-label">Name</div><div class="tqb-info-value">'+escHtml(d.contact_name||'-')+'</div></div>';
		h+='<div class="tqb-info-item"><div class="tqb-info-label">Email</div><div class="tqb-info-value"><a href="mailto:'+escAttr(d.contact_email||'')+'">'+escHtml(d.contact_email||'-')+'</a></div></div>';
		h+='<div class="tqb-info-item"><div class="tqb-info-label">Phone</div><div class="tqb-info-value">'+escHtml(d.contact_phone||'-')+'</div></div>';
		h+='<div class="tqb-info-item"><div class="tqb-info-label">Type</div><div class="tqb-info-value">'+ucfirst(d.quote_type||'-')+'</div></div>';
		h+='</div></div>';

		// Quote Details
		h+='<div class="tqb-section"><div class="tqb-section-title">Quote Details</div><div class="tqb-info-grid">';
		h+='<div class="tqb-info-item"><div class="tqb-info-label">Status</div><div class="tqb-info-value">'+badge(d.status)+'</div></div>';
		if(d.is_custom_quote==1){
			h+='<div class="tqb-info-item full-width"><div class="tqb-info-label">Quote Amount</div><div class="tqb-info-value">Custom Quote Required</div></div>';
			if(d.custom_quote_reason)h+='<div class="tqb-info-item full-width"><div class="tqb-info-label">Reason</div><div class="tqb-info-value">'+escHtml(d.custom_quote_reason)+'</div></div>';
		}else{
			h+='<div class="tqb-info-item full-width"><div class="tqb-info-label">Quote Amount</div><div class="tqb-info-value">$'+(d.calculated_total?parseFloat(d.calculated_total).toFixed(2):'0.00')+'</div></div>';
		}
		h+='</div></div>';

		// Form Answers
		h+='<div class="tqb-section"><div class="tqb-section-title">Submitted Answers</div>';
		if(Object.keys(ans).length>0){
			h+='<table class="tqb-answers-table"><thead><tr><th>Question</th><th>Answer</th></tr></thead><tbody>';
			for(var k in ans){
				var v=ans[k];
				if(typeof v==='object')v=JSON.stringify(v);
				var q=k.replace(/_/g,' ').replace(/\b\w/g,function(l){return l.toUpperCase()});
				h+='<tr><td class="tqb-answer-q">'+escHtml(q)+'</td><td class="tqb-answer-a">'+escHtml(String(v))+'</td></tr>';
			}
			h+='</tbody></table>';
		}else{
			h+='<p style="color:#475569;">No parsed answers available.</p>';
			h+='<div style="margin-top:15px;padding:15px;background:#fff3cd;border-radius:4px;font-size:12px;">';
			h+='<strong>Raw Data Debug:</strong><br>';
			h+='<strong>answers field:</strong> <code>'+escHtml(d.answers||'EMPTY/NULL')+'</code><br>';
			h+='<strong>All available fields:</strong> ';
			var fields=[];
			for(var f in d){fields.push(f);}
			h+=fields.join(', ')+'<br>';
			h+='</div>';
		}
		h+='</div>';

		// Timestamps
		h+='<div class="tqb-section"><div class="tqb-section-title">Timestamps</div><div class="tqb-info-grid">';
		h+='<div class="tqb-info-item"><div class="tqb-info-label">Submitted</div><div class="tqb-info-value">'+fmtDate(d.created_at)+'</div></div>';
		h+='<div class="tqb-info-item"><div class="tqb-info-label">Last Updated</div><div class="tqb-info-value">'+fmtDate(d.updated_at)+'</div></div>';
		h+='</div></div>';

		return h;
	}
	function escHtml(s){if(!s)return'';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
	function escAttr(s){if(!s)return'';return String(s).replace(/"/g,'&quot;');}
	function ucfirst(s){if(!s)return'';return s.charAt(0).toUpperCase()+s.slice(1);}

	$('.tqb-view-btn').on('click',function(){
		var id=$(this).data('id');
		$content.html('<div class="tqb-loading"><div class="tqb-loading-spinner"></div>Loading...</div>');
		$modal.fadeIn(200);$overlay.fadeIn(200);$('body').css('overflow','hidden');
		$.post(tqbAdminData.ajaxUrl,{action:'tqb_get_submission',id:id,nonce:tqbAdminData.nonce},function(resp){
			if(resp.success)$content.html(render(resp.data));
			else $content.html('<div style="text-align:center;padding:40px;color:#dc2626"><h3>Error</h3><p>'+(resp.data||'Could not load')+'</p></div>');
		},'json');
	});
	function closeModal(){$modal.fadeOut(200);$overlay.fadeOut(200);$('body').css('overflow','');}
	$('#tqb-close-modal, #tqb-modal-overlay-v2').on('click',closeModal);
	$(document).on('keydown',function(e){if(e.key==='Escape'&&$modal.is(':visible'))closeModal();});
});
</script>
