<?php
/**
 * Reusable line-items editor with repeater functionality.
 * Expects: $items (array), $quote_type, $heading
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php echo esc_html( $heading ); ?></h2>
<p class="description">
	<strong>Add/Delete:</strong> Use the + button to add new items, trash icon to delete. 
	<strong>Active:</strong> Toggle to show/hide on the public form.
	<strong>Threshold:</strong> For conditional custom quotes (e.g., Crypto with threshold_qty=100, trigger=above: qty > 100 = custom).
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tqb-line-items-form">
	<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_LINE_ITEMS, 'tqb_nonce' ); ?>
	<input type="hidden" name="action" value="tqb_save_line_items" />
	<input type="hidden" name="quote_type" value="<?php echo esc_attr( $quote_type ); ?>" />
	<input type="hidden" name="deleted_items" id="tqb-deleted-items" value="" />

	<div id="tqb-line-items-repeater">
		<?php 
		$row_num = 0;
		foreach ( $items as $item ) : 
			$row_num++;
		?>
			<div class="tqb-line-item-row" data-id="<?php echo esc_attr( $item['id'] ); ?>" data-is-new="0">
				<table class="widefat" style="margin-bottom: 10px;">
					<tr>
						<td style="width: 25%;">
							<label style="font-size: 11px; color: #666;">Label</label>
							<input type="text"
								name="items[<?php echo esc_attr( $item['id'] ); ?>][label]"
								value="<?php echo esc_attr( $item['label'] ); ?>"
								style="width: 100%;" />
							<small style="color:#888;">Key: <code><?php echo esc_html( $item['item_key'] ); ?></code></small>
						</td>
						<td style="width: 20%;">
							<label style="font-size: 11px; color: #666;">Tooltip (hover help)</label>
							<textarea
								name="items[<?php echo esc_attr( $item['id'] ); ?>][tooltip]"
								rows="2"
								style="width: 100%; font-size: 12px;"
								placeholder="Help text shown on hover..."><?php echo esc_textarea( $item['tooltip'] ); ?></textarea>
						</td>
						<td style="width: 8%;">
							<label style="font-size: 11px; color: #666;">Fee ($)</label>
							<input type="number" step="0.01" min="0"
								name="items[<?php echo esc_attr( $item['id'] ); ?>][fee]"
								value="<?php echo esc_attr( $item['fee'] ); ?>"
								style="width: 80px;" />
						</td>
						<td style="width: 10%;">
							<label style="font-size: 11px; color: #666;">Pattern</label>
							<select name="items[<?php echo esc_attr( $item['id'] ); ?>][pricing_pattern]" style="width: 100%;">
								<option value="qty_times_fee" <?php selected( $item['pricing_pattern'], 'qty_times_fee' ); ?>>Qty × Fee</option>
								<option value="flat" <?php selected( $item['pricing_pattern'], 'flat' ); ?>>Flat</option>
								<option value="hardcoded" <?php selected( $item['pricing_pattern'], 'hardcoded' ); ?>>Hardcoded</option>
							</select>
						</td>
						<td style="width: 8%;">
							<label style="font-size: 11px; color: #666;">Hardcoded ($)</label>
							<input type="number" step="0.01" min="0"
								name="items[<?php echo esc_attr( $item['id'] ); ?>][hardcoded_value]"
								value="<?php echo esc_attr( $item['hardcoded_value'] ); ?>"
								style="width: 70px;" />
						</td>
						<td style="width: 10%;">
							<label style="font-size: 11px; color: #666;">Threshold Qty</label>
							<input type="number" step="1" min="0"
								name="items[<?php echo esc_attr( $item['id'] ); ?>][threshold_qty]"
								value="<?php echo esc_attr( $item['threshold_qty'] ); ?>"
								style="width: 70px;"
								placeholder="e.g. 100" />
						</td>
						<td style="width: 10%;">
							<label style="font-size: 11px; color: #666;">Trigger</label>
							<select name="items[<?php echo esc_attr( $item['id'] ); ?>][threshold_trigger]" style="width: 100%;">
								<option value="">None</option>
								<option value="above" <?php selected( $item['threshold_trigger'], 'above' ); ?>>Above</option>
								<option value="below" <?php selected( $item['threshold_trigger'], 'below' ); ?>>Below</option>
							</select>
						</td>
						<td style="width: 5%; text-align: center;">
							<label style="font-size: 11px; color: #666;">Active</label><br />
							<input type="checkbox"
								name="items[<?php echo esc_attr( $item['id'] ); ?>][is_active]"
								value="1" <?php checked( (int) $item['is_active'], 1 ); ?> />
						</td>
						<td style="width: 4%; text-align: center; vertical-align: bottom;">
							<button type="button" class="tqb-delete-item button button-secondary" 
								style="color: #b32d2e; padding: 4px 8px; height: auto;"
								title="Delete this item">
								<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
							</button>
						</td>
					</tr>
				</table>
			</div>
		<?php endforeach; ?>
	</div>

	<p>
		<button type="button" id="tqb-add-item" class="button button-secondary">
			<span class="dashicons dashicons-plus" style="font-size: 14px; width: 14px; height: 14px;"></span>
			Add New Item
		</button>
	</p>

	<p class="submit">
		<button type="submit" class="button button-primary">Save Changes</button>
	</p>
</form>

<!-- Template for new rows -->
<template id="tqb-item-template">
	<div class="tqb-line-item-row" data-id="__NEW_ID__" data-is-new="1">
		<table class="widefat" style="margin-bottom: 10px; background: #fff;">
			<tr>
				<td style="width: 25%;">
					<label style="font-size: 11px; color: #666;">Label *</label>
					<input type="text" name="new_items[__NEW_ID__][label]" value="" style="width: 100%;" placeholder="e.g., Rental Property" />
					<small style="color:#888;">Key: <code>__NEW_KEY__</code></small>
				</td>
				<td style="width: 20%;">
					<label style="font-size: 11px; color: #666;">Tooltip</label>
					<textarea name="new_items[__NEW_ID__][tooltip]" rows="2" style="width: 100%; font-size: 12px;" placeholder="Help text..."></textarea>
				</td>
				<td style="width: 8%;">
					<label style="font-size: 11px; color: #666;">Fee ($)</label>
					<input type="number" step="0.01" min="0" name="new_items[__NEW_ID__][fee]" value="0" style="width: 80px;" />
				</td>
				<td style="width: 10%;">
					<label style="font-size: 11px; color: #666;">Pattern</label>
					<select name="new_items[__NEW_ID__][pricing_pattern]" style="width: 100%;">
						<option value="qty_times_fee">Qty × Fee</option>
						<option value="flat">Flat</option>
						<option value="hardcoded">Hardcoded</option>
					</select>
				</td>
				<td style="width: 8%;">
					<label style="font-size: 11px; color: #666;">Hardcoded ($)</label>
					<input type="number" step="0.01" min="0" name="new_items[__NEW_ID__][hardcoded_value]" value="" style="width: 70px;" />
				</td>
				<td style="width: 10%;">
					<label style="font-size: 11px; color: #666;">Threshold Qty</label>
					<input type="number" step="1" min="0" name="new_items[__NEW_ID__][threshold_qty]" value="" style="width: 70px;" placeholder="e.g. 100" />
				</td>
				<td style="width: 10%;">
					<label style="font-size: 11px; color: #666;">Trigger</label>
					<select name="new_items[__NEW_ID__][threshold_trigger]" style="width: 100%;">
						<option value="">None</option>
						<option value="above">Above</option>
						<option value="below">Below</option>
					</select>
				</td>
				<td style="width: 5%; text-align: center;">
					<label style="font-size: 11px; color: #666;">Active</label><br />
					<input type="checkbox" name="new_items[__NEW_ID__][is_active]" value="1" checked />
				</td>
				<td style="width: 4%; text-align: center; vertical-align: bottom;">
					<button type="button" class="tqb-delete-item button button-secondary" 
						style="color: #b32d2e; padding: 4px 8px; height: auto;"
						title="Delete this item">
						<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
					</button>
				</td>
			</tr>
		</table>
	</div>
</template>

<script>
(function() {
	var newItemCount = 0;
	var deletedItems = [];

	// Add new item
	document.getElementById('tqb-add-item').onclick = function() {
		newItemCount--;
		var template = document.getElementById('tqb-item-template');
		var clone = template.content.cloneNode(true);
		var html = clone.querySelector('div').outerHTML;
		html = html.replace(/__NEW_ID__/g, 'new_' + newItemCount);
		html = html.replace(/__NEW_KEY__/g, 'new_item_' + Math.abs(newItemCount));
		document.getElementById('tqb-line-items-repeater').insertAdjacentHTML('beforeend', html);
	};

	// Delete item (delegation)
	document.getElementById('tqb-line-items-repeater').onclick = function(e) {
		var btn = e.target.closest('.tqb-delete-item');
		if (!btn) return;
		
		var row = btn.closest('.tqb-line-item-row');
		var itemId = row.dataset.id;
		var isNew = row.dataset.isNew === '1';
		
		if (isNew) {
			row.remove();
		} else {
			if (confirm('Delete this item?\n\nThis cannot be undone.')) {
				deletedItems.push(itemId);
				document.getElementById('tqb-deleted-items').value = deletedItems.join(',');
				row.style.opacity = '0.5';
				row.style.pointerEvents = 'none';
				row.querySelectorAll('input, textarea, select').forEach(function(el) {
					el.disabled = true;
				});
			}
		}
	};

	// Mark new rows visually
	document.getElementById('tqb-line-items-form').addEventListener('submit', function() {
		// Re-enable deleted items to be excluded by PHP
	});
})();
</script>

<style>
.tqb-line-item-row {
	padding: 10px;
	border: 1px solid #ddd;
	border-radius: 4px;
	margin-bottom: 10px;
	background: #f9f9f9;
}
.tqb-line-item-row table {
	background: transparent !important;
	border: none !important;
}
.tqb-line-item-row table td {
	border: none !important;
	padding: 5px 8px !important;
	vertical-align: top;
}
#tqb-add-item {
	margin-top: 10px;
}
</style>
