<?php
/**
 * Reusable line-items editor. Expects these variables to be set by the
 * including file: $items (array of line item rows), $quote_type
 * ('individual' or 'business'), $heading (string).
 *
 * Task 2 update: Now supports:
 * - Add new items
 * - Delete items (with confirmation)
 * - Reorder items (up/down buttons)
 * - Multi-condition thresholds (repeater UI)
 * - Reveal followup toggle
 *
 * Not accessed directly — always included from TQB_Admin.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Render a single condition row HTML for the multi-condition threshold builder.
 *
 * @param int   $item_id      The line item ID.
 * @param int   $cond_index   The condition index (0-based).
 * @param array $condition    The condition data array, or null for new row.
 *
 * @return string HTML string for the condition row.
 */
function tqb_render_threshold_condition_row( $item_id, $cond_index, $condition = null ) {
$type     = isset( $condition['type'] ) ? $condition['type'] : 'qty';
$operator = isset( $condition['operator'] ) ? $condition['operator'] : 'above';
$value    = isset( $condition['value'] ) ? $condition['value'] : '';

$base_name = 'items[' . $item_id . '][threshold_conditions][' . $cond_index . ']';
$row_id    = 'tqb-cond-' . $item_id . '-' . $cond_index;

ob_start();
?>
<div class="tqb-threshold-condition-row">
<div class="tqb-condition-field">
<label class="tqb-field-label" for="<?php echo esc_attr( $row_id ); ?>-type">Type</label>
<select name="<?php echo esc_attr( $base_name ); ?>[type]" id="<?php echo esc_attr( $row_id ); ?>-type" class="tqb-input tqb-cond-type">
<option value="qty" <?php selected( $type, 'qty' ); ?>>Quantity</option>
<option value="dollar_value" <?php selected( $type, 'dollar_value' ); ?>>Total value ($)</option>
</select>
</div>
<div class="tqb-condition-field">
<label class="tqb-field-label" for="<?php echo esc_attr( $row_id ); ?>-operator">Operator</label>
<select name="<?php echo esc_attr( $base_name ); ?>[operator]" id="<?php echo esc_attr( $row_id ); ?>-operator" class="tqb-input tqb-cond-operator">
<option value="above" <?php selected( $operator, 'above' ); ?>>Above (&gt;)</option>
<option value="below" <?php selected( $operator, 'below' ); ?>>Below (&lt;)</option>
</select>
</div>
<div class="tqb-condition-field">
<label class="tqb-field-label" for="<?php echo esc_attr( $row_id ); ?>-value">Value</label>
<input type="number" step="1" min="0"
name="<?php echo esc_attr( $base_name ); ?>[value]"
id="<?php echo esc_attr( $row_id ); ?>-value"
value="<?php echo esc_attr( $value ); ?>"
placeholder="e.g., 100"
class="tqb-input tqb-cond-value" />
</div>
</div>
<?php
return ob_get_clean();
}

?>

