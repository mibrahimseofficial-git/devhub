<?php
/**
 * Reusable line-items editor. Expects these variables to be set by the
 * including file: $items (array of line item rows), $quote_type
 * ('individual' or 'business'), $heading (string).
 *
 * Not accessed directly — always included from TQB_Admin.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- Page Header -->
<div class="tqb-page-header">
	<h1>
		<span class="dashicons dashicons-list-view"></span>
		<?php echo esc_html( $heading ); ?>
	</h1>
</div>

<div class="tqb-card">
	<div class="tqb-card-body" style="padding: 0;">
		<div class="tqb-alert tqb-alert-info" style="border-radius: 0; border-left: none; border-right: none; border-top: none; margin: 0;">
			<span class="dashicons dashicons-info"></span>
			<div>
				<strong>How to use:</strong> Toggle "Active" to show/hide items on the public form. Edit the <strong>Label</strong> to change what users see, and add <strong>Tooltip</strong> text for help that appears on hover.
				<br /><br />
				<strong>Threshold:</strong> Use this to create conditional custom quotes. For example, for Crypto with threshold_qty=100 and threshold_trigger=above: if the user enters a quantity greater than 100, it triggers a custom quote instead of calculating the fee.
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_LINE_ITEMS, 'tqb_nonce' ); ?>
			<input type="hidden" name="action" value="tqb_save_line_items" />
			<input type="hidden" name="quote_type" value="<?php echo esc_attr( $quote_type ); ?>" />

			<div class="tqb-table-wrapper">
				<table class="tqb-input-table" style="border-radius: 0; box-shadow: none;">
					<thead>
						<tr>
							<th style="width: 18%;">Label</th>
							<th style="width: 16%;">Tooltip</th>
							<th style="width: 8%;">Fee ($)</th>
							<th style="width: 10%;">Pattern</th>
							<th style="width: 8%;">Hardcoded</th>
							<th style="width: 12%;">Threshold</th>
							<th style="width: 8%;">Active</th>
							<th style="width: 20%;">Internal Info</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $item ) : ?>
							<tr>
								<td>
									<input type="text"
										name="items[<?php echo esc_attr( $item['id'] ); ?>][label]"
										value="<?php echo esc_attr( $item['label'] ); ?>" />
									<code style="color:#64748b; font-size:10px; margin-top:4px; display:block;"><?php echo esc_html( $item['item_key'] ); ?></code>
								</td>
								<td>
									<textarea
										name="items[<?php echo esc_attr( $item['id'] ); ?>][tooltip]"
										rows="2"
										placeholder="Help text..."><?php echo esc_textarea( $item['tooltip'] ); ?></textarea>
								</td>
								<td>
									<input type="number" step="0.01" min="0"
										name="items[<?php echo esc_attr( $item['id'] ); ?>][fee]"
										value="<?php echo esc_attr( $item['fee'] ); ?>" />
								</td>
								<td>
									<select name="items[<?php echo esc_attr( $item['id'] ); ?>][pricing_pattern]">
										<option value="qty_times_fee" <?php selected( $item['pricing_pattern'], 'qty_times_fee' ); ?>>Qty × Fee</option>
										<option value="flat" <?php selected( $item['pricing_pattern'], 'flat' ); ?>>Flat</option>
										<option value="hardcoded" <?php selected( $item['pricing_pattern'], 'hardcoded' ); ?>>Hardcoded</option>
									</select>
								</td>
								<td>
									<input type="number" step="0.01" min="0"
										name="items[<?php echo esc_attr( $item['id'] ); ?>][hardcoded_value]"
										value="<?php echo esc_attr( $item['hardcoded_value'] ); ?>" />
								</td>
								<td>
									<div style="display:flex; gap:4px; align-items:center;">
										<input type="number" step="1" min="0"
											name="items[<?php echo esc_attr( $item['id'] ); ?>][threshold_qty]"
											value="<?php echo esc_attr( $item['threshold_qty'] ); ?>"
											placeholder="Qty"
											style="width:60px;" />
										<select name="items[<?php echo esc_attr( $item['id'] ); ?>][threshold_trigger]" style="width:70px;">
											<option value="">—</option>
											<option value="above" <?php selected( $item['threshold_trigger'], 'above' ); ?>>Above</option>
											<option value="below" <?php selected( $item['threshold_trigger'], 'below' ); ?>>Below</option>
										</select>
									</div>
								</td>
								<td style="text-align:center;">
									<input type="checkbox"
										name="items[<?php echo esc_attr( $item['id'] ); ?>][is_active]"
										value="1" <?php checked( (int) $item['is_active'], 1 ); ?> />
								</td>
								<td style="font-size:11px; color:#64748b;">
									<?php if ( ! empty( $item['is_custom_quote_trigger'] ) ) : ?>
										<span style="color:#dc2626; font-weight:600;">Custom quote trigger</span>
									<?php endif; ?>
									<?php if ( ! empty( $item['notes'] ) ) : ?>
										<br /><small><em><?php echo esc_html( $item['notes'] ); ?></em></small>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div style="padding: 20px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
				<button type="submit" class="tqb-btn tqb-btn-primary">
					<span class="dashicons dashicons-saved" style="font-size:18px;"></span>
					Save Changes
				</button>
			</div>
		</form>
	</div>
</div>
