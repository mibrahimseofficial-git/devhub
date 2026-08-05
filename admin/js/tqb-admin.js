/**
 * Tavola Quote Builder — admin settings page JS.
 * Fetches HubSpot pipelines/stages live and populates the General Settings
 * dropdowns. The actual saved values live in hidden inputs (tqb_hubspot_
 * pipeline_id / _stage_new / _stage_custom) — the visible <select> elements
 * just drive those hidden inputs, since the selects get rebuilt every time
 * pipelines are fetched and we don't want that to lose the current value.
 */
( function () {
	'use strict';

	if ( typeof tqbAdminData === 'undefined' ) {
		return;
	}

	var refreshBtn = document.getElementById( 'tqb-refresh-pipelines' );
	var statusEl = document.getElementById( 'tqb-pipeline-status' );
	var pipelineSelect = document.getElementById( 'tqb_hubspot_pipeline_select' );
	var stageNewSelect = document.getElementById( 'tqb_hubspot_stage_new_select' );
	var stageCustomSelect = document.getElementById( 'tqb_hubspot_stage_custom_select' );

	var pipelineHidden = document.getElementById( 'tqb_hubspot_pipeline_id' );
	var stageNewHidden = document.getElementById( 'tqb_hubspot_stage_new' );
	var stageCustomHidden = document.getElementById( 'tqb_hubspot_stage_custom' );

	if ( ! refreshBtn || ! pipelineSelect ) {
		return;
	}

	var pipelinesData = [];

	function setStatus( text, isError ) {
		statusEl.textContent = text;
		statusEl.style.color = isError ? '#b32d2e' : '#666';
	}

	function fetchPipelines() {
		setStatus( 'Loading…', false );
		refreshBtn.disabled = true;

		var body = new URLSearchParams();
		body.append( 'action', 'tqb_fetch_hubspot_pipelines' );
		body.append( 'nonce', tqbAdminData.nonce );

		fetch( tqbAdminData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				refreshBtn.disabled = false;

				if ( ! json.success ) {
					setStatus( ( json.data && json.data.message ) || 'Failed to load pipelines.', true );
					return;
				}

				pipelinesData = json.data.pipelines || [];
				populatePipelineSelect();
				setStatus( pipelinesData.length + ' pipeline(s) loaded.', false );
			} )
			.catch( function () {
				refreshBtn.disabled = false;
				setStatus( 'Could not reach the server.', true );
			} );
	}

	function populatePipelineSelect() {
		var currentValue = pipelineHidden.value;

		pipelineSelect.innerHTML = '<option value="">— Select a pipeline —</option>';

		pipelinesData.forEach( function ( pipeline ) {
			var opt = document.createElement( 'option' );
			opt.value = pipeline.id;
			opt.textContent = pipeline.label;
			if ( pipeline.id === currentValue ) {
				opt.selected = true;
			}
			pipelineSelect.appendChild( opt );
		} );

		// If we already had a saved pipeline, populate its stages immediately
		// so the saved stage selections are still visible without an extra click.
		if ( currentValue ) {
			populateStageSelects( currentValue );
		}
	}

	function populateStageSelects( pipelineId ) {
		var pipeline = pipelinesData.filter( function ( p ) {
			return p.id === pipelineId;
		} )[ 0 ];

		[ stageNewSelect, stageCustomSelect ].forEach( function ( select, index ) {
			var hidden = index === 0 ? stageNewHidden : stageCustomHidden;
			var currentValue = hidden.value;

			select.innerHTML = '';
			select.disabled = ! pipeline;

			if ( ! pipeline ) {
				select.innerHTML = '<option value="">— Select a pipeline first —</option>';
				return;
			}

			var blankOpt = document.createElement( 'option' );
			blankOpt.value = '';
			blankOpt.textContent = '— Use pipeline default —';
			select.appendChild( blankOpt );

			pipeline.stages.forEach( function ( stage ) {
				var opt = document.createElement( 'option' );
				opt.value = stage.id;
				opt.textContent = stage.label;
				if ( stage.id === currentValue ) {
					opt.selected = true;
				}
				select.appendChild( opt );
			} );
		} );
	}

	refreshBtn.addEventListener( 'click', fetchPipelines );

	pipelineSelect.addEventListener( 'change', function () {
		pipelineHidden.value = pipelineSelect.value;
		populateStageSelects( pipelineSelect.value );
	} );

	stageNewSelect.addEventListener( 'change', function () {
		stageNewHidden.value = stageNewSelect.value;
	} );

	stageCustomSelect.addEventListener( 'change', function () {
		stageCustomHidden.value = stageCustomSelect.value;
	} );

	// Auto-load on page open if a pipeline was already saved previously, so
	// the dropdowns show meaningful labels instead of just an ID in a hidden
	// field. If nothing was saved yet, wait for the user to click Refresh
	// (avoids an unnecessary API call on every page load).
	if ( pipelineHidden.value ) {
		fetchPipelines();
	}
} )();