<div class="tqb-card">
	<div class="tqb-card-header">
		<h2>
			<span class="dashicons dashicons-list-view"></span>
			<?php echo esc_html( $heading ); ?>
		</h2>
	</div>
	<div class="tqb-card-body">
		<div class="tqb-alert tqb-alert-info">
			<span class="dashicons dashicons-info"></span>
			<div>
				<strong>How to use:</strong> Toggle "Active" to show/hide items on the public form. Edit the <strong>Label</strong> to change what users see, and add <strong>Tooltip</strong> text for help that appears on hover.
				<br /><br />
				<strong>Threshold:</strong> Use this to create conditional custom quotes. For example, for Crypto with threshold qty=100 and operator=above: if the user enters a quantity greater than 100, it triggers a custom quote instead of calculating the fee. Multiple conditions can be combined with AND/OR logic.
				<br /><br />
				<strong>Reveal Qty:</strong> If enabled, the quantity input only appears after the user checks "Yes" — creating a cleaner, progressive form.
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tqb-line-items-form">
			<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_LINE_ITEMS, 'tqb_nonce' ); ?>
			<input type="hidden" name="action" value="tqb_save_line_items" />
			<input type="hidden" name="quote_type" value="<?php echo esc_attr( $quote_type ); ?>" />

			<div class="tqb-table-wrapper">
				<table class="tqb-input-table tqb-line-items-table">
					<thead>
						<tr>
							<th style="width: 5%;">Order</th>
							<th style="width: 15%;">Filing Status</th>
							<th style="width: 27%;">Label & Tooltip</th>
							<th style="width: 9%;">Fee ($)</th>
							<th style="width: 12%;">Pattern</th>
							<th style="width: 20%;">Threshold</th>
							<th style="width: 8%;">Reveal Qty</th>
							<th style="width: 8%;">Active</th>
							<th style="width: 6%;">Action</th>
							<th style="width: 12%;">Internal Info</th>
						</tr>
					</thead>
					<tbody class="tqb-line-items-tbody">
						<?php foreach ( $items as $index => $item ) : ?>
							<tr class="tqb-line-item-row" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" data-sort-order="<?php echo esc_attr( $item['sort_order'] ); ?>">
								<!-- Order/Reorder -->
								<td class="tqb-order-column">
									<div class="tqb-order-buttons">
										<button type="button" class="tqb-btn tqb-btn-ghost tqb-btn-sm tqb-btn-move-up" 
											onclick="tqbMoveItemUp(event, <?php echo esc_attr( $item['id'] ); ?>)"
											title="Move up">
											<span class="dashicons dashicons-arrow-up" style="font-size: 14px;"></span>
										</button>
										<button type="button" class="tqb-btn tqb-btn-ghost tqb-btn-sm tqb-btn-move-down" 
											onclick="tqbMoveItemDown(event, <?php echo esc_attr( $item['id'] ); ?>)"
											title="Move down">
											<span class="dashicons dashicons-arrow-down" style="font-size: 14px;"></span>
										</button>
									</div>
								</td>

								<!-- Filing Status Filter -->
								<td class="tqb-filing-status-cell">
									<select name="items[<?php echo esc_attr( $item['id'] ); ?>][filing_status]" class="tqb-input" style="width: 100%;">
										<option value="">All Filing Statuses</option>
										<option value="single" <?php selected( $item['filing_status'], 'single' ); ?>>Single Only</option>
										<option value="mfj" <?php selected( $item['filing_status'], 'mfj' ); ?>>MFJ Only</option>
										<option value="mfs" <?php selected( $item['filing_status'], 'mfs' ); ?>>MFS Only</option>
										<option value="hoh" <?php selected( $item['filing_status'], 'hoh' ); ?>>HOH Only</option>
									</select>
								</td>

								<!-- Label & Tooltip (merged, vertical stack) -->
								<td class="tqb-label-tooltip-cell">
									<div class="tqb-label-section">
										<label class="tqb-cell-label">Label</label>
										<input type="text"
											name="items[<?php echo esc_attr( $item['id'] ); ?>][label]"
											value="<?php echo esc_attr( $item['label'] ); ?>"
											class="tqb-input" />
										<code class="tqb-item-key"><?php echo esc_html( $item['item_key'] ); ?></code>
									</div>
									
									<div class="tqb-tooltip-section">
										<label class="tqb-cell-label">Tooltip / Help Text</label>
										<textarea
											name="items[<?php echo esc_attr( $item['id'] ); ?>][tooltip]"
											rows="2"
											placeholder="Optional help text for users..."
											class="tqb-textarea"><?php echo esc_textarea( $item['tooltip'] ); ?></textarea>
									</div>
								</td>

								<!-- Fee -->
								<td>
									<input type="number" step="0.01" min="0"
										name="items[<?php echo esc_attr( $item['id'] ); ?>][fee]"
										value="<?php echo esc_attr( $item['fee'] ); ?>"
										class="tqb-input" />
								</td>

								<!-- Pattern -->
								<td>
									<select name="items[<?php echo esc_attr( $item['id'] ); ?>][pricing_pattern]" class="tqb-input">
										<option value="qty_times_fee" <?php selected( $item['pricing_pattern'], 'qty_times_fee' ); ?>>Qty × Fee</option>
										<option value="flat" <?php selected( $item['pricing_pattern'], 'flat' ); ?>>Flat</option>
									</select>
								</td>

									<!-- Threshold (Multi-Condition Builder) -->
									<!-- Threshold (Multi-Condition Builder) -->
									<td class="tqb-threshold-cell">
									<?php
									$threshold_rules = $item['threshold_rules'] ? json_decode( $item['threshold_rules'], true ) : null;
									$has_threshold   = ! empty( $threshold_rules ) && ! empty( $threshold_rules['conditions'] );
									$conditions      = $has_threshold ? $threshold_rules['conditions'] : array();
									$threshold_logic = isset( $threshold_rules['logic'] ) ? $threshold_rules['logic'] : 'AND';
									$condition_count = count( $conditions );
									?>
									<div class="tqb-threshold-inline" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
									<!-- Mode: None or Custom -->
									<div class="tqb-threshold-mode-row">
									<label class="tqb-threshold-mode-label">
									<input type="radio" name="items[<?php echo esc_attr( $item['id'] ); ?>][threshold_mode]"
									value="none"
									<?php checked( $has_threshold, false ); ?> />
									None
									</label>
									<label class="tqb-threshold-mode-label">
									<input type="radio" name="items[<?php echo esc_attr( $item['id'] ); ?>][threshold_mode]"
									value="custom"
									<?php checked( $has_threshold, true ); ?> />
									Custom
									</label>
									</div>
									
									<!-- Custom Fields (visible when Custom is selected) -->
									<div class="tqb-threshold-fields"<?php echo $has_threshold ? '' : ' style="display: none;"'; ?>>
									<!-- Condition rows container -->
									<div class="tqb-threshold-conditions" id="tqb-conditions-<?php echo esc_attr( $item['id'] ); ?>">
									<?php if ( ! empty( $conditions ) ) : ?>
									<?php foreach ( $conditions as $ci => $cond ) : ?>
									<?php echo tqb_render_threshold_condition_row( $item['id'], $ci, $cond ); ?>
									<?php endforeach; ?>
									<?php else : ?>
									<?php echo tqb_render_threshold_condition_row( $item['id'], 0, null ); ?>
									<?php endif; ?>
									</div>
									
									<!-- Logic toggle (shown only when 2+ conditions exist) -->
									<div class="tqb-threshold-logic-row" id="tqb-logic-row-<?php echo esc_attr( $item['id'] ); ?>"<?php echo $condition_count >= 2 ? '' : ' style="display: none;"'; ?>>
									<label class="tqb-threshold-mode-label">
									<input type="radio" name="items[<?php echo esc_attr( $item['id'] ); ?>][threshold_logic]"
									value="OR"
									<?php checked( $threshold_logic, 'OR' ); ?> />
									Match ANY (OR)
									</label>
									<label class="tqb-threshold-mode-label">
									<input type="radio" name="items[<?php echo esc_attr( $item['id'] ); ?>][threshold_logic]"
									value="AND"
									<?php checked( $threshold_logic, 'AND' ); ?> />
									Match ALL (AND)
									</label>
									</div>
									
									<!-- Add condition button -->
									<button type="button" class="tqb-btn tqb-btn-secondary tqb-btn-add-condition"
									data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
									<span class="dashicons dashicons-plus-alt"></span>
									Add Condition
									</button>
									</div>
									</div>
									</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Add Item Button (Bottom) -->
			<div style="margin-top: 16px; margin-bottom: 24px;">
				<button type="button" class="tqb-btn tqb-btn-secondary tqb-btn-add-item"
					data-quote-type="<?php echo esc_attr( $quote_type ); ?>">
					<span class="dashicons dashicons-plus"></span> Add Item
				</button>
			</div>

			<div class="tqb-submit">
				<button type="submit" class="tqb-btn tqb-btn-primary">
					<span class="dashicons dashicons-saved" style="font-size:18px;"></span>
					Save Changes
				</button>
			</div>
		</form>
	</div>
