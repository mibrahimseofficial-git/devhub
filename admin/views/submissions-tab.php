<?php
/**
 * Submissions list view.
 * Shows all quote submissions with status, filtering, and pagination.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_pages = ceil( $total_count / $per_page );
?>
<h2>Quote Submissions</h2>
<p class="description">View all quote submissions, including completed quotes and abandoned form progress. Use the filters below to find specific submissions.</p>

<div class="tqb-submissions-filters" style="margin-bottom: 20px; display: flex; gap: 15px; align-items: center;">
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
	<table class="widefat striped" style="max-width: 100%;">
		<thead>
			<tr>
				<th style="width: 50px;">ID</th>
				<th style="width: 100px;">Type</th>
				<th style="width: 150px;">Name</th>
				<th style="width: 200px;">Email</th>
				<th style="width: 100px;">Phone</th>
				<th style="width: 100px;">Status</th>
				<th style="width: 100px;">Quote</th>
				<th style="width: 80px;">Step</th>
				<th style="width: 150px;">Created</th>
				<th style="width: 150px;">Emails</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $submissions as $sub ) : ?>
				<tr>
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
						<?php
						$status_class = 'completed' === $sub['status'] ? 'green' : ( 'abandoned' === $sub['status'] ? 'red' : 'yellow' );
						$status_label = 'in_progress' === $sub['status'] ? 'In Progress' : ucfirst( $sub['status'] );
						?>
						<span class="tqb-status tqb-status--<?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_label ); ?>
						</span>
					</td>
					<td>
						<?php if ( $sub['is_custom_quote'] ) : ?>
							<span style="color: #c9a84c; font-weight: 600;">Custom</span>
						<?php elseif ( null !== $sub['calculated_total'] ) : ?>
							$<?php echo number_format( (float) $sub['calculated_total'], 2 ); ?>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td>
						<?php 
						$step_labels = array( '', 'Type', 'Contact', 'Questions', 'Review', 'Complete' );
						echo esc_html( isset( $step_labels[ $sub['last_completed_step'] ] ) ? $step_labels[ $sub['last_completed_step'] ] : $sub['last_completed_step'] );
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
					<td style="font-size: 12px;">
						<?php
						$emails = array();
						if ( $sub['confirmation_email_sent'] ) {
							$emails[] = '✓ Confirm';
						}
						if ( $sub['reminder_email_sent'] ) {
							$emails[] = '✓ Reminder';
						}
						if ( $sub['followup_email_sent'] ) {
							$emails[] = '✓ Follow-up';
						}
						if ( $sub['final_email_sent'] ) {
							$emails[] = '✓ Final';
						}
						echo empty( $emails ) ? '<span style="color: #999;">None</span>' : esc_html( implode( ', ', $emails ) );
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

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
</style>
