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
	Base fee lookup by business size. Leave a band's price blank to mark it as
	<strong>Custom</strong> (routes the prospect to a custom-quote request instead of a price —
	this is currently how the $5M+ bands are configured).
	Band ranges themselves aren't editable here — contact your developer if a range needs to change.
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_RATE_BANDS, 'tqb_nonce' ); ?>
	<input type="hidden" name="action" value="tqb_save_rate_bands" />

	<table class="widefat striped" style="max-width: 700px;">
		<thead>
			<tr>
				<th>Asset Band</th>
				<th>C-Corp / S-Corp Price ($)</th>
				<th>Partnership Price ($)</th>
			</tr>
		</thead>
		<tbody>
			<?php
			// Both arrays are seeded in the same order (same bands, same sort_order),
			// so we can walk them in lockstep by index.
			foreach ( $asset_bands_c_s as $i => $band ) :
				$partnership_band = $asset_bands_partnership[ $i ] ?? null;
				?>
				<tr>
					<td><strong><?php echo esc_html( $band['band_label'] ); ?></strong></td>
					<td>
						<?php if ( $band['is_custom'] ) : ?>
							<em>Custom quote</em>
						<?php else : ?>
							<input type="number" step="0.01" min="0"
								name="bands[<?php echo esc_attr( $band['id'] ); ?>][price]"
								value="<?php echo esc_attr( $band['price'] ); ?>"
								style="width: 100px;" />
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $partnership_band && $partnership_band['is_custom'] ) : ?>
							<em>Custom quote</em>
						<?php elseif ( $partnership_band ) : ?>
							<input type="number" step="0.01" min="0"
								name="bands[<?php echo esc_attr( $partnership_band['id'] ); ?>][price]"
								value="<?php echo esc_attr( $partnership_band['price'] ); ?>"
								style="width: 100px;" />
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h3 style="margin-top: 30px;">Revenue Add-On</h3>
	<p class="description">Added on top of the asset-band price when the return requires Schedule L.</p>

	<table class="widefat striped" style="max-width: 500px;">
		<thead>
			<tr>
				<th>Revenue Band</th>
				<th>Add-On ($)</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $revenue_addons as $addon ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $addon['band_label'] ); ?></strong></td>
					<td>
						<input type="number" step="0.01" min="0"
							name="bands[<?php echo esc_attr( $addon['id'] ); ?>][price]"
							value="<?php echo esc_attr( $addon['price'] ); ?>"
							style="width: 100px;" />
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary">Save Rate Reference</button>
	</p>
</form>

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
