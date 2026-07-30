<?php
/**
 * Business tab: Rate Reference grid (asset bands by entity group + revenue
 * add-ons), followed by the Part B extras line items.
 *
 * Expects: $extra_items, $asset_bands_c_s, $asset_bands_partnership,
 * $revenue_addons — all set by TQB_Admin::render_business_tab().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2>Rate Reference — Asset Bands</h2>
<p class="description">
	Base fee lookup by business size. Add or delete bands as needed.
	Leave price blank to mark it as <strong>Custom</strong> (routes to custom-quote request).
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tqb-rate-bands-form">
	<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_RATE_BANDS, 'tqb_nonce' ); ?>
	<input type="hidden" name="action" value="tqb_save_rate_bands" />
	<input type="hidden" name="deleted_asset_bands" id="tqb-deleted-asset-bands" value="" />
	<input type="hidden" name="deleted_revenue_addons" id="tqb-deleted-revenue-addons" value="" />

	<h3>Asset Bands</h3>
	<div id="tqb-asset-bands-repeater">
		<?php 
		// Merge both arrays for display
		$all_asset_bands = array();
		foreach ( $asset_bands_c_s as $i => $band ) {
			$partnership_band = $asset_bands_partnership[ $i ] ?? null;
			$all_asset_bands[] = array(
				'c_s_band' => $band,
				'partnership_band' => $partnership_band,
				'sort' => $band['sort_order'],
			);
		}
		usort($all_asset_bands, function($a, $b) { return $a['sort'] - $b['sort']; });
		
		foreach ( $all_asset_bands as $row ) :
			$c_band = $row['c_s_band'];
			$p_band = $row['partnership_band'];
		?>
			<div class="tqb-asset-band-row" data-id="<?php echo esc_attr( $c_band['id'] ); ?>" data-is-new="0" data-is-custom="<?php echo esc_attr( $c_band['is_custom'] ); ?>">
				<table class="widefat" style="margin-bottom: 8px;">
					<tr>
						<td style="width: 20%;">
							<label style="font-size: 11px; color: #666;">Band Label</label>
							<input type="text" name="asset_bands[<?php echo esc_attr( $c_band['id'] ); ?>][label]" 
								value="<?php echo esc_attr( $c_band['band_label'] ); ?>" style="width: 100%;" />
						</td>
						<td style="width: 12%;">
							<label style="font-size: 11px; color: #666;">Min ($)</label>
							<input type="number" step="1" min="0" name="asset_bands[<?php echo esc_attr( $c_band['id'] ); ?>][min]" 
								value="<?php echo esc_attr( $c_band['band_min'] ); ?>" style="width: 100px;" />
						</td>
						<td style="width: 12%;">
							<label style="font-size: 11px; color: #666;">Max ($)</label>
							<input type="number" step="1" name="asset_bands[<?php echo esc_attr( $c_band['id'] ); ?>][max]" 
								value="<?php echo esc_attr( $c_band['band_max'] ); ?>" style="width: 100px;" placeholder="Leave empty for unlimited" />
						</td>
						<td style="width: 12%;">
							<label style="font-size: 11px; color: #666;">C-Corp/S-Corp ($)</label>
							<input type="number" step="0.01" min="0" name="asset_bands[<?php echo esc_attr( $c_band['id'] ); ?>][c_s_price]" 
								value="<?php echo esc_attr( $c_band['price'] ); ?>" style="width: 100px;" placeholder="Leave blank for Custom" />
						</td>
						<td style="width: 12%;">
							<label style="font-size: 11px; color: #666;">Partnership ($)</label>
							<input type="number" step="0.01" min="0" name="asset_bands[<?php echo esc_attr( $c_band['id'] ); ?>][p_price]" 
								value="<?php echo esc_attr( $p_band['price'] ?? '' ); ?>" style="width: 100px;" placeholder="Leave blank for Custom" />
						</td>
						<td style="width: 12%;">
							<label style="font-size: 11px; color: #666;">Sort Order</label>
							<input type="number" step="1" min="0" name="asset_bands[<?php echo esc_attr( $c_band['id'] ); ?>][sort_order]" 
								value="<?php echo esc_attr( $c_band['sort_order'] ); ?>" style="width: 70px;" />
						</td>
						<td style="width: 5%; text-align: center; vertical-align: bottom;">
							<button type="button" class="tqb-delete-band button button-secondary" 
								style="color: #b32d2e; padding: 4px 8px; height: auto;" title="Delete this band">
								<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
							</button>
						</td>
					</tr>
				</table>
			</div>
		<?php endforeach; ?>
	</div>

	<p>
		<button type="button" id="tqb-add-asset-band" class="button button-secondary">
			<span class="dashicons dashicons-plus" style="font-size: 14px; width: 14px; height: 14px;"></span>
			Add Asset Band
		</button>
	</p>

	<h3 style="margin-top: 30px;">Revenue Add-On</h3>
	<p class="description">Added on top of the asset-band price when Schedule L is required.</p>

	<div id="tqb-revenue-addons-repeater">
		<?php foreach ( $revenue_addons as $addon ) : ?>
			<div class="tqb-revenue-row" data-id="<?php echo esc_attr( $addon['id'] ); ?>" data-is-new="0">
				<table class="widefat" style="margin-bottom: 8px;">
					<tr>
						<td style="width: 25%;">
							<label style="font-size: 11px; color: #666;">Revenue Band Label</label>
							<input type="text" name="revenue_addons[<?php echo esc_attr( $addon['id'] ); ?>][label]" 
								value="<?php echo esc_attr( $addon['band_label'] ); ?>" style="width: 100%;" />
						</td>
						<td style="width: 12%;">
							<label style="font-size: 11px; color: #666;">Min ($)</label>
							<input type="number" step="1" min="0" name="revenue_addons[<?php echo esc_attr( $addon['id'] ); ?>][min]" 
								value="<?php echo esc_attr( $addon['band_min'] ); ?>" style="width: 100px;" />
						</td>
						<td style="width: 12%;">
							<label style="font-size: 11px; color: #666;">Max ($)</label>
							<input type="number" step="1" name="revenue_addons[<?php echo esc_attr( $addon['id'] ); ?>][max]" 
								value="<?php echo esc_attr( $addon['band_max'] ); ?>" style="width: 100px;" placeholder="Leave empty for unlimited" />
						</td>
						<td style="width: 12%;">
							<label style="font-size: 11px; color: #666;">Add-On Fee ($)</label>
							<input type="number" step="0.01" min="0" name="revenue_addons[<?php echo esc_attr( $addon['id'] ); ?>][price]" 
								value="<?php echo esc_attr( $addon['price'] ); ?>" style="width: 100px;" />
						</td>
						<td style="width: 12%;">
							<label style="font-size: 11px; color: #666;">Sort Order</label>
							<input type="number" step="1" min="0" name="revenue_addons[<?php echo esc_attr( $addon['id'] ); ?>][sort_order]" 
								value="<?php echo esc_attr( $addon['sort_order'] ); ?>" style="width: 70px;" />
						</td>
						<td style="width: 5%; text-align: center; vertical-align: bottom;">
							<button type="button" class="tqb-delete-revenue button button-secondary" 
								style="color: #b32d2e; padding: 4px 8px; height: auto;" title="Delete this band">
								<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
							</button>
						</td>
					</tr>
				</table>
			</div>
		<?php endforeach; ?>
	</div>

	<p>
		<button type="button" id="tqb-add-revenue-addon" class="button button-secondary">
			<span class="dashicons dashicons-plus" style="font-size: 14px; width: 14px; height: 14px;"></span>
			Add Revenue Band
		</button>
	</p>

	<p class="submit">
		<button type="submit" class="button button-primary">Save Rate Reference</button>
	</p>
</form>

<!-- Templates -->
<template id="tqb-asset-band-template">
	<div class="tqb-asset-band-row" data-id="__NEW_ID__" data-is-new="1">
		<table class="widefat" style="margin-bottom: 8px; background: #f0f6ffc4;">
			<tr>
				<td style="width: 20%;">
					<label style="font-size: 11px; color: #666;">Band Label</label>
					<input type="text" name="new_asset_bands[__NEW_ID__][label]" value="" style="width: 100%;" placeholder="e.g. $5M-$10M" />
				</td>
				<td style="width: 12%;">
					<label style="font-size: 11px; color: #666;">Min ($)</label>
					<input type="number" step="1" min="0" name="new_asset_bands[__NEW_ID__][min]" value="" style="width: 100px;" placeholder="e.g. 5000000" />
				</td>
				<td style="width: 12%;">
					<label style="font-size: 11px; color: #666;">Max ($)</label>
					<input type="number" step="1" name="new_asset_bands[__NEW_ID__][max]" value="" style="width: 100px;" placeholder="Leave empty for unlimited" />
				</td>
				<td style="width: 12%;">
					<label style="font-size: 11px; color: #666;">C-Corp/S-Corp ($)</label>
					<input type="number" step="0.01" min="0" name="new_asset_bands[__NEW_ID__][c_s_price]" value="" style="width: 100px;" placeholder="Blank = Custom" />
				</td>
				<td style="width: 12%;">
					<label style="font-size: 11px; color: #666;">Partnership ($)</label>
					<input type="number" step="0.01" min="0" name="new_asset_bands[__NEW_ID__][p_price]" value="" style="width: 100px;" placeholder="Blank = Custom" />
				</td>
				<td style="width: 12%;">
					<label style="font-size: 11px; color: #666;">Sort Order</label>
					<input type="number" step="1" min="0" name="new_asset_bands[__NEW_ID__][sort_order]" value="100" style="width: 70px;" />
				</td>
				<td style="width: 5%; text-align: center; vertical-align: bottom;">
					<button type="button" class="tqb-delete-band button button-secondary" style="color: #b32d2e; padding: 4px 8px; height: auto;">
						<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
					</button>
				</td>
			</tr>
		</table>
	</div>
</template>

<template id="tqb-revenue-addon-template">
	<div class="tqb-revenue-row" data-id="__NEW_ID__" data-is-new="1">
		<table class="widefat" style="margin-bottom: 8px; background: #f0f6ffc4;">
			<tr>
				<td style="width: 25%;">
					<label style="font-size: 11px; color: #666;">Revenue Band Label</label>
					<input type="text" name="new_revenue_addons[__NEW_ID__][label]" value="" style="width: 100%;" placeholder="e.g. Over $1M" />
				</td>
				<td style="width: 12%;">
					<label style="font-size: 11px; color: #666;">Min ($)</label>
					<input type="number" step="1" min="0" name="new_revenue_addons[__NEW_ID__][min]" value="" style="width: 100px;" />
				</td>
				<td style="width: 12%;">
					<label style="font-size: 11px; color: #666;">Max ($)</label>
					<input type="number" step="1" name="new_revenue_addons[__NEW_ID__][max]" value="" style="width: 100px;" placeholder="Leave empty for unlimited" />
				</td>
				<td style="width: 12%;">
					<label style="font-size: 11px; color: #666;">Add-On Fee ($)</label>
					<input type="number" step="0.01" min="0" name="new_revenue_addons[__NEW_ID__][price]" value="0" style="width: 100px;" />
				</td>
				<td style="width: 12%;">
					<label style="font-size: 11px; color: #666;">Sort Order</label>
					<input type="number" step="1" min="0" name="new_revenue_addons[__NEW_ID__][sort_order]" value="100" style="width: 70px;" />
				</td>
				<td style="width: 5%; text-align: center; vertical-align: bottom;">
					<button type="button" class="tqb-delete-revenue button button-secondary" style="color: #b32d2e; padding: 4px 8px; height: auto;">
						<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
					</button>
				</td>
			</tr>
		</table>
	</div>
</template>

<script>
(function() {
	var newAssetBandCount = 0;
	var newRevenueCount = 0;
	var deletedAssetBands = [];
	var deletedRevenue = [];

	// Add asset band
	document.getElementById('tqb-add-asset-band').onclick = function() {
		newAssetBandCount--;
		var template = document.getElementById('tqb-asset-band-template');
		var html = template.innerHTML.replace(/__NEW_ID__/g, 'new_' + newAssetBandCount);
		document.getElementById('tqb-asset-bands-repeater').insertAdjacentHTML('beforeend', html);
	};

	// Add revenue addon
	document.getElementById('tqb-add-revenue-addon').onclick = function() {
		newRevenueCount--;
		var template = document.getElementById('tqb-revenue-addon-template');
		var html = template.innerHTML.replace(/__NEW_ID__/g, 'new_rev_' + newRevenueCount);
		document.getElementById('tqb-revenue-addons-repeater').insertAdjacentHTML('beforeend', html);
	};

	// Delete handlers (delegation)
	document.addEventListener('click', function(e) {
		// Asset band delete
		if (e.target.closest('.tqb-delete-band')) {
			var btn = e.target.closest('.tqb-delete-band');
			var row = btn.closest('.tqb-asset-band-row');
			var isNew = row.dataset.isNew === '1';
			
			if (isNew) {
				row.remove();
			} else {
				if (confirm('Delete this asset band?\n\nThis cannot be undone.')) {
					deletedAssetBands.push(row.dataset.id);
					document.getElementById('tqb-deleted-asset-bands').value = deletedAssetBands.join(',');
					row.style.opacity = '0.5';
					row.style.pointerEvents = 'none';
					row.querySelectorAll('input').forEach(function(el) { el.disabled = true; });
				}
			}
		}
		
		// Revenue addon delete
		if (e.target.closest('.tqb-delete-revenue')) {
			var btn = e.target.closest('.tqb-delete-revenue');
			var row = btn.closest('.tqb-revenue-row');
			var isNew = row.dataset.isNew === '1';
			
			if (isNew) {
				row.remove();
			} else {
				if (confirm('Delete this revenue band?\n\nThis cannot be undone.')) {
					deletedRevenue.push(row.dataset.id);
					document.getElementById('tqb-deleted-revenue-addons').value = deletedRevenue.join(',');
					row.style.opacity = '0.5';
					row.style.pointerEvents = 'none';
					row.querySelectorAll('input').forEach(function(el) { el.disabled = true; });
				}
			}
		}
	});
})();
</script>

<style>
.tqb-asset-band-row, .tqb-revenue-row {
	padding: 8px;
	border: 1px solid #ddd;
	border-radius: 4px;
	margin-bottom: 8px;
	background: #f9f9f9;
}
.tqb-asset-band-row table, .tqb-revenue-row table {
	background: transparent !important;
	border: none !important;
}
.tqb-asset-band-row table td, .tqb-revenue-row table td {
	border: none !important;
	padding: 4px 6px !important;
	vertical-align: top;
}
</style>

<hr style="margin: 40px 0;" />

<?php
$items      = $extra_items;
$quote_type = 'business';
$heading    = 'Business Return — Extras (Part B)';
include TQB_PLUGIN_DIR . 'admin/views/line-items-tab.php';
?>

<hr style="margin: 40px 0;" />

<h2>Schedule L Thresholds (Flat Fee Rule)</h2>
<p class="description">
	When a business meets ALL conditions below, it qualifies for the flat fee instead of the asset-band lookup.
	Edit these values to customize when the $999 flat fee applies.
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width: 700px;">
	<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_SCHEDULE_L, 'tqb_schedule_l_nonce' ); ?>
	<input type="hidden" name="action" value="tqb_save_schedule_l" />

	<table class="widefat" style="margin-bottom: 20px;">
		<thead>
			<tr>
				<th style="width: 20%;">Entity Type</th>
				<th style="width: 25%;">Asset Threshold ($)</th>
				<th style="width: 25%;">Revenue Threshold ($)</th>
				<th style="width: 15%;">Flat Fee ($)</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><strong>C-Corporation</strong></td>
				<td>
					<input type="number" step="1" min="0" name="schedule_l[c_corp][asset_threshold]" 
						value="<?php echo esc_attr( $schedule_l_thresholds['c_corp']['asset_threshold'] ?? 250000 ); ?>" 
						style="width: 120px;" />
				</td>
				<td>
					<input type="number" step="1" min="0" name="schedule_l[c_corp][revenue_threshold]" 
						value="<?php echo esc_attr( $schedule_l_thresholds['c_corp']['revenue_threshold'] ?? 250000 ); ?>" 
						style="width: 120px;" />
				</td>
				<td>
					<input type="number" step="0.01" min="0" name="schedule_l[c_corp][flat_fee]" 
						value="<?php echo esc_attr( $schedule_l_thresholds['c_corp']['flat_fee'] ?? 999 ); ?>" 
						style="width: 100px;" />
				</td>
			</tr>
			<tr>
				<td><strong>S-Corporation</strong></td>
				<td>
					<input type="number" step="1" min="0" name="schedule_l[s_corp][asset_threshold]" 
						value="<?php echo esc_attr( $schedule_l_thresholds['s_corp']['asset_threshold'] ?? 250000 ); ?>" 
						style="width: 120px;" />
				</td>
				<td>
					<input type="number" step="1" min="0" name="schedule_l[s_corp][revenue_threshold]" 
						value="<?php echo esc_attr( $schedule_l_thresholds['s_corp']['revenue_threshold'] ?? 250000 ); ?>" 
						style="width: 120px;" />
				</td>
				<td>
					<input type="number" step="0.01" min="0" name="schedule_l[s_corp][flat_fee]" 
						value="<?php echo esc_attr( $schedule_l_thresholds['s_corp']['flat_fee'] ?? 999 ); ?>" 
						style="width: 100px;" />
				</td>
			</tr>
			<tr>
				<td><strong>Partnership</strong></td>
				<td>
					<input type="number" step="1" min="0" name="schedule_l[partnership][asset_threshold]" 
						value="<?php echo esc_attr( $schedule_l_thresholds['partnership']['asset_threshold'] ?? 1000000 ); ?>" 
						style="width: 120px;" />
				</td>
				<td>
					<input type="number" step="1" min="0" name="schedule_l[partnership][revenue_threshold]" 
						value="<?php echo esc_attr( $schedule_l_thresholds['partnership']['revenue_threshold'] ?? 250000 ); ?>" 
						style="width: 120px;" />
				</td>
				<td>
					<input type="number" step="0.01" min="0" name="schedule_l[partnership][flat_fee]" 
						value="<?php echo esc_attr( $schedule_l_thresholds['partnership']['flat_fee'] ?? 999 ); ?>" 
						style="width: 100px;" />
				</td>
			</tr>
		</tbody>
	</table>

	<p style="margin-bottom: 20px;">
		<em style="color: #666; font-size: 13px;">
			<strong>How it works:</strong> For each entity type, if BOTH asset AND revenue are below their thresholds, 
			the Flat Fee applies. Otherwise, the asset-band lookup is used.
		</em>
	</p>

	<p class="submit">
		<button type="submit" class="button button-primary">Save Schedule L Thresholds</button>
	</p>
</form>
