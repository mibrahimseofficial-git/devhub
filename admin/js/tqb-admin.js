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
