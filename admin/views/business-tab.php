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

<!-- Page Header -->
<div class="tqb-page-header">
	<h1>
		<span class="dashicons dashicons-money-alt"></span>
		Business Pricing
	</h1>
</div>

<!-- Rate Reference Card -->
<div class="tqb-card">
	<div class="tqb-card-body" style="padding: 0;">
		<div class="tqb-alert tqb-alert-info" style="border-radius: 0; border-left: none; border-right: none; border-top: none; margin: 0;">
			<span class="dashicons dashicons-info"></span>
			<div>
				<strong>How it works:</strong> Base fee lookup by business size. Leave a band's price blank to mark it as <strong>Custom</strong> (routes the prospect to a custom-quote request instead of a price — this is currently how the $5M+ bands are configured).
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_RATE_BANDS, 'tqb_nonce' ); ?>
			<input type="hidden" name="action" value="tqb_save_rate_bands" />

			<div class="tqb-table-wrapper" style="margin-bottom: 30px;">
				<table class="tqb-input-table" style="border-radius: 0; box-shadow: none;">
					<thead>
						<tr>
							<th>Asset Band</th>
							<th>C-Corp / S-Corp Price ($)</th>
							<th>Partnership Price ($)</th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $asset_bands_c_s as $i => $band ) :
							$partnership_band = $asset_bands_partnership[ $i ] ?? null;
							?>
							<tr>
								<td><strong><?php echo esc_html( $band['band_label'] ); ?></strong></td>
								<td>
									<?php if ( $band['is_custom'] ) : ?>
										<span style="color:#d97706; font-weight:600;">Custom quote</span>
									<?php else : ?>
										<input type="number" step="0.01" min="0"
											name="bands[<?php echo esc_attr( $band['id'] ); ?>][price]"
											value="<?php echo esc_attr( $band['price'] ); ?>"
											style="width: 120px;" />
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $partnership_band && $partnership_band['is_custom'] ) : ?>
										<span style="color:#d97706; font-weight:600;">Custom quote</span>
									<?php elseif ( $partnership_band ) : ?>
										<input type="number" step="0.01" min="0"
											name="bands[<?php echo esc_attr( $partnership_band['id'] ); ?>][price]"
											value="<?php echo esc_attr( $partnership_band['price'] ); ?>"
											style="width: 120px;" />
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div style="padding: 0 24px 24px;">
				<h3 class="tqb-section-title" style="font-size:14px; border-bottom:none; padding-bottom:0; margin-bottom:12px;">
					<span class="dashicons dashicons-plus-alt" style="font-size:16px;"></span>
					Revenue Add-On
				</h3>
				<p class="tqb-description" style="margin-bottom:16px;">Added on top of the asset-band price when the return requires Schedule L.</p>

				<div class="tqb-table-wrapper" style="max-width: 400px;">
					<table class="tqb-input-table">
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
											style="width: 120px;" />
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div style="padding: 20px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
				<button type="submit" class="tqb-btn tqb-btn-primary">
					<span class="dashicons dashicons-saved" style="font-size:18px;"></span>
					Save Rate Reference
				</button>
			</div>
		</form>
	</div>
</div>

<?php
$items      = $extra_items;
$quote_type = 'business';
$heading    = 'Business Return — Extras (Part B)';
include TQB_PLUGIN_DIR . 'admin/views/line-items-tab.php';
?>

<!-- Schedule L Reference Card -->
<div class="tqb-card">
	<div class="tqb-card-body" style="padding: 0;">
		<div class="tqb-alert tqb-alert-warning" style="border-radius: 0; border-left: none; border-right: none; border-top: none; margin: 0;">
			<span class="dashicons dashicons-warning"></span>
			<div>
				When a return qualifies, it gets the flat $999 base fee instead of the asset-band lookup above. This logic is entity-specific and lives in the plugin's pricing engine — contact your developer if changes are needed.
			</div>
		</div>

		<div class="tqb-table-wrapper">
			<table class="tqb-table" style="border-radius: 0; box-shadow: none;">
				<thead>
					<tr>
						<th>Entity Type</th>
						<th>Schedule L NOT required when</th>
						<th style="width:120px;">Flat Fee</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong>C-Corporation</strong></td>
						<td>Receipts under $250K AND assets under $250K</td>
						<td><span style="font-weight:700; color:#001a44;">$999.00</span></td>
					</tr>
					<tr>
						<td><strong>S-Corporation</strong></td>
						<td>Receipts under $250K AND assets under $250K</td>
						<td><span style="font-weight:700; color:#001a44;">$999.00</span></td>
					</tr>
					<tr>
						<td><strong>Partnership</strong></td>
						<td>Receipts under $250K AND assets do not exceed $1M</td>
						<td><span style="font-weight:700; color:#001a44;">$999.00</span></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
