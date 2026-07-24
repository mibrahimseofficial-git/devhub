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
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_LINE_ITEMS, 'tqb_nonce' ); ?>
	<input type="hidden" name="action" value="tqb_save_line_items" />
	<input type="hidden" name="quote_type" value="<?php echo esc_attr( $quote_type ); ?>" />

	<table class="widefat striped" style="max-width: 1200px;">
		<thead>
			<tr>
				<th style="width: 25%;">Label (shown to users)</th>
				<th style="width: 20%;">Tooltip (hover help text)</th>
				<th style="width: 10%;">Fee ($)</th>
				<th style="width: 12%;">Pricing Pattern</th>
				<th style="width: 10%;">Hardcoded ($)</th>
				<th style="width: 8%;">Active</th>
				<th style="width: 15%;">Internal Info</th>
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
							style="width: 80px;" />
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
							style="width: 70px;" />
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
