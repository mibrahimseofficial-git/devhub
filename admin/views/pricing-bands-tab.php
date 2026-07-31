<?php
/**
 * Pricing Bands Editor — Asset Bands and Revenue Add-Ons.
 * Allows adding/removing rows, editing prices, and managing threshold tiers.
 * 
 * Not accessed directly — included from TQB_Admin.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="tqb-card">
	<div class="tqb-card-header">
		<h2>
			<span class="dashicons dashicons-chart-line"></span>
			Pricing Bands & Tiers
		</h2>
	</div>
	<div class="tqb-card-body">
		<div class="tqb-alert tqb-alert-info">
			<span class="dashicons dashicons-info"></span>
			<div>
				<strong>How to use:</strong> Define tiered pricing for Business returns. Create rows for each revenue band, asset band, or pricing tier. Use the Add/Remove buttons to manage rows dynamically.
				<br /><br />
				<strong>Asset Bands:</strong> Range from Under $250K to Over $10M, with separate pricing for C-Corp/S-Corp and Partnership structures.
				<br /><br />
				<strong>Revenue Add-Ons:</strong> Applied on top of base asset-band pricing when a Schedule L (balance sheet) is required.
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tqb-pricing-bands-form">
			<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_PRICING_BANDS, 'tqb_nonce' ); ?>
			<input type="hidden" name="action" value="tqb_save_pricing_bands" />

			<!-- Asset Bands Section -->
			<div class="tqb-pricing-section">
				<h3 class="tqb-pricing-section__title">
					<span class="dashicons dashicons-money-alt"></span>
					Asset Bands (C-Corp / S-Corp vs Partnership)
				</h3>

				<div class="tqb-table-wrapper">
					<table class="tqb-input-table tqb-pricing-table" data-band-type="asset">
						<thead>
							<tr>
								<th style="width: 30%;">Asset Band</th>
								<th style="width: 25%;">C-Corp / S-Corp Price ($)</th>
								<th style="width: 25%;">Partnership Price ($)</th>
								<th style="width: 15%;">Action</th>
								<th style="width: 5%;">Order</th>
							</tr>
						</thead>
						<tbody class="tqb-pricing-tbody" data-band-type="asset">
							<?php
								$asset_bands = get_option( 'tqb_asset_bands', array() );
								if ( empty( $asset_bands ) ) {
									$asset_bands = array(
										array( 'id' => 1, 'label' => 'Under $250K', 'corp_price' => '1250.00', 'partnership_price' => '1250.00', 'sort_order' => 0 ),
										array( 'id' => 2, 'label' => '$250K-$500K', 'corp_price' => '1250.00', 'partnership_price' => '1250.00', 'sort_order' => 1 ),
										array( 'id' => 3, 'label' => '$500K-$1M', 'corp_price' => '1500.00', 'partnership_price' => '1250.00', 'sort_order' => 2 ),
										array( 'id' => 4, 'label' => '$1M-$2M', 'corp_price' => '1500.00', 'partnership_price' => '1500.00', 'sort_order' => 3 ),
										array( 'id' => 5, 'label' => '$2M-$5M', 'corp_price' => '1750.00', 'partnership_price' => '1700.00', 'sort_order' => 4 ),
										array( 'id' => 6, 'label' => '$5M-$10M', 'corp_price' => 'Custom quote', 'partnership_price' => 'Custom quote', 'sort_order' => 5 ),
										array( 'id' => 7, 'label' => 'Over $10M', 'corp_price' => 'Custom quote', 'partnership_price' => 'Custom quote', 'sort_order' => 6 ),
									);
								}
								foreach ( $asset_bands as $band ) :
							?>
							<tr class="tqb-pricing-row" data-band-id="<?php echo esc_attr( $band['id'] ); ?>" data-sort-order="<?php echo esc_attr( $band['sort_order'] ?? 0 ); ?>">
								<td>
									<input type="text"
										name="asset_bands[<?php echo esc_attr( $band['id'] ); ?>][label]"
										value="<?php echo esc_attr( $band['label'] ); ?>"
										class="tqb-input" 
										placeholder="e.g., Under $250K" />
								</td>
								<td>
									<input type="text"
										name="asset_bands[<?php echo esc_attr( $band['id'] ); ?>][corp_price]"
										value="<?php echo esc_attr( $band['corp_price'] ); ?>"
										class="tqb-input" 
										placeholder="1250.00 or Custom quote" />
								</td>
								<td>
									<input type="text"
										name="asset_bands[<?php echo esc_attr( $band['id'] ); ?>][partnership_price]"
										value="<?php echo esc_attr( $band['partnership_price'] ); ?>"
										class="tqb-input" 
										placeholder="1250.00 or Custom quote" />
								</td>
								<td style="text-align: center;">
									<button type="button" class="tqb-btn tqb-btn-icon-delete tqb-remove-row"
										title="Remove this band">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</td>
								<td>
									<div class="tqb-order-buttons">
										<button type="button" class="tqb-btn tqb-btn-ghost tqb-btn-sm tqb-move-up" 
											title="Move up">
											<span class="dashicons dashicons-arrow-up" style="font-size: 12px;"></span>
										</button>
										<button type="button" class="tqb-btn tqb-btn-ghost tqb-btn-sm tqb-move-down" 
											title="Move down">
											<span class="dashicons dashicons-arrow-down" style="font-size: 12px;"></span>
										</button>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div style="margin-top: 12px;">
					<button type="button" class="tqb-btn tqb-btn-secondary tqb-add-row" data-band-type="asset">
						<span class="dashicons dashicons-plus"></span> Add Asset Band
					</button>
				</div>
			</div>

			<!-- Revenue Add-Ons Section -->
			<div class="tqb-pricing-section" style="margin-top: 40px;">
				<h3 class="tqb-pricing-section__title">
					<span class="dashicons dashicons-plus-alt"></span>
					Revenue Add-Ons (Schedule L Add-On Pricing)
				</h3>
				<p style="color: #666; font-size: 13px; margin-bottom: 16px;">Applied on top of the asset-band price when a Schedule L (balance sheet) is included.</p>

				<div class="tqb-table-wrapper">
					<table class="tqb-input-table tqb-pricing-table" data-band-type="revenue">
						<thead>
							<tr>
								<th style="width: 30%;">Revenue Band</th>
								<th style="width: 25%;">Add-On Price ($)</th>
								<th style="width: 40%;">Notes</th>
								<th style="width: 15%;">Action</th>
								<th style="width: 5%;">Order</th>
							</tr>
						</thead>
						<tbody class="tqb-pricing-tbody" data-band-type="revenue">
							<?php
								$revenue_addons = get_option( 'tqb_revenue_addons', array() );
								if ( empty( $revenue_addons ) ) {
									$revenue_addons = array(
										array( 'id' => 1, 'label' => 'Under $250K', 'addon_price' => '0.00', 'notes' => '', 'sort_order' => 0 ),
										array( 'id' => 2, 'label' => '$250K-$1M', 'addon_price' => '0.00', 'notes' => '', 'sort_order' => 1 ),
										array( 'id' => 3, 'label' => 'Over $1M', 'addon_price' => '200.00', 'notes' => 'Higher complexity', 'sort_order' => 2 ),
									);
								}
								foreach ( $revenue_addons as $addon ) :
							?>
							<tr class="tqb-pricing-row" data-addon-id="<?php echo esc_attr( $addon['id'] ); ?>" data-sort-order="<?php echo esc_attr( $addon['sort_order'] ?? 0 ); ?>">
								<td>
									<input type="text"
										name="revenue_addons[<?php echo esc_attr( $addon['id'] ); ?>][label]"
										value="<?php echo esc_attr( $addon['label'] ); ?>"
										class="tqb-input" 
										placeholder="e.g., Under $250K" />
								</td>
								<td>
									<input type="text"
										name="revenue_addons[<?php echo esc_attr( $addon['id'] ); ?>][addon_price]"
										value="<?php echo esc_attr( $addon['addon_price'] ); ?>"
										class="tqb-input" 
										placeholder="0.00" />
								</td>
								<td>
									<input type="text"
										name="revenue_addons[<?php echo esc_attr( $addon['id'] ); ?>][notes]"
										value="<?php echo esc_attr( $addon['notes'] ?? '' ); ?>"
										class="tqb-input" 
										placeholder="Optional notes" />
								</td>
								<td style="text-align: center;">
									<button type="button" class="tqb-btn tqb-btn-icon-delete tqb-remove-row"
										title="Remove this addon">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</td>
								<td>
									<div class="tqb-order-buttons">
										<button type="button" class="tqb-btn tqb-btn-ghost tqb-btn-sm tqb-move-up" 
											title="Move up">
											<span class="dashicons dashicons-arrow-up" style="font-size: 12px;"></span>
										</button>
										<button type="button" class="tqb-btn tqb-btn-ghost tqb-btn-sm tqb-move-down" 
											title="Move down">
											<span class="dashicons dashicons-arrow-down" style="font-size: 12px;"></span>
										</button>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div style="margin-top: 12px;">
					<button type="button" class="tqb-btn tqb-btn-secondary tqb-add-row" data-band-type="revenue">
						<span class="dashicons dashicons-plus"></span> Add Revenue Add-On
					</button>
				</div>
			</div>

			<div class="tqb-submit">
				<button type="submit" class="tqb-btn tqb-btn-primary">
					<span class="dashicons dashicons-saved" style="font-size:18px;"></span>
					Save Pricing Bands
				</button>
			</div>
		</form>
	</div>
</div>

<style>
	.tqb-pricing-section {
		background: #f9fafb;
		padding: 20px;
		border-radius: 6px;
		border: 1px solid #e5e7eb;
	}

	.tqb-pricing-section__title {
		margin: 0 0 16px 0;
		font-size: 16px;
		font-weight: 600;
		color: #111827;
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.tqb-pricing-section__title .dashicons {
		font-size: 20px;
		width: 20px;
		height: 20px;
	}

	.tqb-pricing-table {
		width: 100%;
		border-collapse: collapse;
		background: #fff;
	}

	.tqb-pricing-table thead {
		background: #f3f4f6;
		border-bottom: 2px solid #e5e7eb;
	}

	.tqb-pricing-table th {
		padding: 12px 16px;
		text-align: left;
		font-size: 13px;
		font-weight: 600;
		color: #374151;
		letter-spacing: 0.5px;
	}

	.tqb-pricing-table td {
		padding: 12px 16px;
		border-bottom: 1px solid #e5e7eb;
		vertical-align: middle;
	}

	.tqb-pricing-table tbody tr:hover {
		background: #f9fafb;
	}

	.tqb-pricing-table .tqb-input {
		width: 100%;
		padding: 8px 12px;
		font-size: 13px;
		border: 1px solid #d1d5db;
		border-radius: 4px;
		font-family: inherit;
		transition: border-color 0.2s ease;
	}

	.tqb-pricing-table .tqb-input:focus {
		outline: none;
		border-color: #2271b1;
		box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
	}

	.tqb-order-buttons {
		display: flex;
		gap: 4px;
	}

	.tqb-btn-move-up,
	.tqb-btn-move-down,
	.tqb-remove-row {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 28px;
		height: 28px;
		padding: 0;
		border: 1px solid #d1d5db;
		background: #fff;
		color: #666;
		cursor: pointer;
		border-radius: 4px;
		transition: all 0.2s ease;
	}

	.tqb-btn-move-up:hover,
	.tqb-btn-move-down:hover {
		color: #2271b1;
		border-color: #2271b1;
		background: #f0f7ff;
	}

	.tqb-remove-row:hover {
		color: #dc2626;
		border-color: #dc2626;
		background: #fee2e2;
	}

	.tqb-remove-row .dashicons {
		font-size: 16px;
		width: 16px;
		height: 16px;
	}

	.tqb-btn-secondary {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 10px 16px;
		background: #e5e7eb;
		color: #374151;
		border: 1px solid #d1d5db;
		border-radius: 4px;
		font-size: 13px;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.2s ease;
	}

	.tqb-btn-secondary:hover {
		background: #d1d5db;
		border-color: #9ca3af;
	}
</style>

<script>
	document.addEventListener( 'DOMContentLoaded', function() {
		// Add row functionality
		document.querySelectorAll( '.tqb-add-row' ).forEach( btn => {
			btn.addEventListener( 'click', function( e ) {
				e.preventDefault();
				const bandType = this.getAttribute( 'data-band-type' );
				tqbAddPricingRow( bandType );
			} );
		} );

		// Remove row functionality
		document.querySelectorAll( '.tqb-remove-row' ).forEach( btn => {
			btn.addEventListener( 'click', function( e ) {
				e.preventDefault();
				if ( confirm( 'Remove this row?' ) ) {
					this.closest( 'tr' ).remove();
					tqbUpdateSortOrders( this.closest( 'tbody' ) );
				}
			} );
		} );

		// Move up/down functionality
		document.querySelectorAll( '.tqb-move-up, .tqb-move-down' ).forEach( btn => {
			btn.addEventListener( 'click', function( e ) {
				e.preventDefault();
				const row = this.closest( 'tr' );
				const tbody = row.parentNode;
				const isUp = this.classList.contains( 'tqb-move-up' );

				if ( isUp && row.previousElementSibling ) {
					tbody.insertBefore( row, row.previousElementSibling );
				} else if ( ! isUp && row.nextElementSibling ) {
					tbody.insertBefore( row.nextElementSibling, row );
				}
				tqbUpdateSortOrders( tbody );
			} );
		} );
	} );

	function tqbAddPricingRow( bandType ) {
		const tbody = document.querySelector( `.tqb-pricing-tbody[data-band-type="${bandType}"]` );
		const rowCount = tbody.querySelectorAll( 'tr' ).length;
		const newId = Math.floor( Math.random() * -100000 );

		let html = `
			<tr class="tqb-pricing-row" data-${bandType}-id="${newId}" data-sort-order="${rowCount}">
		`;

		if ( bandType === 'asset' ) {
			html += `
				<td>
					<input type="text" name="asset_bands[${newId}][label]" class="tqb-input" placeholder="e.g., $250K-$500K" />
				</td>
				<td>
					<input type="text" name="asset_bands[${newId}][corp_price]" class="tqb-input" placeholder="1250.00 or Custom quote" />
				</td>
				<td>
					<input type="text" name="asset_bands[${newId}][partnership_price]" class="tqb-input" placeholder="1250.00 or Custom quote" />
				</td>
			`;
		} else if ( bandType === 'revenue' ) {
			html += `
				<td>
					<input type="text" name="revenue_addons[${newId}][label]" class="tqb-input" placeholder="e.g., $250K-$1M" />
				</td>
				<td>
					<input type="text" name="revenue_addons[${newId}][addon_price]" class="tqb-input" placeholder="0.00" />
				</td>
				<td>
					<input type="text" name="revenue_addons[${newId}][notes]" class="tqb-input" placeholder="Optional notes" />
				</td>
			`;
		}

		html += `
				<td style="text-align: center;">
					<button type="button" class="tqb-btn tqb-btn-icon-delete tqb-remove-row" title="Remove">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</td>
				<td>
					<div class="tqb-order-buttons">
						<button type="button" class="tqb-btn tqb-btn-ghost tqb-btn-sm tqb-move-up" title="Move up">
							<span class="dashicons dashicons-arrow-up" style="font-size: 12px;"></span>
						</button>
						<button type="button" class="tqb-btn tqb-btn-ghost tqb-btn-sm tqb-move-down" title="Move down">
							<span class="dashicons dashicons-arrow-down" style="font-size: 12px;"></span>
						</button>
					</div>
				</td>
			</tr>
		`;

		tbody.insertAdjacentHTML( 'beforeend', html );

		// Reattach event listeners to the new row
		const newRow = tbody.lastElementChild;
		newRow.querySelector( '.tqb-remove-row' ).addEventListener( 'click', function( e ) {
			e.preventDefault();
			if ( confirm( 'Remove this row?' ) ) {
				newRow.remove();
				tqbUpdateSortOrders( tbody );
			}
		} );

		newRow.querySelectorAll( '.tqb-move-up, .tqb-move-down' ).forEach( btn => {
			btn.addEventListener( 'click', function( e ) {
				e.preventDefault();
				const isUp = this.classList.contains( 'tqb-move-up' );
				if ( isUp && newRow.previousElementSibling ) {
					tbody.insertBefore( newRow, newRow.previousElementSibling );
				} else if ( ! isUp && newRow.nextElementSibling ) {
					tbody.insertBefore( newRow.nextElementSibling, newRow );
				}
				tqbUpdateSortOrders( tbody );
			} );
		} );
	}

	function tqbUpdateSortOrders( tbody ) {
		tbody.querySelectorAll( 'tr' ).forEach( ( row, index ) => {
			row.setAttribute( 'data-sort-order', index );
		} );
	}
</script>
