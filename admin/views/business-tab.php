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

<h2>Schedule L Threshold (reference only, not editable here)</h2>
<p class="description">
	When a return qualifies, it gets the flat $999 base fee instead of the asset-band lookup above.
	This logic is entity-specific and lives in the plugin's pricing engine, not in a database table —
	changing it requires a developer.
</p>
<table class="widefat striped" style="max-width: 700px;">
	<thead>
		<tr>
			<th>Entity Type</th>
			<th>Schedule L NOT required when</th>
			<th>Flat Fee</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>C-Corporation</td>
			<td>Receipts under $250K AND assets under $250K</td>
			<td>$999.00</td>
		</tr>
		<tr>
			<td>S-Corporation</td>
			<td>Receipts under $250K AND assets under $250K</td>
			<td>$999.00</td>
		</tr>
		<tr>
			<td>Partnership</td>
			<td>Receipts under $250K AND assets do not exceed $1M</td>
			<td>$999.00</td>
		</tr>
	</tbody>
</table>