/**
 * Line Items Management Functions
 */

// Add new line item row to the table
function tqbAddLineItem( quoteType ) {
	const table = document.querySelector( '.tqb-line-items-tbody' );
	if ( ! table ) {
		return;
	}

	// Generate a temporary ID for the new row (negative number to indicate it's new)
	const newId = Math.floor( Math.random() * -100000 );
	const rowIndex = table.querySelectorAll( 'tr' ).length;

	const html = `
		<tr class="tqb-line-item-row" data-item-id="${newId}" data-sort-order="${rowIndex}">
			<!-- Order/Reorder -->
			<td class="tqb-order-column">
				<div class="tqb-order-buttons">
					<button type="button" class="tqb-btn tqb-btn-ghost tqb-btn-sm tqb-btn-move-up" 
						onclick="tqbMoveItemUp(event, ${newId})"
						title="Move up">
						<span class="dashicons dashicons-arrow-up" style="font-size: 14px;"></span>
					</button>
					<button type="button" class="tqb-btn tqb-btn-ghost tqb-btn-sm tqb-btn-move-down" 
						onclick="tqbMoveItemDown(event, ${newId})"
						title="Move down">
						<span class="dashicons dashicons-arrow-down" style="font-size: 14px;"></span>
					</button>
				</div>
			</td>

			<!-- Filing Status Filter -->
			<td class="tqb-filing-status-cell">
				<select name="items[${newId}][filing_status]" class="tqb-input" style="width: 100%;">
					<option value="">All Filing Statuses</option>
					<option value="single">Single Only</option>
					<option value="mfj">MFJ Only</option>
					<option value="mfs">MFS Only</option>
					<option value="hoh">HOH Only</option>
				</select>
			</td>

			<!-- Label & Tooltip -->
			<td class="tqb-label-tooltip-cell">
				<div class="tqb-label-section">
					<label class="tqb-cell-label">Label</label>
					<input type="text"
						name="items[${newId}][label]"
						value=""
						class="tqb-input" placeholder="Item label" />
					<code class="tqb-item-key">new_item_${Math.abs(newId)}</code>
				</div>
				<div class="tqb-tooltip-section">
					<label class="tqb-cell-label">Tooltip / Help Text</label>
					<textarea
						name="items[${newId}][tooltip]"
						rows="2"
						placeholder="Optional help text for users..."
						class="tqb-textarea"></textarea>
				</div>
			</td>

			<!-- Fee -->
			<td>
				<input type="number" step="0.01" min="0"
					name="items[${newId}][fee]"
					value="0.00"
					class="tqb-input" />
			</td>

			<!-- Pattern -->
			<td>
				<select name="items[${newId}][pricing_pattern]" class="tqb-input">
					<option value="qty_times_fee" selected>Qty × Fee</option>
					<option value="flat">Flat</option>
				</select>
			</td>

			<!-- Threshold (Multi-Condition Builder) -->
			<td class="tqb-threshold-cell">
				<div class="tqb-threshold-inline" data-item-id="${newId}">
					<!-- Mode: None or Custom -->
					<div class="tqb-threshold-mode-row">
						<label class="tqb-threshold-mode-label">
							<input type="radio" name="items[${newId}][threshold_mode]"
								value="none" checked />
							None
						</label>
						<label class="tqb-threshold-mode-label">
							<input type="radio" name="items[${newId}][threshold_mode]"
								value="custom" />
							Custom
						</label>
					</div>

					<!-- Custom Fields (visible when Custom is selected) -->
					<div class="tqb-threshold-fields" style="display: none;">
						<!-- Condition rows container -->
						<div class="tqb-threshold-conditions" id="tqb-conditions-${newId}">
							<!-- Single default condition row -->
							<div class="tqb-threshold-condition-row">
								<div class="tqb-condition-field">
									<label class="tqb-field-label">Type</label>
									<select name="items[${newId}][threshold_conditions][0][type]" class="tqb-input tqb-cond-type">
										<option value="qty" selected>Quantity</option>
										<option value="dollar_value">Total value ($)</option>
									</select>
								</div>
								<div class="tqb-condition-field">
									<label class="tqb-field-label">Operator</label>
									<select name="items[${newId}][threshold_conditions][0][operator]" class="tqb-input tqb-cond-operator">
										<option value="above" selected>Above (&gt;)</option>
										<option value="below">Below (&lt;)</option>
									</select>
								</div>
								<div class="tqb-condition-field">
									<label class="tqb-field-label">Value</label>
									<input type="number" step="1" min="0"
										name="items[${newId}][threshold_conditions][0][value]"
										value=""
										placeholder="e.g., 100"
										class="tqb-input tqb-cond-value" />
								</div>
							</div>
						</div>

						<!-- Logic toggle (shown only when 2+ conditions exist) -->
						<div class="tqb-threshold-logic-row" id="tqb-logic-row-${newId}" style="display: none;">
							<label class="tqb-threshold-mode-label">
								<input type="radio" name="items[${newId}][threshold_logic]"
									value="OR" checked />
								Match ANY (OR)
							</label>
							<label class="tqb-threshold-mode-label">
								<input type="radio" name="items[${newId}][threshold_logic]"
									value="AND" />
								Match ALL (AND)
							</label>
						</div>

						<!-- Add condition button -->
						<button type="button" class="tqb-btn tqb-btn-secondary tqb-btn-add-condition"
							data-item-id="${newId}">
							<span class="dashicons dashicons-plus-alt"></span>
							Add Condition
						</button>
					</div>
				</div>
			</td>

			<!-- Reveal Qty -->
			<td style="text-align: center;">
				<label title="Show quantity input only after user checks 'Yes'">
					<input type="checkbox"
						name="items[${newId}][reveal_followup]"
						value="1" />
				</label>
			</td>

			<!-- Active -->
			<td style="text-align: center;">
				<input type="checkbox"
					name="items[${newId}][is_active]"
					value="1" checked />
			</td>

			<!-- Action -->
			<td style="text-align: center;">
				<button type="button" class="tqb-btn tqb-btn-icon-delete"
					onclick="tqbDeleteLineItem(event, ${newId})"
					title="Delete this item">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</td>

			<!-- Internal Info -->
			<td style="font-size:11px; color:#64748b;">
				<span style="color:#1e90ff;">New item</span>
			</td>
		</tr>
	`;

	table.insertAdjacentHTML( 'beforeend', html );

	// Initialize threshold controls for the new row
	if ( window.tqbInitThresholdControls ) {
		window.tqbInitThresholdControls();
	}
}

