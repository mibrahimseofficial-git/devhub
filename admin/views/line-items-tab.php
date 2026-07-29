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
<h2><?php echo esc_html( $heading ); ?></h2>
<p class="description">
	Toggle "Active" to show/hide an item on the public form. Edit the <strong>Label</strong> to change what users see, and add <strong>Tooltip</strong> text for help that appears on hover.
	<br /><br />
	<strong>Threshold:</strong> Use this to create conditional custom quotes. For example, for Crypto with threshold_qty=100 and threshold_trigger=above: if the user enters a quantity greater than 100, it triggers a custom quote instead of calculating the fee.
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_LINE_ITEMS, 'tqb_nonce' ); ?>
	<input type="hidden" name="action" value="tqb_save_line_items" />
	<input type="hidden" name="quote_type" value="<?php echo esc_attr( $quote_type ); ?>" />

	<table class="widefat striped" style="max-width: 1400px;">
		<thead>
			<tr>
				<th style="width: 22%;">Label (shown to users)</th>
				<th style="width: 18%;">Tooltip (hover help text)</th>
				<th style="width: 8%;">Fee ($)</th>
				<th style="width: 10%;">Pricing Pattern</th>
				<th style="width: 8%;">Hardcoded ($)</th>
				<th style="width: 12%;">Threshold Qty</th>
				<th style="width: 10%;">Threshold Trigger</th>
				<th style="width: 5%;">Active</th>
				<th style="width: 12%;">Internal Info</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td>
						<input type="text"
							name="items[<?php echo esc_attr( $item['id'] ); ?>][label]"
							value="<?php echo esc_attr( $item['label'] ); ?>"
							style="width: 100%;" /><br />
						<code style="color:#888; font-size:11px;"><?php echo esc_html( $item['item_key'] ); ?></code>
					</td>
					<td>
						<textarea
							name="items[<?php echo esc_attr( $item['id'] ); ?>][tooltip]"
							rows="3"
							style="width: 100%; font-size:12px;"
							placeholder="Help text shown on hover..."><?php echo esc_textarea( $item['tooltip'] ); ?></textarea>
					</td>
					<td>
						<input type="number" step="0.01" min="0"
							name="items[<?php echo esc_attr( $item['id'] ); ?>][fee]"
							value="<?php echo esc_attr( $item['fee'] ); ?>"
							style="width: 70px;" />
					</td>
					<td>
						<select name="items[<?php echo esc_attr( $item['id'] ); ?>][pricing_pattern]" style="width: 100px;">
							<option value="qty_times_fee" <?php selected( $item['pricing_pattern'], 'qty_times_fee' ); ?>>Qty × Fee</option>
							<option value="flat" <?php selected( $item['pricing_pattern'], 'flat' ); ?>>Flat</option>
							<option value="hardcoded" <?php selected( $item['pricing_pattern'], 'hardcoded' ); ?>>Hardcoded</option>
						</select>
					</td>
					<td>
						<input type="number" step="0.01" min="0"
							name="items[<?php echo esc_attr( $item['id'] ); ?>][hardcoded_value]"
							value="<?php echo esc_attr( $item['hardcoded_value'] ); ?>"
							style="width: 65px;" />
					</td>
					<td>
						<input type="number" step="1" min="0"
							name="items[<?php echo esc_attr( $item['id'] ); ?>][threshold_qty]"
							value="<?php echo esc_attr( $item['threshold_qty'] ); ?>"
							style="width: 60px;"
							placeholder="e.g. 100" />
						<p style="margin: 4px 0 0; font-size:10px; color:#888;">Leave empty for no threshold</p>
					</td>
					<td>
						<select name="items[<?php echo esc_attr( $item['id'] ); ?>][threshold_trigger]" style="width: 90px;">
							<option value="">—</option>
							<option value="above" <?php selected( $item['threshold_trigger'], 'above' ); ?>>Above</option>
							<option value="below" <?php selected( $item['threshold_trigger'], 'below' ); ?>>Below</option>
						</select>
						<p style="margin: 4px 0 0; font-size:10px; color:#888;">
							<strong>Above:</strong> qty > threshold = custom<br />
							<strong>Below:</strong> qty < threshold = custom
						</p>
					</td>
					<td style="text-align:center;">
						<input type="checkbox"
							name="items[<?php echo esc_attr( $item['id'] ); ?>][is_active]"
							value="1" <?php checked( (int) $item['is_active'], 1 ); ?> />
					</td>
					<td style="font-size:11px; color:#666;">
						<?php if ( ! empty( $item['is_custom_quote_trigger'] ) ) : ?>
							<span style="color:#b32d2e;">Custom quote trigger</span>
						<?php endif; ?>
						<?php if ( ! empty( $item['notes'] ) ) : ?>
							<br /><small><em>Notes: <?php echo esc_html( $item['notes'] ); ?></em></small>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary">Save Changes</button>
	</p>
</form>
