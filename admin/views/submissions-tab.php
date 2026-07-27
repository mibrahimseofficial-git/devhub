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

<div class="tqb-submissions-filters" style="margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
	<div>
		<strong>Status:</strong>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tqb-settings&tab=submissions' . ( $type_filter ? '&type=' . esc_attr( $type_filter ) : '' ) ) ); ?>" 
		   class="button <?php echo empty( $status_filter ) ? 'button-primary' : ''; ?>" style="margin-left: 5px;">All (<?php echo $counts['all']; ?>)</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tqb-settings&tab=submissions&status=completed' . ( $type_filter ? '&type=' . esc_attr( $type_filter ) : '' ) ) ); ?>" 
		   class="button <?php echo 'completed' === $status_filter ? 'button-primary' : ''; ?>">Completed (<?php echo $counts['completed']; ?>)</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tqb-settings&tab=submissions&status=in_progress' . ( $type_filter ? '&type=' . esc_attr( $type_filter ) : '' ) ) ); ?>" 
		   class="button <?php echo 'in_progress' === $status_filter ? 'button-primary' : ''; ?>">In Progress (<?php echo $counts['in_progress']; ?>)</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tqb-settings&tab=submissions&status=abandoned' . ( $type_filter ? '&type=' . esc_attr( $type_filter ) : '' ) ) ); ?>" 
		   class="button <?php echo 'abandoned' === $status_filter ? 'button-primary' : ''; ?>">Abandoned (<?php echo $counts['abandoned']; ?>)</a>
	</div>
	<div style="margin-left: auto;">
		<strong>Type:</strong>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tqb-settings&tab=submissions' . ( $status_filter ? '&status=' . esc_attr( $status_filter ) : '' ) ) ); ?>" 
		   class="button <?php echo empty( $type_filter ) ? 'button-primary' : ''; ?>" style="margin-left: 5px;">All</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tqb-settings&tab=submissions&type=individual' . ( $status_filter ? '&status=' . esc_attr( $status_filter ) : '' ) ) ); ?>" 
		   class="button <?php echo 'individual' === $type_filter ? 'button-primary' : ''; ?>">Individual</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tqb-settings&tab=submissions&type=business' . ( $status_filter ? '&status=' . esc_attr( $status_filter ) : '' ) ) ); ?>" 
		   class="button <?php echo 'business' === $type_filter ? 'button-primary' : ''; ?>">Business</a>
	</div>
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
					<th style="width: 50px;">ID</th>
					<th style="width: 100px;">Type</th>
					<th style="width: 150px;">Name</th>
					<th style="width: 200px;">Email</th>
					<th style="width: 100px;">Phone</th>
					<th style="width: 100px;">Status</th>
					<th style="width: 100px;">Quote</th>
					<th style="width: 80px;">Step</th>
					<th style="width: 150px;">Created</th>
					<th style="width: 80px;">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $submissions as $sub ) : 
					$status_info = tqb_get_display_status( $sub['status'] );
				?>
					<tr>
						<td><input type="checkbox" name="delete_ids[]" value="<?php echo esc_attr( $sub['id'] ); ?>" class="tqb-delete-checkbox" /></td>
						<td><?php echo esc_html( $sub['id'] ); ?></td>
						<td>
							<span class="tqb-badge tqb-badge--<?php echo esc_attr( $sub['quote_type'] ); ?>">
								<?php echo esc_html( ucfirst( $sub['quote_type'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $sub['contact_name'] ); ?></td>
						<td><a href="mailto:<?php echo esc_attr( $sub['contact_email'] ); ?>"><?php echo esc_html( $sub['contact_email'] ); ?></a></td>
						<td><?php echo esc_html( $sub['contact_phone'] ); ?></td>
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
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tqb_delete_submission&id=' . $sub['id'] ), 'tqb_delete_sub_' . $sub['id'] ) ); ?>" 
							   class="button button-small" 
							   style="color: #b32d2e;"
							   onclick="return confirm('Delete this submission? This cannot be undone.');">
								Delete
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div style="margin-top: 15px; display: flex; align-items: center; gap: 10px;">
			<select name="bulk_action" style="padding: 5px;">
				<option value="">Bulk Actions</option>
				<option value="delete">Delete Selected</option>
			</select>
			<button type="submit" class="button">Apply</button>
			<span style="color: #666; font-size: 13px;">
				<?php echo esc_html( $total_count ); ?> <?php echo $total_count === 1 ? 'submission' : 'submissions'; ?>
			</span>
		</div>
	</form>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav" style="margin-top: 15px;">
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo esc_html( $total_count ); ?> items</span>
				<span class="pagination-links">
					<?php
					$base_url = admin_url( 'admin.php?page=tqb-settings&tab=submissions' );
					if ( $status_filter ) {
						$base_url .= '&status=' . esc_attr( $status_filter );
					}
					if ( $type_filter ) {
						$base_url .= '&type=' . esc_attr( $type_filter );
					}

					if ( $current_page > 1 ) {
						echo '<a class="prev-page" href="' . esc_url( $base_url . '&paged=' . ( $current_page - 1 ) ) . '">&lsaquo;</a>';
					}
					?>
					<span class="paging-input">
						Page <?php echo esc_html( $current_page ); ?> of <?php echo esc_html( $total_pages ); ?>
					</span>
					<?php if ( $current_page < $total_pages ) : ?>
						<a class="next-page" href="<?php echo esc_url( $base_url . '&paged=' . ( $current_page + 1 ) ); ?>">&rsaquo;</a>
					<?php endif; ?>
				</span>
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
</style>
