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
	Toggle "Active" to show/hide an item on the public form. "Pricing pattern" controls how the fee is applied:
	<strong>Qty × Fee</strong> multiplies by the quantity entered, <strong>Flat</strong> charges the fee once regardless of quantity,
	<strong>Hardcoded</strong> always charges the fixed amount shown (ignores the Fee column — used for one known pricing quirk, see notes column).
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_LINE_ITEMS, 'tqb_nonce' ); ?>
	<input type="hidden" name="action" value="tqb_save_line_items" />
	<input type="hidden" name="quote_type" value="<?php echo esc_attr( $quote_type ); ?>" />

	<table class="widefat striped" style="max-width: 1100px;">
		<thead>
			<tr>
				<th style="width: 30%;">Item</th>
				<th style="width: 15%;">Fee ($)</th>
				<th style="width: 18%;">Pricing Pattern</th>
				<th style="width: 15%;">Hardcoded Value ($)</th>
				<th style="width: 10%;">Active</th>
				<th style="width: 12%;">Custom-Quote Trigger?</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $item['label'] ); ?></strong><br />
						<code style="color:#888;"><?php echo esc_html( $item['item_key'] ); ?></code>
						<?php if ( ! empty( $item['notes'] ) ) : ?>
							<p class="description"><?php echo esc_html( $item['notes'] ); ?></p>
						<?php endif; ?>
					</td>
					<td>
						<input type="number" step="0.01" min="0"
							name="items[<?php echo esc_attr( $item['id'] ); ?>][fee]"
							value="<?php echo esc_attr( $item['fee'] ); ?>"
							style="width: 100px;" />
					</td>
					<td>
						<select name="items[<?php echo esc_attr( $item['id'] ); ?>][pricing_pattern]">
							<option value="qty_times_fee" <?php selected( $item['pricing_pattern'], 'qty_times_fee' ); ?>>Qty × Fee</option>
							<option value="flat" <?php selected( $item['pricing_pattern'], 'flat' ); ?>>Flat (ignore Qty)</option>
							<option value="hardcoded" <?php selected( $item['pricing_pattern'], 'hardcoded' ); ?>>Hardcoded</option>
						</select>
					</td>
					<td>
						<input type="number" step="0.01" min="0"
							name="items[<?php echo esc_attr( $item['id'] ); ?>][hardcoded_value]"
							value="<?php echo esc_attr( $item['hardcoded_value'] ); ?>"
							style="width: 100px;" />
					</td>
					<td style="text-align:center;">
						<input type="checkbox"
							name="items[<?php echo esc_attr( $item['id'] ); ?>][is_active]"
							value="1" <?php checked( (int) $item['is_active'], 1 ); ?> />
					</td>
					<td style="text-align:center;">
						<?php if ( ! empty( $item['is_custom_quote_trigger'] ) ) : ?>
							<span title="Any 'Yes' on this item routes the prospect to the custom-quote path instead of an auto-price. Not editable from this screen.">Yes 🔒</span>
						<?php else : ?>
							—
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