// Delete line item row
function tqbDeleteLineItem( e, itemId ) {
	e.preventDefault();
	if ( confirm( 'Are you sure you want to delete this item?' ) ) {
		const row = document.querySelector( `.tqb-line-item-row[data-item-id="${itemId}"]` );
		if ( row ) {
			row.remove();
		}
	}
}

// Move item up
function tqbMoveItemUp( e, itemId ) {
	e.preventDefault();
	const row = document.querySelector( `.tqb-line-item-row[data-item-id="${itemId}"]` );
	if ( row && row.previousElementSibling ) {
		row.parentNode.insertBefore( row, row.previousElementSibling );
		tqbUpdateSortOrder();
	}
}

// Move item down
function tqbMoveItemDown( e, itemId ) {
	e.preventDefault();
	const row = document.querySelector( `.tqb-line-item-row[data-item-id="${itemId}"]` );
	if ( row && row.nextElementSibling ) {
		row.parentNode.insertBefore( row.nextElementSibling, row );
		tqbUpdateSortOrder();
	}
}

// Update sort order after reordering
function tqbUpdateSortOrder() {
	const rows = document.querySelectorAll( '.tqb-line-item-row' );
	rows.forEach( ( row, index ) => {
		row.setAttribute( 'data-sort-order', index );
	} );
}

// Initialize on page load
document.addEventListener( 'DOMContentLoaded', function() {
	// Initialize threshold controls if the function is defined in the view
	if ( window.tqbInitThresholdControls ) {
		window.tqbInitThresholdControls();
	}
	
	// Add click handler to the Add Item button
	const addBtn = document.querySelector( '.tqb-btn-add-item' );
	if ( addBtn ) {
		addBtn.addEventListener( 'click', function( e ) {
			e.preventDefault();
			const quoteType = this.getAttribute( 'data-quote-type' );
			tqbAddLineItem( quoteType );
		} );
	}
} );