</div>

<?php if ( $quote_type === 'individual' ) : ?>
<!-- Filing Status Configuration -->
<div class="tqb-card">
	<div class="tqb-card-header">
		<h2>
			<span class="dashicons dashicons-admin-settings"></span>
			Filing Status Configuration
		</h2>
	</div>
	<div class="tqb-card-body">
		<p class="tqb-description">Configure filing status options and pricing for individual tax returns. The surcharge is added to the base price.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_FILING_STATUS, 'tqb_nonce' ); ?>
			<input type="hidden" name="action" value="tqb_save_filing_status" />

			<table class="wp-list-table widefat striped" style="margin-bottom:20px;">
				<thead>
					<tr>
						<th>Filing Status</th>
						<th>Label</th>
						<th>Surcharge</th>
						<th>Total Price</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$filing_statuses = array( 'single', 'mfj', 'mfs', 'hoh' );
					$filing_labels = array(
						'single' => 'Single',
						'mfj'    => 'Married Filing Jointly',
						'mfs'    => 'Married Filing Separately',
						'hoh'    => 'Head of Household'
					);
					$base_price = (float) get_option( 'tqb_individual_base_price', 500 );

					foreach ( $filing_statuses as $status ) {
						$label = get_option( 'tqb_filing_status_label_' . $status, $filing_labels[ $status ] );
						$surcharge = (float) get_option( 'tqb_filing_status_price_' . $status, 0 );
						$total = $base_price + $surcharge;
						?>
						<tr>
							<td><strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></strong></td>
							<td>
								<input type="text" 
									name="tqb_filing_status_label_<?php echo esc_attr( $status ); ?>" 
									value="<?php echo esc_attr( $label ); ?>" 
									class="regular-text" />
							</td>
							<td>
								<div style="display:flex; align-items:center; gap:8px;">
									$<input type="number" 
										name="tqb_filing_status_price_<?php echo esc_attr( $status ); ?>" 
										value="<?php echo esc_attr( $surcharge ); ?>" 
										class="small-text" 
										step="1" style="width:80px;" />
								</div>
							</td>
							<td>
								<strong>$<?php echo number_format( $total, 2 ); ?></strong>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>

			<table class="tqb-form-table">
				<tr>
					<th scope="row"><label for="tqb_individual_base_price">Individual Return Base Price</label></th>
					<td>
						<div style="display:flex; align-items:center; gap:12px;">
							$<input type="number" 
								id="tqb_individual_base_price" 
								name="tqb_individual_base_price" 
								value="<?php echo esc_attr( $base_price ); ?>" 
								step="0.01" 
								min="0" 
								style="width:120px;" />
						</div>
						<p class="tqb-description">This is the starting price for all individual returns, before any filing status surcharge. For example: Single = $500 + $0 = $500, MFJ = $500 + $200 = $700.</p>
					</td>
				</tr>
			</table>

			<div class="tqb-submit">
				<button type="submit" class="tqb-btn tqb-btn-primary">
					<span class="dashicons dashicons-saved" style="font-size:18px;"></span>
					Save Filing Status Settings
				</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<style>
	/* Table Wrapper - Horizontal Scrollbar */
	.tqb-table-wrapper {
		width: 100%;
		overflow-x: auto;
		overflow-y: visible;
		border: 1px solid #ddd;
		border-radius: 4px;
		background: #fff;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
	}

	.tqb-table-wrapper::-webkit-scrollbar {
		height: 8px;
	}

	.tqb-table-wrapper::-webkit-scrollbar-track {
		background: #f5f5f5;
		border-radius: 4px;
	}

	.tqb-table-wrapper::-webkit-scrollbar-thumb {
		background: #d0d0d0;
		border-radius: 4px;
	}

	.tqb-table-wrapper::-webkit-scrollbar-thumb:hover {
		background: #999;
	}

	/* Table Styling */
	.tqb-input-table {
		width: 100%;
		min-width: 1200px;
		border-collapse: collapse;
		background: #fff;
	}

	.tqb-input-table thead {
		background: #f5f5f5;
		border-bottom: 2px solid #ddd;
	}

	.tqb-input-table th {
		padding: 12px 8px;
		text-align: left;
		font-weight: 600;
		font-size: 12px;
		color: #374151;
		white-space: nowrap;
	}

	.tqb-input-table td {
		padding: 12px 8px;
		border-bottom: 1px solid #eee;
		vertical-align: top;
	}

	.tqb-input-table tbody tr:hover {
		background: #fafbfc;
	}

	/* Order Column - Vertical Stack */
	.tqb-order-column {
		text-align: center;
		width: 5%;
		min-width: 60px;
	}

	.tqb-order-buttons {
		display: flex;
		flex-direction: column;
		gap: 4px;
		justify-content: center;
		align-items: center;
	}

	.tqb-order-buttons button {
		width: 32px;
		height: 32px;
		padding: 0;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	/* Button Styles */
	.tqb-btn-sm {
		padding: 4px 8px;
		font-size: 12px;
		line-height: 1;
	}

	.tqb-btn-primary {
		background: #2271b1;
		color: #fff;
		padding: 10px 16px;
		font-weight: 600;
		border: none;
		border-radius: 4px;
		cursor: pointer;
		transition: all 0.2s ease;
	}

	.tqb-btn-primary:hover {
		background: #135e96;
	}

	.tqb-btn-icon-delete {
		background: transparent;
		border: 1px solid #e5e7eb;
		color: #6b7280;
		padding: 6px;
		cursor: pointer;
		border-radius: 3px;
		transition: all 0.2s ease;
		display: inline-flex;
		align-items: center;
		justify-content: center;
	}

	.tqb-btn-icon-delete:hover {
		background: #fee2e2;
		border-color: #dc2626;
		color: #dc2626;
	}

	.tqb-btn-icon-delete .dashicons {
		font-size: 16px;
		width: auto;
		height: auto;
	}

	.tqb-btn-ghost {
		background: transparent;
		border: 1px solid #ddd;
		color: #666;
	}

	.tqb-btn-ghost:hover {
		background: #f5f5f5;
		color: #000;
	}

	/* Input and Textarea styles */
	.tqb-input {
		width: 100%;
		padding: 8px 10px;
		border: 1px solid #ddd;
		border-radius: 3px;
		font-family: inherit;
		font-size: 13px;
		box-sizing: border-box;
	}

	.tqb-input:focus {
		border-color: #2271b1;
		outline: none;
		box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
	}

	.tqb-textarea {
		width: 100%;
		padding: 8px 10px;
		border: 1px solid #ddd;
		border-radius: 3px;
		font-family: inherit;
		font-size: 12px;
		box-sizing: border-box;
		resize: vertical;
		min-height: 60px;
	}

	.tqb-textarea:focus {
		border-color: #2271b1;
		outline: none;
		box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
	}

	/* Label & Tooltip Cell - Vertical Stack */
	.tqb-label-tooltip-cell {
		display: flex;
		flex-direction: column;
		gap: 16px;
		padding: 12px !important;
		background: #fafbfc;
	}

	.tqb-label-section {
		display: flex;
		flex-direction: column;
		gap: 6px;
		padding-bottom: 12px;
		border-bottom: 1px solid #e0e0e0;
	}

	.tqb-tooltip-section {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}

	.tqb-cell-label {
		font-size: 11px;
		font-weight: 600;
		color: #374151;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		margin: 0;
		display: block;
	}

	.tqb-item-key {
		font-family: 'Monaco', 'Courier', monospace;
		font-size: 10px;
		color: #64748b;
		background: #f1f5f9;
		padding: 4px 6px;
		border-radius: 2px;
		display: block;
		word-break: break-all;
	}

	/* ===== INLINE THRESHOLD BUILDER STYLES ===== */

	/* Threshold cell - compact inline layout */
	.tqb-threshold-cell {
		font-size: 11px;
		vertical-align: top;
		padding: 8px !important;
	}

	.tqb-threshold-inline {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	/* Mode radio buttons - compact inline layout */
	.tqb-threshold-mode-row {
		display: flex;
		gap: 12px;
		margin-bottom: 4px;
	}

	.tqb-threshold-mode-label {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		font-size: 11px;
		cursor: pointer;
		white-space: nowrap;
	}

	.tqb-threshold-mode-label input[type="radio"] {
		margin: 0;
		cursor: pointer;
	}

	/* Threshold fields container */
	.tqb-threshold-fields {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	/* Condition rows container */
	.tqb-threshold-conditions {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}

	/* Single condition row - compact 3-column layout */
	.tqb-threshold-condition-row {
		display: grid;
		grid-template-columns: 1fr 1fr 1fr;
		gap: 6px;
		align-items: end;
	}

	/* Condition field */
	.tqb-condition-field {
		display: flex;
		flex-direction: column;
		gap: 2px;
	}

	.tqb-condition-field .tqb-field-label {
		font-size: 10px;
		font-weight: 600;
		color: #555;
		text-transform: uppercase;
		letter-spacing: 0.3px;
		margin: 0;
	}

	.tqb-condition-field .tqb-input {
		padding: 4px 6px;
		font-size: 11px;
		border-radius: 3px;
		height: auto;
	}

	/* Logic toggle row */
	.tqb-threshold-logic-row {
		display: flex;
		gap: 12px;
		padding: 6px 0;
		border-top: 1px dashed #ddd;
		margin-top: 4px;
	}

	/* Add condition button */
	.tqb-btn-add-condition {
		padding: 6px 10px;
		font-size: 11px;
		margin-top: 4px;
	}

	.tqb-btn-add-condition .dashicons {
		font-size: 14px;
		width: 14px;
		height: 14px;
	}


	.tqb-threshold-editor {
		font-size: 12px;
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.tqb-threshold-config {
		position: relative;
		margin-top: 16px;
		padding: 16px;
		border: 1px solid #e5e7eb;
		border-radius: 6px;
		background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
		width: 100%;
		min-width: 0;
		box-sizing: border-box;
		display: flex;
		flex-direction: column;
		gap: 16px;
	}

	.tqb-threshold-logic-section {
		margin-bottom: 24px;
		padding-bottom: 16px;
		border-bottom: 2px solid #e5e7eb;
	}

	.tqb-threshold-logic-section select {
		width: 100%;
		max-width: 400px;
		min-width: 250px;
		padding: 10px 12px;
		font-size: 13px;
		border: 1px solid #d1d5db;
		border-radius: 4px;
		background-color: #fff;
	}

	.tqb-threshold-label {
		display: block;
		font-size: 13px;
		font-weight: 600;
		color: #374151;
		margin-bottom: 8px;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	.tqb-threshold-config select {
		width: 100%;
		max-width: 100%;
		padding: 10px 12px;
		font-size: 13px;
		border: 1px solid #d1d5db;
		border-radius: 4px;
		background-color: #fff;
		color: #111827;
	}

	.tqb-threshold-config select:focus {
		border-color: #2271b1;
		outline: none;
		box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
	}

	/* Conditions container - vertical stack */
	.tqb-threshold-conditions {
		display: flex;
		flex-direction: column !important;
		gap: 12px;
		width: 100%;
		align-items: stretch;
	}

	/* Condition card */
	.tqb-threshold-condition-card {
		display: block !important;
		background: #fff;
		border: 1px solid #d1d5db;
		border-radius: 4px;
		padding: 16px;
		box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
		transition: all 0.2s ease;
		width: 100%;
		min-width: 100%;
		flex-shrink: 0;
	}

	.tqb-threshold-condition-card:hover {
		border-color: #9ca3af;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
	}

	/* Condition header with number and delete */
	.tqb-condition-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 12px;
		padding-bottom: 12px;
		border-bottom: 1px solid #f3f4f6;
	}

	.tqb-condition-number {
		font-size: 12px;
		font-weight: 600;
		color: #6b7280;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	.tqb-btn-icon-only {
		padding: 6px;
		background: transparent;
		border: 1px solid #e5e7eb;
		color: #6b7280;
		cursor: pointer;
		border-radius: 3px;
		transition: all 0.2s ease;
	}

	.tqb-btn-icon-only:hover {
		background: #fee2e2;
		border-color: #dc2626;
		color: #dc2626;
	}

	.tqb-btn-icon-only .dashicons {
		font-size: 16px;
		width: auto;
		height: auto;
	}

	/* Condition fields row - strict 3 column layout, never wraps */
	.tqb-condition-row {
		display: grid;
		grid-template-columns: 1fr 1fr 1fr;
		gap: 12px;
		width: 100%;
		grid-auto-flow: row;
		grid-auto-rows: auto;
		flex-wrap: nowrap;
	}

	/* Individual field - strict sizing */
	.tqb-condition-field {
		display: flex;
		flex-direction: column;
		gap: 6px;
		min-width: 0;
		overflow: hidden;
	}

	.tqb-field-label {
		font-size: 12px;
		font-weight: 600;
		color: #374151;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	.tqb-condition-field input,
	.tqb-condition-field select {
		width: 100%;
		padding: 10px 12px;
		font-size: 13px;
		border: 1px solid #d1d5db;
		border-radius: 4px;
		background-color: #fff;
		color: #111827;
		font-family: inherit;
	}

	.tqb-condition-field input:focus,
	.tqb-condition-field select:focus {
		border-color: #2271b1;
		outline: none;
		box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
	}

	.tqb-condition-field input::placeholder {
		color: #9ca3af;
	}

	/* Add button styling */
	.tqb-btn-secondary {
		background: #fff;
		border: 2px solid #2271b1;
		color: #2271b1;
		padding: 12px 16px;
		font-weight: 600;
		font-size: 13px;
		cursor: pointer;
		border-radius: 4px;
		transition: all 0.2s ease;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 6px;
	}

	.tqb-btn-secondary:hover {
		background: #2271b1;
		color: #fff;
	}

	.tqb-btn-block {
		width: 100%;
	}

	/* Force single column layout for condition fields within cards */
	.tqb-threshold-condition-card .tqb-condition-row {
		grid-template-columns: 1fr !important;
		gap: 16px !important;
	}

	.tqb-threshold-condition {
		position: relative;
	}

	.tqb-threshold-condition input,
	.tqb-threshold-condition select {
		padding: 4px 8px;
		border-radius: 3px;
	}

	/* ===== MODAL STYLES ===== */
	
	/* Modal Overlay Background */
	.tqb-threshold-modal-overlay {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: rgba(0, 0, 0, 0.5);
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 9999;
		padding: 20px;
		box-sizing: border-box;
	}

	.tqb-threshold-modal-overlay.hidden {
		display: none !important;
	}

	/* Modal Dialog Box */
	.tqb-threshold-modal {
		background: #fff;
		border-radius: 8px;
		box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
		width: 100%;
		max-width: 850px;
		max-height: 90vh;
		display: flex;
		flex-direction: column;
		animation: tqbSlideUp 0.3s ease-out;
	}

	@keyframes tqbSlideUp {
		from {
			opacity: 0;
			transform: translateY(20px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	/* Modal Header */
	.tqb-modal-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 20px 24px;
		border-bottom: 2px solid #e5e7eb;
		background: #f9fafb;
		border-radius: 8px 8px 0 0;
		flex-shrink: 0;
	}

	.tqb-modal-header h2 {
		margin: 0;
		font-size: 18px;
		font-weight: 600;
		color: #111827;
		letter-spacing: -0.5px;
	}

	/* Modal Close Button */
	.tqb-modal-close {
		background: transparent;
		border: none;
		font-size: 24px;
		color: #6b7280;
		cursor: pointer;
		padding: 0;
		width: 32px;
		height: 32px;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.2s ease;
		border-radius: 4px;
		flex-shrink: 0;
	}

	.tqb-modal-close:hover {
		color: #dc2626;
		background: #fee2e2;
	}

	.tqb-modal-close .dashicons {
		font-size: 24px;
		width: 24px;
		height: 24px;
		line-height: 1;
	}

	/* Modal Body */
	.tqb-modal-body {
		padding: 24px;
		overflow-y: auto;
		flex: 1;
		background: #fff;
	}

	/* Custom scrollbar for modal body */
	.tqb-modal-body::-webkit-scrollbar {
		width: 8px;
	}

	.tqb-modal-body::-webkit-scrollbar-track {
		background: #f1f5f9;
	}

	.tqb-modal-body::-webkit-scrollbar-thumb {
		background: #cbd5e1;
		border-radius: 4px;
	}

	.tqb-modal-body::-webkit-scrollbar-thumb:hover {
		background: #94a3b8;
	}

	/* Modal Footer */
	.tqb-modal-footer {
		padding: 16px 24px;
		border-top: 1px solid #e5e7eb;
		background: #f9fafb;
		display: flex;
		gap: 12px;
		justify-content: flex-end;
		flex-shrink: 0;
		border-radius: 0 0 8px 8px;
	}

	.tqb-modal-footer button {
		padding: 10px 20px;
		font-size: 13px;
		font-weight: 600;
		border-radius: 4px;
		cursor: pointer;
		border: none;
		transition: all 0.2s ease;
	}

	.tqb-modal-footer .tqb-btn-primary {
		background: #2271b1;
		color: #fff;
	}

	.tqb-modal-footer .tqb-btn-primary:hover {
		background: #135e96;
	}

	.tqb-modal-footer .tqb-btn-secondary {
		background: #fff;
		color: #2271b1;
		border: 1px solid #2271b1;
	}

	.tqb-modal-footer .tqb-btn-secondary:hover {
		background: #f0f7ff;
		border-color: #1e5a96;
	}

	/* Responsive Modal */
	@media (max-width: 768px) {
		.tqb-threshold-modal {
			max-width: 95vw;
			max-height: 95vh;
		}

		.tqb-modal-header {
			padding: 16px 20px;
		}

		.tqb-modal-header h2 {
			font-size: 16px;
		}

		.tqb-modal-body {
			padding: 16px 20px;
		}

		.tqb-modal-footer {
			padding: 12px 20px;
		}
	}

	@media (max-width: 480px) {
		.tqb-threshold-modal-overlay {
			padding: 10px;
		}

		.tqb-threshold-modal {
			max-width: 100vw;
		}

		.tqb-modal-footer {
			flex-direction: column;
		}

		.tqb-modal-footer button {
			width: 100%;
		}
	}

	/* Threshold config inside modal */
	.tqb-modal-body .tqb-threshold-config {
		background: transparent;
		border: none;
		padding: 0;
		margin: 0;
		display: flex;
		flex-direction: column;
		gap: 24px;
	}

	/* Condition cards in modal have more breathing room */
	.tqb-modal-body .tqb-threshold-condition-card {
		padding: 20px;
		margin-bottom: 0;
	}

	/* Modal condition row - perfect 3-column layout */
	.tqb-modal-body .tqb-condition-row {
		grid-template-columns: 1fr 1fr 1fr;
		gap: 20px;
	}

	/* Responsive condition row in modal */
	@media (max-width: 768px) {
		.tqb-modal-body .tqb-condition-row {
			grid-template-columns: 1fr 1fr;
			gap: 16px;
		}
	}

	@media (max-width: 480px) {
		.tqb-modal-body .tqb-condition-row {
			grid-template-columns: 1fr;
			gap: 12px;
		}
	}

</style>

<script>
	/* ===== INLINE THRESHOLD FIELD CONTROL ===== */

	document.addEventListener('DOMContentLoaded', function() {
		// Initialize threshold field visibility based on mode selection
		tqbInitThresholdControls();

		// Delegate click for Add Condition buttons
		document.addEventListener('click', function(e) {
			if (e.target.closest('.tqb-btn-add-condition')) {
				const btn = e.target.closest('.tqb-btn-add-condition');
				const itemId = btn.getAttribute('data-item-id');
				tqbAddThresholdCondition(itemId);
			}
		});
	});

	/**
	 * Initialize threshold controls for all threshold rows on the page.
	 */
	function tqbInitThresholdControls() {
		const thresholdRows = document.querySelectorAll('.tqb-threshold-inline');
		thresholdRows.forEach(row => {
			const itemId = row.getAttribute('data-item-id');
			const modeRadios = row.querySelectorAll('input[type="radio"][name*="threshold_mode"]');
			const fieldsDiv = row.querySelector('.tqb-threshold-fields');

			modeRadios.forEach(radio => {
				radio.addEventListener('change', function() {
					if (fieldsDiv) {
						fieldsDiv.style.display = this.value === 'custom' ? '' : 'none';
					}
				});
			});

			// Update logic toggle visibility based on condition count
			tqbUpdateLogicToggleVisibility(itemId);
		});
	}

	/**
	 * Add a new condition row to the threshold builder.
	 *
	 * @param {string|number} itemId The item ID.
	 */
	function tqbAddThresholdCondition(itemId) {
		const conditionsContainer = document.getElementById('tqb-conditions-' + itemId);
		if (!conditionsContainer) return;

		// Count current conditions
		const currentConditions = conditionsContainer.querySelectorAll('.tqb-threshold-condition-row');
		const newIndex = currentConditions.length;

		// Create new condition row HTML
		const newRow = document.createElement('div');
		newRow.className = 'tqb-threshold-condition-row';
		newRow.innerHTML = \
			'<div class="tqb-condition-field">' +
				'<label class="tqb-field-label" for="tqb-cond-' + itemId + '-' + newIndex + '-type">Type</label>' +
				'<select name="items[' + itemId + '][threshold_conditions][' + newIndex + '][type]" id="tqb-cond-' + itemId + '-' + newIndex + '-type" class="tqb-input tqb-cond-type">' +
					'<option value="qty" selected>Quantity</option>' +
					'<option value="dollar_value">Total value ($)</option>' +
				'</select>' +
			'</div>' +
			'<div class="tqb-condition-field">' +
				'<label class="tqb-field-label" for="tqb-cond-' + itemId + '-' + newIndex + '-operator">Operator</label>' +
				'<select name="items[' + itemId + '][threshold_conditions][' + newIndex + '][operator]" id="tqb-cond-' + itemId + '-' + newIndex + '-operator" class="tqb-input tqb-cond-operator">' +
					'<option value="above" selected>Above (&gt;)</option>' +
					'<option value="below">Below (&lt;)</option>' +
				'</select>' +
			'</div>' +
			'<div class="tqb-condition-field">' +
				'<label class="tqb-field-label" for="tqb-cond-' + itemId + '-' + newIndex + '-value">Value</label>' +
				'<input type="number" step="1" min="0" ' +
					'name="items[' + itemId + '][threshold_conditions][' + newIndex + '][value]" ' +
					'id="tqb-cond-' + itemId + '-' + newIndex + '-value" ' +
					'value="" placeholder="e.g., 100" class="tqb-input tqb-cond-value" />' +
			'</div>';

		conditionsContainer.appendChild(newRow);

		// Update logic toggle visibility
		tqbUpdateLogicToggleVisibility(itemId);
	}

	/**
	 * Update the visibility of the AND/OR logic toggle based on condition count.
	 *
	 * @param {string|number} itemId The item ID.
	 */
	function tqbUpdateLogicToggleVisibility(itemId) {
		const conditionsContainer = document.getElementById('tqb-conditions-' + itemId);
		const logicRow = document.getElementById('tqb-logic-row-' + itemId);

		if (!conditionsContainer || !logicRow) return;

		const conditionCount = conditionsContainer.querySelectorAll('.tqb-threshold-condition-row').length;
		logicRow.style.display = conditionCount >= 2 ? '' : 'none';
	}

	// Expose for use in admin JS (tqb-admin.js)
	window.tqbInitThresholdControls = tqbInitThresholdControls;
	window.tqbAddThresholdCondition = tqbAddThresholdCondition;
	window.tqbUpdateLogicToggleVisibility = tqbUpdateLogicToggleVisibility;

</script>
