/**
 * Tavola Quote Builder — front-end wizard logic.
 * No framework dependency (vanilla JS), matches the "no third-party
 * form-builder" architecture decision in PROJECT_SPEC.md.
 *
 * 5 steps: 1 Return Type, 2 Your Info, 3 Details, 4 Review, 5 Your Quote.
 *
 * IMPORTANT — client-side pricing mirror (PROJECT_SPEC.md Section 9.1):
 * calculateIndividualPreview() / calculateBusinessPreview() below are a JS
 * re-implementation of the PHP pricing rules in
 * includes/class-tqb-pricing-engine.php, used ONLY to power the live
 * summary panel. The actual submitted price always comes from the server
 * (TQB_Pricing_Engine, via the AJAX submit) — this preview can never be
 * what gets charged. If pricing logic changes, BOTH places need updating,
 * or the live preview can drift from the real calculated price (the final
 * submitted quote will still be correct either way).
 */
( function () {
	'use strict';

	if ( typeof tqbData === 'undefined' ) {
		return;
	}

	var wizard = document.getElementById( 'tqb-wizard' );
	if ( ! wizard ) {
		return;
	}

	var STEP = { TYPE: 1, CONTACT: 2, QUESTIONS: 3, REVIEW: 4, RESULT: 5 };

	// Mirrors the Schedule L thresholds in TQB_Pricing_Engine::calculate_business().
	var SCHEDULE_L_ASSET_THRESHOLD_C_S_CORP = 250000;
	var SCHEDULE_L_ASSET_THRESHOLD_PARTNERSHIP = 1000000;
	var SCHEDULE_L_REVENUE_THRESHOLD = 250000;
	var SCHEDULE_L_FLAT_FEE = 999;

	var state = {
		quoteType: null,
	};

	var ENTITY_OPTIONS = [
		{ value: 'c_corp', label: 'C-Corporation (Form 1120)' },
		{ value: 's_corp', label: 'S-Corporation (Form 1120-S)' },
		{ value: 'partnership', label: 'Partnership (Form 1065)' },
	];

	// ---------------------------------------------------------------------
	// Step navigation
	// ---------------------------------------------------------------------

	function goToStep( stepNumber ) {
		var steps = wizard.querySelectorAll( '.tqb-step' );
		steps.forEach( function ( section ) {
			var isTarget = parseInt( section.getAttribute( 'data-step' ), 10 ) === stepNumber;
			section.hidden = ! isTarget;
		} );

		var indicators = wizard.querySelectorAll( '.tqb-progress__step' );
		indicators.forEach( function ( indicator ) {
			var indicatorStep = parseInt( indicator.getAttribute( 'data-step-indicator' ), 10 );
			indicator.classList.toggle( 'is-active', indicatorStep === stepNumber );
			indicator.classList.toggle( 'is-complete', indicatorStep < stepNumber );
		} );

		wizard.setAttribute( 'data-step', stepNumber );
		wizard.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}

	// ---------------------------------------------------------------------
	// Reset — clears everything and returns to Step 1
	// ---------------------------------------------------------------------

	function resetAll() {
		state.quoteType = null;

		var nameEl = document.getElementById( 'tqb-contact-name' );
		var emailEl = document.getElementById( 'tqb-contact-email' );
		var phoneEl = document.getElementById( 'tqb-contact-phone' );
		[ nameEl, emailEl, phoneEl ].forEach( function ( field ) {
			field.value = '';
			field.style.borderColor = '';
		} );

		document.getElementById( 'tqb-questions-list' ).innerHTML = '';
		document.getElementById( 'tqb-business-basics' ).innerHTML = '';
		document.getElementById( 'tqb-business-basics' ).hidden = true;
		document.getElementById( 'tqb-review-content' ).innerHTML = '';
		document.getElementById( 'tqb-result-content' ).innerHTML = '';

		var errorEl = document.getElementById( 'tqb-form-error' );
		errorEl.hidden = true;

		updateSummaryPanel();
		goToStep( STEP.TYPE );
	}

	wizard.querySelectorAll( '[data-action="reset-all"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			resetAll();
		} );
	} );

	// ---------------------------------------------------------------------
	// Step 1: quote type selection
	// ---------------------------------------------------------------------

	wizard.querySelectorAll( '.tqb-type-card' ).forEach( function ( card ) {
		card.addEventListener( 'click', function () {
			state.quoteType = card.getAttribute( 'data-quote-type' );
			buildQuestionsStep();
			updateSummaryPanel();
			goToStep( STEP.CONTACT );
		} );
	} );

	// ---------------------------------------------------------------------
	// Step 2 nav
	// ---------------------------------------------------------------------

	wizard.querySelectorAll( '[data-step="2"] [data-action="back"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			goToStep( STEP.TYPE );
		} );
	} );

	wizard.querySelectorAll( '[data-step="2"] [data-action="to-questions"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( ! validateContactFields() ) {
				return;
			}
			goToStep( STEP.QUESTIONS );
		} );
	} );

	[ 'tqb-contact-name', 'tqb-contact-email', 'tqb-contact-phone' ].forEach( function ( id ) {
		var field = document.getElementById( id );
		field.addEventListener( 'input', updateSummaryPanel );
	} );

	function validateContactFields() {
		var name = document.getElementById( 'tqb-contact-name' );
		var email = document.getElementById( 'tqb-contact-email' );
		var phone = document.getElementById( 'tqb-contact-phone' );

		var valid = name.value.trim() !== '' && email.checkValidity() && phone.value.trim() !== '';

		[ name, email, phone ].forEach( function ( field ) {
			field.style.borderColor = field.value.trim() === '' ? '#B3261E' : '';
		} );

		return valid;
	}

	// ---------------------------------------------------------------------
	// Step 3: build the dynamic questions list
	// ---------------------------------------------------------------------

	function buildQuestionsStep() {
		var businessBasics = document.getElementById( 'tqb-business-basics' );
		var questionsList = document.getElementById( 'tqb-questions-list' );
		var title = document.getElementById( 'tqb-questions-title' );

		questionsList.innerHTML = '';
		businessBasics.innerHTML = '';

		if ( 'business' === state.quoteType ) {
			title.textContent = 'Tell us about your business';
			businessBasics.hidden = false;
			buildBusinessBasics( businessBasics );
			renderQuestionRows( questionsList, tqbData.businessItems );
		} else {
			title.textContent = 'Tell us about your situation';
			businessBasics.hidden = true;
			renderQuestionRows( questionsList, tqbData.individualItems );

			// W-2 wages is the mandatory Individual starting point — pre-select
			// and disable its checkbox since it always applies (PROJECT_SPEC.md
			// Section 3).
			var w2Row = questionsList.querySelector( '[data-item-key="w2_wages"]' );
			if ( w2Row ) {
				var w2Checkbox = w2Row.querySelector( 'input[type="checkbox"]' );
				w2Checkbox.checked = true;
				w2Checkbox.disabled = true;
				var w2Note = document.createElement( 'span' );
				w2Note.className = 'tqb-question-row__note';
				w2Note.textContent = 'Everyone starts here — included automatically.';
				w2Row.querySelector( '.tqb-question-row__label' ).parentNode.appendChild( w2Note );
			}
		}

		updateSummaryPanel();
	}

	function buildBusinessBasics( container ) {
		container.appendChild(
			buildSelectField( 'tqb-entity-type', 'Business type', ENTITY_OPTIONS, function () {
				updateAssetBandOptions();
				updateSummaryPanel();
			} )
		);

		container.appendChild(
			buildSelectField( 'tqb-asset-band', 'Total business assets', [], updateSummaryPanel )
		);

		container.appendChild(
			buildSelectField( 'tqb-revenue-band', 'Annual revenue / total receipts', tqbData.revenueBands.map( function ( b ) {
				return { value: b.label, label: b.label };
			} ), updateSummaryPanel )
		);

		updateAssetBandOptions();
	}

	function updateAssetBandOptions() {
		var entityType = document.getElementById( 'tqb-entity-type' ).value;
		var entityGroup = ( 'partnership' === entityType ) ? 'partnership' : 'c_s_corp';
		var bands = tqbData.assetBands[ entityGroup ] || [];

		var select = document.getElementById( 'tqb-asset-band' );
		select.innerHTML = '';
		bands.forEach( function ( band ) {
			var opt = document.createElement( 'option' );
			opt.value = band.label;
			opt.textContent = band.label + ( band.isCustom ? ' (requires custom quote)' : '' );
			select.appendChild( opt );
		} );
	}

	function buildSelectField( id, labelText, options, onChange ) {
		var wrapper = document.createElement( 'div' );
		wrapper.className = 'tqb-field';

		var label = document.createElement( 'label' );
		label.setAttribute( 'for', id );
		label.textContent = labelText;
		wrapper.appendChild( label );

		var select = document.createElement( 'select' );
		select.id = id;
		options.forEach( function ( o ) {
			var opt = document.createElement( 'option' );
			opt.value = o.value;
			opt.textContent = o.label;
			select.appendChild( opt );
		} );
		if ( onChange ) {
			select.addEventListener( 'change', onChange );
		}
		wrapper.appendChild( select );

		return wrapper;
	}

	function renderQuestionRows( container, items ) {
		items.forEach( function ( item ) {
			var row = document.createElement( 'div' );
			row.className = 'tqb-question-row';
			row.setAttribute( 'data-item-key', item.key );

			var main = document.createElement( 'div' );
			main.className = 'tqb-question-row__main';

			var checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.id = 'tqb-item-' + item.key;
			checkbox.setAttribute( 'data-item-key', item.key );

			var textWrap = document.createElement( 'div' );

			var label = document.createElement( 'label' );
			label.className = 'tqb-question-row__label';
			label.setAttribute( 'for', 'tqb-item-' + item.key );
			label.textContent = item.label;
			textWrap.appendChild( label );

			if ( item.notes ) {
				var note = document.createElement( 'div' );
				note.className = 'tqb-question-row__note';
				note.textContent = item.notes;
				textWrap.appendChild( note );
			}

			main.appendChild( checkbox );
			main.appendChild( textWrap );
			row.appendChild( main );

			checkbox.addEventListener( 'change', updateSummaryPanel );

			if ( item.showQty ) {
				var qty = document.createElement( 'input' );
				qty.type = 'number';
				qty.className = 'tqb-question-row__qty';
				qty.min = '1';
				qty.value = '1';
				qty.setAttribute( 'data-item-key-qty', item.key );
				qty.disabled = true;
				row.appendChild( qty );

				checkbox.addEventListener( 'change', function () {
					qty.disabled = ! checkbox.checked;
				} );
				qty.addEventListener( 'input', updateSummaryPanel );
			}

			container.appendChild( row );
		} );
	}

	// ---------------------------------------------------------------------
	// Step 3 nav -> Step 4 (Review)
	// ---------------------------------------------------------------------

	wizard.querySelectorAll( '[data-step="3"] [data-action="back"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			goToStep( STEP.CONTACT );
		} );
	} );

	wizard.querySelectorAll( '[data-step="3"] [data-action="to-review"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			buildReviewStep();
			goToStep( STEP.REVIEW );
		} );
	} );

	wizard.querySelectorAll( '[data-step="4"] [data-action="back"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			goToStep( STEP.QUESTIONS );
		} );
	} );

	wizard.querySelectorAll( '[data-step="4"] [data-action="submit"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', submitQuote );
	} );

	function collectAnswers() {
		var answers = {};
		wizard.querySelectorAll( '#tqb-questions-list input[type="checkbox"]' ).forEach( function ( checkbox ) {
			var key = checkbox.getAttribute( 'data-item-key' );
			var qtyField = wizard.querySelector( '[data-item-key-qty="' + key + '"]' );
			answers[ key ] = {
				selected: checkbox.checked,
				qty: qtyField ? ( parseInt( qtyField.value, 10 ) || 1 ) : 1,
			};
		} );
		return answers;
	}

	// ---------------------------------------------------------------------
	// Step 4: Review screen — shows every answer before final submit
	// ---------------------------------------------------------------------

	function buildReviewStep() {
		var container = document.getElementById( 'tqb-review-content' );
		container.innerHTML = '';

		// Contact info
		var contactSection = document.createElement( 'div' );
		contactSection.className = 'tqb-review-section';
		var contactTitle = document.createElement( 'div' );
		contactTitle.className = 'tqb-review-section__title';
		contactTitle.textContent = 'Your Info';
		contactSection.appendChild( contactTitle );

		[
			[ 'Name', document.getElementById( 'tqb-contact-name' ).value ],
			[ 'Email', document.getElementById( 'tqb-contact-email' ).value ],
			[ 'Phone', document.getElementById( 'tqb-contact-phone' ).value ],
		].forEach( function ( pair ) {
			contactSection.appendChild( buildReviewRow( pair[ 0 ], pair[ 1 ] ) );
		} );
		container.appendChild( contactSection );

		// Business basics, if applicable
		if ( 'business' === state.quoteType ) {
			var basicsSection = document.createElement( 'div' );
			basicsSection.className = 'tqb-review-section';
			var basicsTitle = document.createElement( 'div' );
			basicsTitle.className = 'tqb-review-section__title';
			basicsTitle.textContent = 'Business Details';
			basicsSection.appendChild( basicsTitle );

			var entitySelect = document.getElementById( 'tqb-entity-type' );
			var entityLabel = entitySelect.options[ entitySelect.selectedIndex ].textContent;

			basicsSection.appendChild( buildReviewRow( 'Business type', entityLabel ) );
			basicsSection.appendChild( buildReviewRow( 'Total assets', document.getElementById( 'tqb-asset-band' ).value ) );
			basicsSection.appendChild( buildReviewRow( 'Annual revenue', document.getElementById( 'tqb-revenue-band' ).value ) );
			container.appendChild( basicsSection );
		}

		// Selected items
		var itemsSection = document.createElement( 'div' );
		itemsSection.className = 'tqb-review-section';
		var itemsTitle = document.createElement( 'div' );
		itemsTitle.className = 'tqb-review-section__title';
		itemsTitle.textContent = 'Your Answers';
		itemsSection.appendChild( itemsTitle );

		var answers = collectAnswers();
		var items = ( 'business' === state.quoteType ) ? tqbData.businessItems : tqbData.individualItems;
		var anySelected = false;

		items.forEach( function ( item ) {
			var answer = answers[ item.key ];
			if ( ! answer || ! answer.selected ) {
				return;
			}
			anySelected = true;
			var valueText = item.showQty ? ( 'Yes (Qty: ' + answer.qty + ')' ) : 'Yes';
			itemsSection.appendChild( buildReviewRow( item.label, valueText ) );
		} );

		if ( ! anySelected ) {
			var empty = document.createElement( 'p' );
			empty.className = 'tqb-review-empty';
			empty.textContent = 'No additional items selected.';
			itemsSection.appendChild( empty );
		}

		container.appendChild( itemsSection );
	}

	function buildReviewRow( label, value ) {
		var row = document.createElement( 'div' );
		row.className = 'tqb-review-row';

		var labelEl = document.createElement( 'span' );
		labelEl.className = 'tqb-review-row__label';
		labelEl.textContent = label;

		var valueEl = document.createElement( 'span' );
		valueEl.className = 'tqb-review-row__value';
		valueEl.textContent = value || '\u2014';

		row.appendChild( labelEl );
		row.appendChild( valueEl );
		return row;
	}

	// ---------------------------------------------------------------------
	// Live summary panel (right column) — client-side calculated preview
	// ---------------------------------------------------------------------

	function calculateLineAmount( item, qty ) {
		switch ( item.pricingPattern ) {
			case 'flat':
				return item.fee;
			case 'hardcoded':
				return item.hardcodedValue;
			case 'qty_times_fee':
			default:
				return qty * item.fee;
		}
	}

	/** Mirrors TQB_Pricing_Engine::calculate_individual(). */
	function calculateIndividualPreview( answers ) {
		return calculateIndividualPreviewForItems( tqbData.individualItems, answers );
	}

	function findBandByLabel( bands, label ) {
		return bands.filter( function ( b ) {
			return b.label === label;
		} )[ 0 ] || null;
	}

	/** Mirrors TQB_Pricing_Engine::calculate_business(). */
	function calculateBusinessPreview( entityType, assetBandLabel, revenueBandLabel, answers ) {
		var entityGroup = ( 'partnership' === entityType ) ? 'partnership' : 'c_s_corp';
		var assetBand = findBandByLabel( tqbData.assetBands[ entityGroup ] || [], assetBandLabel );
		var revenueBand = findBandByLabel( tqbData.revenueBands, revenueBandLabel );

		var extrasResult = calculateIndividualPreviewForItems( tqbData.businessItems, answers );

		if ( ! assetBand || ! revenueBand ) {
			return { total: null, baseFee: null, lineItems: extrasResult.lineItems, isCustomQuote: false, incomplete: true };
		}

		if ( assetBand.isCustom ) {
			return { total: null, baseFee: null, lineItems: extrasResult.lineItems, isCustomQuote: true };
		}

		var assetThreshold = ( 'partnership' === entityGroup ) ? SCHEDULE_L_ASSET_THRESHOLD_PARTNERSHIP : SCHEDULE_L_ASSET_THRESHOLD_C_S_CORP;
		var scheduleLNotRequired =
			assetBand.bandMax !== null && assetBand.bandMax <= assetThreshold &&
			revenueBand.bandMax !== null && revenueBand.bandMax <= SCHEDULE_L_REVENUE_THRESHOLD;

		var baseFee = scheduleLNotRequired ? SCHEDULE_L_FLAT_FEE : ( assetBand.price + revenueBand.price );

		if ( extrasResult.isCustomQuote ) {
			return { total: null, baseFee: baseFee, lineItems: extrasResult.lineItems, isCustomQuote: true };
		}

		return {
			total: baseFee + extrasResult.total,
			baseFee: baseFee,
			lineItems: extrasResult.lineItems,
			isCustomQuote: false,
		};
	}

	function calculateIndividualPreviewForItems( items, answers ) {
		var total = 0;
		var lineItems = [];
		var isCustomQuote = false;

		items.forEach( function ( item ) {
			var answer = answers[ item.key ];
			if ( ! answer || ! answer.selected ) {
				return;
			}
			if ( item.isCustomQuoteTrigger ) {
				isCustomQuote = true;
				return;
			}
			var qty = ( typeof answer.qty === 'number' ) ? answer.qty : 1;
			var amount = calculateLineAmount( item, qty );
			total += amount;
			lineItems.push( {
				label: item.label,
				amount: amount,
				qty: item.showQty ? qty : null,
			} );
		} );

		return { total: total, lineItems: lineItems, isCustomQuote: isCustomQuote };
	}

	function updateSummaryPanel() {
		var content = document.getElementById( 'tqb-summary-content' );
		content.innerHTML = '';

		if ( ! state.quoteType ) {
			var empty = document.createElement( 'p' );
			empty.className = 'tqb-summary__empty';
			empty.textContent = 'Your selections will appear here as you go.';
			content.appendChild( empty );
			return;
		}

		var typeBadge = document.createElement( 'div' );
		typeBadge.className = 'tqb-summary__type';
		typeBadge.textContent = ( 'business' === state.quoteType ) ? 'Business Return' : 'Individual Return';
		content.appendChild( typeBadge );

		var answers = collectAnswers();
		var result;

		if ( 'business' === state.quoteType ) {
			var entityEl = document.getElementById( 'tqb-entity-type' );
			var assetEl = document.getElementById( 'tqb-asset-band' );
			var revenueEl = document.getElementById( 'tqb-revenue-band' );

			if ( entityEl && assetEl && revenueEl ) {
				result = calculateBusinessPreview( entityEl.value, assetEl.value, revenueEl.value, answers );

				// Business details block — the three dropdown selections at
				// the top of Step 3, shown here so the user can confirm them
				// without scrolling back up.
				var basicsWrap = document.createElement( 'div' );
				basicsWrap.className = 'tqb-summary__basics';

				var entityLabel = entityEl.options[ entityEl.selectedIndex ] ? entityEl.options[ entityEl.selectedIndex ].textContent : '';
				[
					[ 'Business type', entityLabel ],
					[ 'Total assets', assetEl.value ],
					[ 'Annual revenue', revenueEl.value ],
				].forEach( function ( pair ) {
					if ( ! pair[ 1 ] ) {
						return;
					}
					var row = document.createElement( 'div' );
					row.className = 'tqb-summary__item';
					var label = document.createElement( 'span' );
					label.textContent = pair[ 0 ];
					var value = document.createElement( 'span' );
					value.className = 'tqb-summary__item-amount';
					value.textContent = pair[ 1 ];
					row.appendChild( label );
					row.appendChild( value );
					basicsWrap.appendChild( row );
				} );
				content.appendChild( basicsWrap );
			} else {
				result = { total: null, lineItems: [], isCustomQuote: false, incomplete: true };
			}
		} else {
			result = calculateIndividualPreview( answers );
		}

		// Base return fee — shown as its own line for Business so the total
		// doesn't look like it's only adding up the extras (client feedback:
		// summary was "missing" the base fee, i.e. it was implicit before).
		if ( 'business' === state.quoteType && result.baseFee !== null && typeof result.baseFee !== 'undefined' ) {
			var baseRow = document.createElement( 'div' );
			baseRow.className = 'tqb-summary__item';
			var baseLabel = document.createElement( 'span' );
			baseLabel.textContent = 'Base Return Fee';
			var baseAmount = document.createElement( 'span' );
			baseAmount.className = 'tqb-summary__item-amount';
			baseAmount.textContent = formatCurrency( result.baseFee );
			baseRow.appendChild( baseLabel );
			baseRow.appendChild( baseAmount );
			content.appendChild( baseRow );
		}

		if ( result.lineItems && result.lineItems.length ) {
			result.lineItems.forEach( function ( li ) {
				var row = document.createElement( 'div' );
				row.className = 'tqb-summary__item';

				var label = document.createElement( 'span' );
				label.textContent = li.label + ( li.qty && li.qty !== 1 ? ' (\u00d7' + li.qty + ')' : '' );

				var amount = document.createElement( 'span' );
				amount.className = 'tqb-summary__item-amount';
				amount.textContent = formatCurrency( li.amount );

				row.appendChild( label );
				row.appendChild( amount );
				content.appendChild( row );
			} );
		}

		if ( result.isCustomQuote ) {
			var note = document.createElement( 'div' );
			note.className = 'tqb-summary__custom-note';
			note.textContent = 'Based on your answers so far, this will likely need a custom quote rather than an instant price.';
			content.appendChild( note );
		} else if ( ! result.incomplete ) {
			var totalRow = document.createElement( 'div' );
			totalRow.className = 'tqb-summary__total';

			var totalLabel = document.createElement( 'span' );
			totalLabel.className = 'tqb-summary__total-label';
			totalLabel.textContent = 'Estimated Total';

			var totalAmount = document.createElement( 'span' );
			totalAmount.className = 'tqb-summary__total-amount';
			totalAmount.textContent = formatCurrency( result.total );

			totalRow.appendChild( totalLabel );
			totalRow.appendChild( totalAmount );
			content.appendChild( totalRow );
		}
	}

	// ---------------------------------------------------------------------
	// Submit (fires from the Review step)
	// ---------------------------------------------------------------------

	function submitQuote() {
		var errorEl = document.getElementById( 'tqb-form-error' );
		errorEl.hidden = true;

		var submitBtn = wizard.querySelector( '[data-step="4"] [data-action="submit"]' );
		var labelEl = submitBtn.querySelector( '.tqb-btn__label' );
		var spinner = submitBtn.querySelector( '.tqb-btn__spinner' );
		submitBtn.disabled = true;
		spinner.hidden = false;
		labelEl.textContent = 'Calculating\u2026';

		var answers = collectAnswers();

		var body = new URLSearchParams();
		body.append( 'action', 'tqb_submit_quote' );
		body.append( 'nonce', tqbData.nonce );
		body.append( 'quote_type', state.quoteType );
		body.append( 'contact_name', document.getElementById( 'tqb-contact-name' ).value );
		body.append( 'contact_email', document.getElementById( 'tqb-contact-email' ).value );
		body.append( 'contact_phone', document.getElementById( 'tqb-contact-phone' ).value );

		Object.keys( answers ).forEach( function ( itemKey ) {
			body.append( 'answers[' + itemKey + '][selected]', answers[ itemKey ].selected ? '1' : '' );
			body.append( 'answers[' + itemKey + '][qty]', answers[ itemKey ].qty );
		} );

		if ( 'business' === state.quoteType ) {
			body.append( 'entity_type', document.getElementById( 'tqb-entity-type' ).value );
			body.append( 'asset_band', document.getElementById( 'tqb-asset-band' ).value );
			body.append( 'revenue_band', document.getElementById( 'tqb-revenue-band' ).value );
		}

		fetch( tqbData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( response ) {
				// Handle rate limiting (429 Too Many Requests)
				if ( response.status === 429 ) {
					return response.json().then( function ( data ) {
						var err = new Error( data.data && data.data.message || 'Too many requests. Please wait before trying again.' );
						err.rateLimited = true;
						throw err;
					} );
				}
				return response.json();
			} )
			.then( function ( json ) {
				submitBtn.disabled = false;
				spinner.hidden = true;
				labelEl.textContent = 'Get My Quote';

				if ( ! json.success ) {
					errorEl.textContent = ( json.data && json.data.message ) || 'Something went wrong. Please try again.';
					errorEl.hidden = false;
					return;
				}

				renderResult( json.data );
				goToStep( STEP.RESULT );
			} )
			.catch( function ( err ) {
				submitBtn.disabled = false;
				spinner.hidden = true;
				labelEl.textContent = 'Get My Quote';
				
				if ( err && err.rateLimited ) {
					errorEl.textContent = err.message;
				} else {
					errorEl.textContent = 'We couldn\u2019t reach the server. Please check your connection and try again.';
				}
				errorEl.hidden = false;
			} );
	}

	// ---------------------------------------------------------------------
	// Step 5: result rendering
	// ---------------------------------------------------------------------

	function renderResult( data ) {
		var container = document.getElementById( 'tqb-result-content' );
		container.innerHTML = '';

		if ( data.isCustomQuote ) {
			container.className = 'tqb-result tqb-result--custom';

			var eyebrow = document.createElement( 'div' );
			eyebrow.className = 'tqb-result__eyebrow';
			eyebrow.textContent = 'Next Step';
			container.appendChild( eyebrow );

			var title = document.createElement( 'h2' );
			title.className = 'tqb-result__custom-title';
			title.textContent = 'A custom proposal is required';
			container.appendChild( title );

			var body = document.createElement( 'p' );
			body.className = 'tqb-result__custom-body';
			body.textContent = 'Based on your answers, your situation needs a closer look before we can quote a price. Someone from our team will follow up \u2014 or you can grab time on our calendar now.';
			container.appendChild( body );

			if ( data.schedulingLink ) {
				var link = document.createElement( 'a' );
				link.href = data.schedulingLink;
				link.className = 'tqb-btn tqb-btn--primary';
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				link.textContent = 'Schedule a Call';
				container.appendChild( link );
			}
		} else {
			container.className = 'tqb-result';

			var eyebrow2 = document.createElement( 'div' );
			eyebrow2.className = 'tqb-result__eyebrow';
			eyebrow2.textContent = 'Your Estimated Quote';
			container.appendChild( eyebrow2 );

			var amount = document.createElement( 'div' );
			amount.className = 'tqb-result__amount';
			amount.textContent = formatCurrency( data.total );
			container.appendChild( amount );

			var disclaimer = document.createElement( 'div' );
			disclaimer.className = 'tqb-result__disclaimer';
			disclaimer.textContent = data.disclaimer;
			container.appendChild( disclaimer );

			var next = document.createElement( 'p' );
			next.className = 'tqb-result__next';
			next.textContent = 'A confirmation has been sent to your email. Someone from our team will follow up shortly.';
			container.appendChild( next );
		}

		var againBtn = document.createElement( 'button' );
		againBtn.type = 'button';
		againBtn.className = 'tqb-btn tqb-btn--ghost';
		againBtn.style.marginTop = '20px';
		againBtn.textContent = 'Get Another Quote';
		againBtn.addEventListener( 'click', function () {
			resetAll();
		} );
		container.appendChild( againBtn );
	}

	function formatCurrency( amount ) {
		var num = parseFloat( amount ) || 0;
		return '$' + num.toLocaleString( 'en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
	}

	updateSummaryPanel();
} )();
