/**
 * Tavola Quote Builder — front-end wizard logic (FULLY REBUILT)
 * 
 * Fixed step numbering: Step 2 is now FILING_STATUS (was missing)
 * - Step 1: Return Type selection
 * - Step 2: Filing Status (for individual returns only)
 * - Step 3: Contact Info
 * - Step 4: Questions (personal or business)
 * - Step 5: Review all answers
 * - Step 6: Display quote or custom quote message
 * 
 * New features in this rebuild:
 * - Help text rendering from database (tooltip field)
 * - Conditional question reveal (reveal_followup logic)
 * - Business name field capture
 * - Custom quote routing for thresholds (crypto, foreign accounts, etc.)
 * - Proper filing status pricing in summary panel
 */
( function () {
	'use strict';

	if ( typeof tqbData === 'undefined' ) {
		console.error( 'tqbData not defined. Plugin may not have enqueued properly.' );
		return;
	}

	var wizard = document.getElementById( 'tqb-wizard' );
	if ( ! wizard ) {
		return;
	}

	// =====================================================================
	// STEP CONSTANTS (FIXED: Step 2 is now FILING_STATUS)
	// =====================================================================
	var STEP = {
		TYPE: 1,
		FILING_STATUS: 2,  // NEW: Added filing status step
		CONTACT: 3,        // Was 2
		QUESTIONS: 4,      // Was 3
		REVIEW: 5,         // Was 4
		RESULT: 6          // Was 5
	};

	// Asset thresholds for Schedule L (Business)
	var SCHEDULE_L_ASSET_THRESHOLD_C_S_CORP = 250000;
	var SCHEDULE_L_ASSET_THRESHOLD_PARTNERSHIP = 1000000;
	var SCHEDULE_L_REVENUE_THRESHOLD = 250000;

	// State for multi-select + multiple businesses + multiple individual filers
	var state = {
		selectedTypes: [],           // ['individual', 'business', 'business', ...]
		filingStatus: null,          // Primary filer's status (Step 2) — mirrored into individualFilingStatuses[0]
		individualCount: 0,          // Number of personal filers (1 once Individual is selected, +1 per "Add Another Personal Return")
		individualNames: {},         // { individualIndex: 'name', ... } — optional, mainly shown for index > 0
		individualFilingStatuses: {},// { individualIndex: 'single|mfj|mfs|hoh', ... } — index 0 mirrors state.filingStatus
		businessCount: 0,            // Number of businesses selected
		businessNames: {},           // { businessIndex: 'name', ... }
		businessTypes: {},           // { businessIndex: 'c_corp|s_corp|partnership', ... }
		businessAssetBands: {},      // { businessIndex: 'band label', ... }
		businessRevenueBands: {},    // { businessIndex: 'band label', ... }
		contactName: null,
		contactEmail: null,
		contactPhone: null,
		answers: {},                 // { questionKey: { answer, quantity, ... }, ... }
		completedSteps: [],
		partialSubmissionId: null,
		summaryNeedsUpdate: true
	};

	var ENTITY_OPTIONS = [
		{ value: 'c_corp', label: 'C-Corporation (Form 1120)' },
		{ value: 's_corp', label: 'S-Corporation (Form 1120-S)' },
		{ value: 'partnership', label: 'Partnership (Form 1065)' }
	];

	// =====================================================================
	// STEP NAVIGATION
	// =====================================================================
	function goToStep( stepNumber ) {
		clearError();

		var steps = wizard.querySelectorAll( '.tqb-step' );
		var stepLabels = {
			1: 'Return Type Selection',
			2: 'Filing Status',          // NEW
			3: 'Contact Information',    // Was 2
			4: 'Filing Details',         // Was 3
			5: 'Review Your Quote',      // Was 4
			6: 'Your Quote Results'      // Was 5
		};

		steps.forEach( function ( section ) {
			var isTarget = parseInt( section.getAttribute( 'data-step' ), 10 ) === stepNumber;
			section.hidden = ! isTarget;
			
			if ( isTarget ) {
				section.setAttribute( 'aria-current', 'step' );
			} else {
				section.removeAttribute( 'aria-current' );
			}
		} );

		var indicators = wizard.querySelectorAll( '.tqb-progress__step' );
		indicators.forEach( function ( indicator ) {
			var indicatorStep = parseInt( indicator.getAttribute( 'data-step-indicator' ), 10 );
			var isPrevStep = indicatorStep < stepNumber;
			var isCurrentStep = indicatorStep === stepNumber;
			
			indicator.classList.toggle( 'is-active', isCurrentStep );
			indicator.classList.toggle( 'is-complete', isPrevStep );
			
			if ( isCurrentStep ) {
				indicator.setAttribute( 'aria-current', 'step' );
			} else {
				indicator.removeAttribute( 'aria-current' );
			}
		} );

		wizard.setAttribute( 'data-step', stepNumber );

		// Once the final result step is reached, the whole progress bar
		// visually locks (dimmed, non-interactive cursor) — matches the click
		// blocking already enforced in setupProgressBarNavigation().
		var progressBar = wizard.querySelector( '.tqb-progress' );
		if ( progressBar ) {
			progressBar.classList.toggle( 'is-locked', stepNumber === STEP.RESULT );
		}

		window.scrollTo( { top: 0, behavior: 'smooth' } );
	}

	// =====================================================================
	// STEP 1: RETURN TYPE SELECTION
	// =====================================================================
	function setupStep1ReturnType() {
		var checkboxes = wizard.querySelectorAll( '.tqb-quote-type-checkbox' );
		var continueBtn = wizard.querySelector( '[data-step="1"] [data-action="start-quote"]' );

		checkboxes.forEach( function ( checkbox ) {
			checkbox.addEventListener( 'change', function () {
				updateReturnTypeSelection();
			} );
		} );

		continueBtn.addEventListener( 'click', function () {
			if ( state.selectedTypes.length === 0 ) {
				showError( 'Please select at least one return type.', '.tqb-type-card' );
				return;
			}

			// Show filing status step only if individual is selected
			var filingStatusStep = document.getElementById( 'tqb-filing-status-step' );
			if ( state.selectedTypes.includes( 'individual' ) ) {
				filingStatusStep.hidden = false;
				goToStep( STEP.FILING_STATUS );
			} else {
				// Skip filing status if only business
				filingStatusStep.hidden = true;
				goToStep( STEP.CONTACT );
			}
		} );
	}

	function updateReturnTypeSelection() {
		var checkboxes = wizard.querySelectorAll( '.tqb-quote-type-checkbox' );
		var continueBtn = wizard.querySelector( '[data-action="start-quote"]' );

		state.selectedTypes = [];
		state.businessCount = 0;
		state.individualCount = 0;

		checkboxes.forEach( function ( checkbox ) {
			var card = checkbox.closest( '.tqb-type-card' );
			if ( checkbox.checked ) {
				state.selectedTypes.push( checkbox.value );
				if ( checkbox.value === 'business' ) {
					state.businessCount++;
				}
				if ( checkbox.value === 'individual' ) {
					state.individualCount = 1;
				}
				if ( card ) {
					card.classList.add( 'is-selected' );
				}
			} else if ( card ) {
				card.classList.remove( 'is-selected' );
			}
		} );

		continueBtn.disabled = state.selectedTypes.length === 0;
		state.summaryNeedsUpdate = true;
		updateSummaryPanel();
	}

	// =====================================================================
	// INITIALIZE FILING STATUS PRICES FROM DATABASE
	// =====================================================================
	function initializeFilingStatusPrices() {
		if ( ! tqbData.filing_status_prices ) {
			return;
		}

		var statusMap = {
			'single': 'single',
			'mfj': 'mfj',
			'mfs': 'mfs',
			'hoh': 'hoh'
		};

		Object.keys( statusMap ).forEach( function ( statusKey ) {
			var dbKey = statusMap[ statusKey ];
			var price = tqbData.filing_status_prices[ dbKey ];
			var priceElement = wizard.querySelector( '[for="tqb-filing-' + statusKey + '"] .tqb-filing-status-price' );
			
			if ( priceElement && price !== undefined ) {
				priceElement.textContent = '$' + price.toFixed( 2 );
			}
		} );
	}

	// =====================================================================
	// PROGRESS BAR: CLICKABLE BACKWARDS ONLY
	// =====================================================================
	function setupProgressBarNavigation() {
		var indicators = wizard.querySelectorAll( '.tqb-progress__step' );
		
		indicators.forEach( function ( indicator ) {
			indicator.style.cursor = 'pointer';
			indicator.addEventListener( 'click', function () {
				var targetStep = parseInt( indicator.getAttribute( 'data-step-indicator' ), 10 );
				var currentStep = parseInt( wizard.getAttribute( 'data-step' ), 10 );

				// Once the quote has been submitted (on the final result step),
				// the form is done — no navigating anywhere, forward or back.
				if ( currentStep === STEP.RESULT ) {
					return;
				}

				// Only allow going backward (to lower step numbers)
				if ( targetStep < currentStep ) {
					goToStep( targetStep );
				}
				// Disable clicking forward
			} );
		} );
	}

	// =====================================================================
	// STEP 2: FILING STATUS (NEW)
	// =====================================================================
	function setupStep2FilingStatus() {
		var radios = wizard.querySelectorAll( '.tqb-filing-status-radio' );
		var continueBtn = wizard.querySelector( '[data-step="2"] [data-action="to-contact"]' );
		var backBtn = wizard.querySelector( '[data-step="2"] [data-action="back"]' );

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				state.filingStatus = this.value;
				state.individualFilingStatuses[ 0 ] = this.value; // keep in sync for the multi-instance loop
				continueBtn.disabled = false;
				state.summaryNeedsUpdate = true;
				updateSummaryPanel();
			} );
		} );

		continueBtn.addEventListener( 'click', function () {
			if ( ! state.filingStatus ) {
				showError( 'Please select a filing status.', '.tqb-filing-status-card' );
				return;
			}

			if ( ! state.completedSteps.includes( STEP.FILING_STATUS ) ) {
				state.completedSteps.push( STEP.FILING_STATUS );
			}

			savePartialProgress( STEP.FILING_STATUS );
			goToStep( STEP.CONTACT );
		} );

		backBtn.addEventListener( 'click', function () {
			state.completedSteps = state.completedSteps.filter( function ( s ) { return s !== STEP.FILING_STATUS; } );
			goToStep( STEP.TYPE );
		} );
	}

	// =====================================================================
	// STEP 3: CONTACT INFO (WAS STEP 2)
	// =====================================================================
	function setupStep3ContactInfo() {
		var nameInput = wizard.querySelector( '#tqb-contact-name' );
		var emailInput = wizard.querySelector( '#tqb-contact-email' );
		var phoneInput = wizard.querySelector( '#tqb-contact-phone' );
		var continueBtn = wizard.querySelector( '[data-step="3"] [data-action="to-questions"]' );
		var backBtn = wizard.querySelector( '[data-step="3"] [data-action="back"]' );

		[ nameInput, emailInput, phoneInput ].forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				validateContactInfo();
			} );
		} );

		continueBtn.addEventListener( 'click', function () {
			if ( ! validateContactInfo() ) {
				return;
			}

			state.contactName = nameInput.value.trim();
			state.contactEmail = emailInput.value.trim();
			state.contactPhone = phoneInput.value.trim();

			if ( ! state.completedSteps.includes( STEP.CONTACT ) ) {
				state.completedSteps.push( STEP.CONTACT );
			}

			savePartialProgress( STEP.CONTACT );
			buildQuestionsStep();
			goToStep( STEP.QUESTIONS );
		} );

		backBtn.addEventListener( 'click', function () {
			state.completedSteps = state.completedSteps.filter( function ( s ) { return s !== STEP.CONTACT; } );
			if ( state.selectedTypes.includes( 'individual' ) ) {
				goToStep( STEP.FILING_STATUS );
			} else {
				goToStep( STEP.TYPE );
			}
		} );
	}

	function validateContactInfo() {
		var nameInput = wizard.querySelector( '#tqb-contact-name' );
		var emailInput = wizard.querySelector( '#tqb-contact-email' );
		var phoneInput = wizard.querySelector( '#tqb-contact-phone' );

		if ( ! nameInput.value.trim() ) {
			showError( 'Please enter your full name.', '#tqb-contact-name' );
			return false;
		}
		if ( ! emailInput.value.trim() || ! emailInput.value.includes( '@' ) ) {
			showError( 'Please enter a valid email address.', '#tqb-contact-email' );
			return false;
		}
		if ( ! phoneInput.value.trim() ) {
			showError( 'Please enter your phone number.', '#tqb-contact-phone' );
			return false;
		}

		clearError();
		return true;
	}

	// =====================================================================
	// STEP 4: BUILD QUESTIONS DYNAMICALLY (WAS STEP 3)
	// =====================================================================
	function buildQuestionsStep() {
		var container = document.getElementById( 'tqb-question-sections' );
		if ( ! container ) return;

		container.innerHTML = '';

		// Build individual section if selected (currently single-instance only
		// — "additional personal returns" removed for now, per request)
		if ( state.selectedTypes.includes( 'individual' ) ) {
			for ( var p = 0; p < state.individualCount; p++ ) {
				container.appendChild( buildIndividualQuestionsSection( container, p ) );
			}
		}

		// Build business sections if selected
		if ( state.selectedTypes.includes( 'business' ) ) {
			for ( var i = 0; i < state.businessCount; i++ ) {
				buildBusinessQuestionsSection( container, i );
			}
		}

		setupAddBusinessButton( container );
	}


	// =====================================================================
	// Wires the "+ Add Another Business" button. Reusable so it can be
	// (re)wired both on normal step build and when business gets added
	// mid-flow via the personal section's routing checkbox.
	// =====================================================================
	function setupAddBusinessButton( container ) {
		var addBusinessBtn = wizard.querySelector( '[data-action="add-business"]' );
		var addBusinessSection = document.getElementById( 'tqb-add-business-section' );

		if ( state.selectedTypes.includes( 'business' ) ) {
			addBusinessSection.hidden = false;

			// Replace the button with a clone to strip any listeners from a
			// previous wiring — otherwise listeners stack and one click adds
			// multiple businesses at once.
			var freshBtn = addBusinessBtn.cloneNode( true );
			addBusinessBtn.parentNode.replaceChild( freshBtn, addBusinessBtn );
			addBusinessBtn = freshBtn;

			addBusinessBtn.addEventListener( 'click', function () {
				var newIndex = state.businessCount;
				state.businessCount++;
				buildBusinessQuestionsSection( container, newIndex );
				state.summaryNeedsUpdate = true;
				updateSummaryPanel();
			} );
		} else {
			addBusinessSection.hidden = true;
		}
	}

	function buildIndividualQuestionsSection( container, individualIndex ) {
		var section = document.createElement( 'div' );
		section.className = 'tqb-question-section';
		section.id = 'tqb-individual-section-' + individualIndex;

		var header = document.createElement( 'h3' );
		header.textContent = individualIndex === 0 ? 'Personal Tax Return' : 'Additional Personal Return #' + ( individualIndex + 1 );
		section.appendChild( header );

		// Additional filers (index > 0) get their own Name + Filing Status
		// fields, mirroring the Business Name + Entity Type fields on
		// additional businesses. The primary filer (index 0) already gave
		// their name in Step 3 and filing status in Step 2, so those fields
		// are skipped here for them.
		if ( individualIndex > 0 ) {
			var nameField = document.createElement( 'div' );
			nameField.className = 'tqb-field';
			nameField.innerHTML =
				'<label for="tqb-individual-name-' + individualIndex + '">Filer Name</label>' +
				'<input type="text" id="tqb-individual-name-' + individualIndex + '" class="tqb-individual-name-input" placeholder="Enter this filer\u2019s name" />';
			section.appendChild( nameField );

			var nameInput = nameField.querySelector( '.tqb-individual-name-input' );
			nameInput.addEventListener( 'input', function () {
				state.individualNames[ individualIndex ] = this.value;
				state.summaryNeedsUpdate = true;
				updateSummaryPanel();
			} );

			var filingDiv = document.createElement( 'div' );
			filingDiv.className = 'tqb-field';
			var filingLabel = document.createElement( 'label' );
			filingLabel.htmlFor = 'tqb-individual-filing-' + individualIndex;
			filingLabel.textContent = 'Filing Status';
			var filingSelect = document.createElement( 'select' );
			filingSelect.id = 'tqb-individual-filing-' + individualIndex;
			filingSelect.className = 'tqb-individual-filing-select';

			var filingPlaceholder = document.createElement( 'option' );
			filingPlaceholder.value = '';
			filingPlaceholder.textContent = '-- Select filing status --';
			filingSelect.appendChild( filingPlaceholder );

			[ 'single', 'mfj', 'mfs', 'hoh' ].forEach( function ( statusKey ) {
				var option = document.createElement( 'option' );
				option.value = statusKey;
				option.textContent = ( tqbData.filing_status_labels && tqbData.filing_status_labels[ statusKey ] ) || statusKey;
				filingSelect.appendChild( option );
			} );

			filingSelect.addEventListener( 'change', function () {
				state.individualFilingStatuses[ individualIndex ] = this.value;
				// Rebuild this section's questions to reflect the newly chosen
				// filing status's filtering, same as changing Step 2 would.
				var oldSection = document.getElementById( 'tqb-individual-section-' + individualIndex );
				if ( oldSection ) {
					var newSection = buildIndividualQuestionsSection( container, individualIndex );
					oldSection.parentNode.replaceChild( newSection, oldSection );
				}
				state.summaryNeedsUpdate = true;
				updateSummaryPanel();
			} );

			filingDiv.appendChild( filingLabel );
			filingDiv.appendChild( filingSelect );
			section.appendChild( filingDiv );
		}

		var resolvedFilingStatus = state.individualFilingStatuses[ individualIndex ] || ( individualIndex === 0 ? state.filingStatus : null );

		// Get individual questions from tqbData, filtered by this instance's
		// own filing status (not necessarily the primary filer's).
		var questions = tqbData.questions.filter( function ( q ) {
			if ( q.quote_type !== 'individual' ) return false;

			// Filter by filing status
			// Empty/null filing_status = show for ALL
			// 'single' = show only for single filers
			// 'mfj' = show only for MFJ filers
			// 'mfs' = show only for MFS filers
			// 'hoh' = show only for HOH filers
			if ( q.filing_status && q.filing_status !== '' ) {
				if ( q.filing_status !== resolvedFilingStatus ) {
					return false; // Hide if doesn't match this filer's status
				}
			}

			return true;
		} );

		// Sort by the sort_order field in database
		questions.sort( function ( a, b ) {
			return (a.sort_order || 0) - (b.sort_order || 0);
		} );

		var contextKey = 'individual_' + individualIndex;

		questions.forEach( function ( question, index ) {
			var questionDiv = createQuestionElement( contextKey, question, index );
			section.appendChild( questionDiv );
		} );

		// Routing question — only offered on the primary filer's section, and
		// only when it's actually useful. If business is already selected on
		// Step 1, asking "do you also need a business quote?" here is
		// redundant and confusing, so it's hidden entirely in that case.
		if ( individualIndex === 0 && ! state.selectedTypes.includes( 'business' ) ) {
			var routingDiv = document.createElement( 'div' );
			routingDiv.className = 'tqb-routing-questions';
			routingDiv.innerHTML =
				'<p><strong>Do you need additional returns?</strong></p>' +
				'<label class="tqb-checkbox"><input type="checkbox" class="tqb-routing-checkbox" data-routing-type="business" /> Do you also need a quote for any business tax returns?</label>' +
				'<div class="tqb-routing-confirm" hidden>A business section has been added below \u2014 scroll down to fill it in.</div>';
			section.appendChild( routingDiv );

			var routingCheckbox = routingDiv.querySelector( '.tqb-routing-checkbox' );
			var confirmMsg = routingDiv.querySelector( '.tqb-routing-confirm' );

			routingCheckbox.addEventListener( 'change', function () {
				if ( this.checked ) {
					state.selectedTypes.push( 'business' );
					var newIndex = state.businessCount;
					state.businessCount++;
					buildBusinessQuestionsSection( container, newIndex );
					setupAddBusinessButton( container );

					// Visible confirmation so checking the box has an obvious
					// effect, since the new section appears further down the
					// page and could otherwise go unnoticed.
					confirmMsg.hidden = false;
					this.disabled = true; // one-way: use "Add Another Business" below for more
				}
				state.summaryNeedsUpdate = true;
				updateSummaryPanel();
			} );
		}

		return section;
	}

	// =====================================================================
	// Populates the Total Assets dropdown based on selected entity type.
	// C-Corp and S-Corp share one band set (c_s_corp); Partnership has its own.
	// =====================================================================
	function populateAssetBandOptions( businessIndex, entityType ) {
		var assetSelect = wizard.querySelector( '#tqb-asset-band-' + businessIndex );
		if ( ! assetSelect ) return;

		assetSelect.innerHTML = '';

		if ( ! entityType || ! tqbData.assetBands ) {
			var placeholder = document.createElement( 'option' );
			placeholder.value = '';
			placeholder.textContent = '-- Select entity type first --';
			assetSelect.appendChild( placeholder );
			assetSelect.disabled = true;
			return;
		}

		var group = ( entityType === 'partnership' ) ? 'partnership' : 'c_s_corp';
		var bands = tqbData.assetBands[ group ] || [];

		var placeholder = document.createElement( 'option' );
		placeholder.value = '';
		placeholder.textContent = '-- Select total assets --';
		assetSelect.appendChild( placeholder );

		bands.forEach( function ( band ) {
			var option = document.createElement( 'option' );
			option.value = band.label;
			option.textContent = band.label;
			assetSelect.appendChild( option );
		} );

		assetSelect.disabled = false;

		// Clear any previously selected band that no longer applies (entity type changed)
		state.businessAssetBands[ businessIndex ] = '';
	}

	function buildBusinessQuestionsSection( container, businessIndex ) {
		var section = document.createElement( 'div' );
		section.className = 'tqb-question-section';
		section.id = 'tqb-business-section-' + businessIndex;

		var header = document.createElement( 'h3' );
		header.textContent = businessIndex === 0 ? 'Business Information' : 'Additional Business #' + (businessIndex + 1);
		section.appendChild( header );

		// Business name input (appears first)
		var nameField = document.createElement( 'div' );
		nameField.className = 'tqb-field';
		nameField.innerHTML = 
			'<label for="tqb-business-name-' + businessIndex + '">Business Name</label>' +
			'<input type="text" id="tqb-business-name-' + businessIndex + '" class="tqb-business-name-input" data-business-index="' + businessIndex + '" placeholder="Enter your business name" />';
		section.appendChild( nameField );

		var nameInput = nameField.querySelector( '.tqb-business-name-input' );
		nameInput.addEventListener( 'input', function () {
			state.businessNames[ businessIndex ] = this.value;
			state.summaryNeedsUpdate = true;
			updateSummaryPanel();
		} );

		// Entity type dropdown
		var entityDiv = document.createElement( 'div' );
		entityDiv.className = 'tqb-field';
		var entityLabel = document.createElement( 'label' );
		entityLabel.htmlFor = 'tqb-entity-type-' + businessIndex;
		entityLabel.textContent = 'Business Entity Type';
		var entitySelect = document.createElement( 'select' );
		entitySelect.id = 'tqb-entity-type-' + businessIndex;
		entitySelect.className = 'tqb-entity-select';
		entitySelect.setAttribute( 'data-business-index', businessIndex );

		var optionDefault = document.createElement( 'option' );
		optionDefault.value = '';
		optionDefault.textContent = '-- Select entity type --';
		entitySelect.appendChild( optionDefault );

		ENTITY_OPTIONS.forEach( function ( opt ) {
			var option = document.createElement( 'option' );
			option.value = opt.value;
			option.textContent = opt.label;
			entitySelect.appendChild( option );
		} );

		entitySelect.addEventListener( 'change', function () {
			state.businessTypes[ businessIndex ] = this.value;
			populateAssetBandOptions( businessIndex, this.value );
			state.summaryNeedsUpdate = true;
			updateSummaryPanel();
		} );

		entityDiv.appendChild( entityLabel );
		entityDiv.appendChild( entitySelect );
		section.appendChild( entityDiv );

		// Total Assets dropdown — options depend on entity type (populated/repopulated on change above)
		var assetDiv = document.createElement( 'div' );
		assetDiv.className = 'tqb-field';
		var assetLabel = document.createElement( 'label' );
		assetLabel.htmlFor = 'tqb-asset-band-' + businessIndex;
		assetLabel.textContent = 'Total Assets';
		var assetSelect = document.createElement( 'select' );
		assetSelect.id = 'tqb-asset-band-' + businessIndex;
		assetSelect.className = 'tqb-asset-band-select';
		assetSelect.setAttribute( 'data-business-index', businessIndex );
		assetSelect.disabled = true;

		var assetPlaceholder = document.createElement( 'option' );
		assetPlaceholder.value = '';
		assetPlaceholder.textContent = '-- Select entity type first --';
		assetSelect.appendChild( assetPlaceholder );

		assetSelect.addEventListener( 'change', function () {
			state.businessAssetBands[ businessIndex ] = this.value;
			state.summaryNeedsUpdate = true;
			updateSummaryPanel();
		} );

		assetDiv.appendChild( assetLabel );
		assetDiv.appendChild( assetSelect );
		section.appendChild( assetDiv );

		// Annual Revenue dropdown — shared bands, available immediately
		var revenueDiv = document.createElement( 'div' );
		revenueDiv.className = 'tqb-field';
		var revenueLabel = document.createElement( 'label' );
		revenueLabel.htmlFor = 'tqb-revenue-band-' + businessIndex;
		revenueLabel.textContent = 'Annual Revenue';
		var revenueSelect = document.createElement( 'select' );
		revenueSelect.id = 'tqb-revenue-band-' + businessIndex;
		revenueSelect.className = 'tqb-revenue-band-select';
		revenueSelect.setAttribute( 'data-business-index', businessIndex );

		var revenuePlaceholder = document.createElement( 'option' );
		revenuePlaceholder.value = '';
		revenuePlaceholder.textContent = '-- Select annual revenue --';
		revenueSelect.appendChild( revenuePlaceholder );

		( tqbData.revenueBands || [] ).forEach( function ( band ) {
			var option = document.createElement( 'option' );
			option.value = band.label;
			option.textContent = band.label;
			revenueSelect.appendChild( option );
		} );

		revenueSelect.addEventListener( 'change', function () {
			state.businessRevenueBands[ businessIndex ] = this.value;
			state.summaryNeedsUpdate = true;
			updateSummaryPanel();
		} );

		revenueDiv.appendChild( revenueLabel );
		revenueDiv.appendChild( revenueSelect );
		section.appendChild( revenueDiv );

		// Get business questions
		var questions = tqbData.questions.filter( function ( q ) {
			return q.quote_type === 'business';
		} );

		questions.sort( function ( a, b ) {
			return (a.sort_order || 0) - (b.sort_order || 0);
		} );

		questions.forEach( function ( question, index ) {
			var questionDiv = createQuestionElement( 'business_' + businessIndex, question, index );
			section.appendChild( questionDiv );
		} );

		container.appendChild( section );
	}

	function createQuestionElement( questionContext, question, questionIndex ) {
		var wrapper = document.createElement( 'div' );
		wrapper.className = 'tqb-question-row';
		wrapper.setAttribute( 'data-question-key', question.item_key );
		wrapper.setAttribute( 'data-question-context', questionContext );

		// Quantity field only makes sense when price = qty × fee
		var showQty = ( question.pricing_pattern === 'qty_times_fee' );

		// LEFT: checkbox + label + tooltip hint below label
		var mainDiv = document.createElement( 'div' );
		mainDiv.className = 'tqb-question-row__main';

		var checkbox = document.createElement( 'input' );
		checkbox.type = 'checkbox';
		checkbox.name = 'question_' + questionContext + '_' + question.item_key;
		checkbox.value = 'yes';
		checkbox.id = 'tqb-q-' + questionContext + '_' + question.item_key;
		checkbox.addEventListener( 'change', function () {
			onQuestionAnswered( questionContext, question, this.checked ? 'yes' : 'no' );
		} );
		mainDiv.appendChild( checkbox );

		var textWrap = document.createElement( 'div' );

		var questionLabel = document.createElement( 'label' );
		questionLabel.className = 'tqb-question-row__label';
		questionLabel.htmlFor = checkbox.id;
		questionLabel.textContent = question.label;
		textWrap.appendChild( questionLabel );

		if ( question.tooltip ) {
			var hintText = document.createElement( 'div' );
			hintText.className = 'tqb-question-row__note';
			hintText.textContent = question.tooltip;
			textWrap.appendChild( hintText );
		}

		mainDiv.appendChild( textWrap );
		wrapper.appendChild( mainDiv );

		// RIGHT: quantity field — only for qty_times_fee pricing pattern, always visible, disabled until checked
		if ( showQty ) {
			var qtyWrap = document.createElement( 'div' );
			qtyWrap.className = 'tqb-question-row__qty-wrap';

			var quantityInput = document.createElement( 'input' );
			quantityInput.type = 'number';
			quantityInput.id = 'tqb-quantity-' + questionContext + '_' + question.item_key;
			quantityInput.className = 'tqb-question-row__qty';
			quantityInput.min = '0';
			quantityInput.placeholder = '1';
			quantityInput.disabled = true;
			quantityInput.addEventListener( 'change', function () {
				onQuantityChanged( questionContext, question, this.value );
			} );

			qtyWrap.appendChild( quantityInput );
			wrapper.appendChild( qtyWrap );
		}

		return wrapper;
	}

	function onQuestionAnswered( questionContext, question, answer ) {
		var key = questionContext + '_' + question.item_key;
		state.answers[ key ] = {
			question_key: question.item_key,
			context: questionContext,
			answer: answer,
			quantity: 0
		};

		// Enable/disable quantity field (always visible, editable only when checked)
		if ( question.pricing_pattern === 'qty_times_fee' ) {
			var quantityInput = wizard.querySelector(
				'[data-question-context="' + questionContext + '"][data-question-key="' + question.item_key + '"] .tqb-question-row__qty'
			);
			if ( quantityInput ) {
				quantityInput.disabled = ( answer !== 'yes' );
				if ( answer === 'yes' && ! quantityInput.value ) {
					quantityInput.value = 1;
					state.answers[ key ].quantity = 1;
				} else if ( answer !== 'yes' ) {
					quantityInput.value = '';
				}
			}
		}

		state.summaryNeedsUpdate = true;
		updateSummaryPanel();
	}

	function onQuantityChanged( questionContext, question, quantity ) {
		var key = questionContext + '_' + question.item_key;
		if ( state.answers[ key ] ) {
			state.answers[ key ].quantity = parseInt( quantity, 10 ) || 0;
		}

		state.summaryNeedsUpdate = true;
		updateSummaryPanel();
	}

	// =====================================================================
	// STEP 4 NAVIGATION (WAS STEP 3)
	// =====================================================================
	function setupStep4QuestionsNav() {
		var backBtn = wizard.querySelector( '[data-step="4"] [data-action="back"]' );
		var reviewBtn = wizard.querySelector( '[data-step="4"] [data-action="to-review"]' );

		backBtn.addEventListener( 'click', function () {
			state.completedSteps = state.completedSteps.filter( function ( s ) { return s !== STEP.QUESTIONS; } );
			goToStep( STEP.CONTACT );
		} );

		reviewBtn.addEventListener( 'click', function () {
			// Validate that at least one question was answered
			if ( Object.keys( state.answers ).length === 0 ) {
				showError( 'Please answer at least one question before proceeding.' );
				return;
			}

			// Validate additional personal filers have a filing status selected
			// (index 0's status comes from Step 2 and is always present by then)
			if ( state.selectedTypes.includes( 'individual' ) ) {
				for ( var p = 1; p < state.individualCount; p++ ) {
					if ( ! state.individualFilingStatuses[ p ] ) {
						showError( 'Please select a filing status for each additional personal return before proceeding.', '#tqb-individual-filing-' + p );
						return;
					}
				}
			}

			// Validate business details are complete for every selected business
			if ( state.selectedTypes.includes( 'business' ) ) {
				for ( var b = 0; b < state.businessCount; b++ ) {
					if ( ! state.businessTypes[ b ] || ! state.businessAssetBands[ b ] || ! state.businessRevenueBands[ b ] ) {
						var missingSelectors = [];
						if ( ! state.businessTypes[ b ] ) missingSelectors.push( '#tqb-entity-type-' + b );
						if ( ! state.businessAssetBands[ b ] ) missingSelectors.push( '#tqb-asset-band-' + b );
						if ( ! state.businessRevenueBands[ b ] ) missingSelectors.push( '#tqb-revenue-band-' + b );
						showError( 'Please complete Business Entity Type, Total Assets, and Annual Revenue for each business before proceeding.', missingSelectors );
						return;
					}
				}
			}

			clearError();

			if ( ! state.completedSteps.includes( STEP.QUESTIONS ) ) {
				state.completedSteps.push( STEP.QUESTIONS );
			}

			savePartialProgress( STEP.QUESTIONS );
			buildReviewStep();
			goToStep( STEP.REVIEW );
		} );
	}

	// =====================================================================
	// STEP 5: REVIEW (WAS STEP 4)
	// =====================================================================
	function buildReviewStep() {
		var reviewContent = document.getElementById( 'tqb-review-content' );
		if ( ! reviewContent ) return;

		reviewContent.innerHTML = '';

		function addSection( title, rowsHtml ) {
			var section = document.createElement( 'div' );
			section.className = 'tqb-review-section';
			section.innerHTML = '<div class="tqb-review-section__title">' + escapeHtml( title ) + '</div>' + rowsHtml;
			reviewContent.appendChild( section );
		}

		function row( label, value ) {
			return '<div class="tqb-review-row"><span class="tqb-review-row__label">' + escapeHtml( label ) + '</span><span class="tqb-review-row__value">' + escapeHtml( value ) + '</span></div>';
		}

		// YOUR INFO
		addSection( 'Your Info', 
			row( 'Name', state.contactName || '' ) +
			row( 'Email', state.contactEmail || '' ) +
			row( 'Phone', state.contactPhone || '' )
		);

		// PERSONAL TAX RETURN(S) (shown first — matches fill order on the Details step)
		if ( state.selectedTypes.includes( 'individual' ) && state.individualCount > 0 ) {
			for ( var p = 0; p < state.individualCount; p++ ) {
				var pSuffix = p === 0 ? '' : ' #' + ( p + 1 );
				var pContextKey = 'individual_' + p;
				var personalFilingStatus = state.individualFilingStatuses[ p ];

				// Details for additional filers (name + filing status)
				var personalDetailRows = '';
				if ( p > 0 && state.individualNames[ p ] ) {
					personalDetailRows += row( 'Filer name', state.individualNames[ p ] );
				}
				if ( personalFilingStatus ) {
					var pFilingLabel = ( tqbData.filing_status_labels && tqbData.filing_status_labels[ personalFilingStatus ] ) || personalFilingStatus;
					personalDetailRows += row( 'Filing status', pFilingLabel );
				}
				if ( personalDetailRows !== '' ) {
					addSection( 'Personal Return' + pSuffix + ' Details', personalDetailRows );
				}

				var personalRows = '';
				Object.keys( state.answers ).forEach( function ( key ) {
					var answer = state.answers[ key ];
					if ( answer.context === pContextKey && answer.answer === 'yes' ) {
						var question = tqbData.questions.find( function ( q ) { return q.item_key === answer.question_key; } );
						if ( question ) {
							var valueText = 'Yes';
							if ( question.pricing_pattern === 'qty_times_fee' && answer.quantity > 0 ) {
								valueText += ' (Qty: ' + answer.quantity + ')';
							}
							personalRows += row( question.label, valueText );
						}
					}
				} );
				if ( personalRows !== '' ) {
					addSection( 'Personal Tax Return' + pSuffix, personalRows );
				}
			}
		}

		// BUSINESS SECTIONS
		if ( state.selectedTypes.includes( 'business' ) && state.businessCount > 0 ) {
			for ( var b = 0; b < state.businessCount; b++ ) {
				var suffix = state.businessCount > 1 ? ' #' + ( b + 1 ) : '';
				var contextKey = 'business_' + b;

				// Details (only fields we actually collect right now)
				var entityLabel = '';
				if ( state.businessTypes[ b ] ) {
					var found = ENTITY_OPTIONS.find( function ( opt ) { return opt.value === state.businessTypes[ b ]; } );
					entityLabel = found ? found.label : state.businessTypes[ b ];
				}
				var detailRows = '';
				if ( state.businessNames[ b ] ) {
					detailRows += row( 'Business name', state.businessNames[ b ] );
				}
				if ( entityLabel ) {
					detailRows += row( 'Business type', entityLabel );
				}
				if ( state.businessAssetBands[ b ] ) {
					detailRows += row( 'Total assets', state.businessAssetBands[ b ] );
				}
				if ( state.businessRevenueBands[ b ] ) {
					detailRows += row( 'Annual revenue', state.businessRevenueBands[ b ] );
				}
				if ( detailRows !== '' ) {
					addSection( 'Business' + suffix + ' Details', detailRows );
				}

				// Tax return answers for this business
				var bizRows = '';
				Object.keys( state.answers ).forEach( function ( key ) {
					var answer = state.answers[ key ];
					if ( answer.context === contextKey && answer.answer === 'yes' ) {
						var question = tqbData.questions.find( function ( q ) { return q.item_key === answer.question_key; } );
						if ( question ) {
							var valueText = 'Yes';
							if ( question.pricing_pattern === 'qty_times_fee' && answer.quantity > 0 ) {
								valueText += ' (Qty: ' + answer.quantity + ')';
							}
							bizRows += row( question.label, valueText );
						}
					}
				} );
				if ( bizRows !== '' ) {
					addSection( 'Business' + suffix + ' Tax Return', bizRows );
				}
			}
		}

		if ( reviewContent.innerHTML === '' ) {
			reviewContent.innerHTML = '<p class="tqb-review-empty">No answers to review yet.</p>';
		}
	}

	function setupStep5ReviewNav() {
		var backBtn = wizard.querySelector( '[data-step="5"] [data-action="back"]' );
		var submitBtn = wizard.querySelector( '[data-step="5"] [data-action="submit"]' );

		backBtn.addEventListener( 'click', function () {
			state.completedSteps = state.completedSteps.filter( function ( s ) { return s !== STEP.REVIEW; } );
			goToStep( STEP.QUESTIONS );
		} );

		submitBtn.addEventListener( 'click', function () {
			submitQuote( submitBtn );
		} );
	}

	// =====================================================================
	// SUBMIT AND QUOTE RESULT
	// =====================================================================
	function submitQuote( submitBtn ) {
		submitBtn.disabled = true;
		var spinner = submitBtn.querySelector( '.tqb-btn__spinner' );
		if ( spinner ) {
			spinner.hidden = false;
		}

		var params = new URLSearchParams();
		params.append( 'action', 'tqb_submit_quote' );
		params.append( 'nonce', tqbData.nonce );
		params.append( 'quote_types', JSON.stringify( state.selectedTypes ) );
		params.append( 'contact_name', state.contactName );
		params.append( 'contact_email', state.contactEmail );
		params.append( 'contact_phone', state.contactPhone );

		// Individuals (personal filers) — index 0 is the primary filer (name
		// falls back to the main contact name if left blank).
		params.append( 'individual_count', state.individualCount );
		for ( var p = 0; p < state.individualCount; p++ ) {
			params.append( 'individuals[' + p + '][name]', state.individualNames[ p ] || '' );
			params.append( 'individuals[' + p + '][filing_status]', state.individualFilingStatuses[ p ] || '' );
		}

		// Businesses — entity type, asset band, and revenue band are all
		// required by the server for every selected business.
		params.append( 'business_count', state.businessCount );
		for ( var b = 0; b < state.businessCount; b++ ) {
			params.append( 'businesses[' + b + '][name]', state.businessNames[ b ] || '' );
			params.append( 'businesses[' + b + '][entity_type]', state.businessTypes[ b ] || '' );
			params.append( 'businesses[' + b + '][asset_band]', state.businessAssetBands[ b ] || '' );
			params.append( 'businesses[' + b + '][revenue_band]', state.businessRevenueBands[ b ] || '' );
		}

		// Answers — transform from the internal live-preview shape
		// { context, question_key, answer, quantity } into the shape the
		// server expects: a flat map keyed by 'type-index-item_key' (hyphens,
		// matching TQB_Quote_Handler::filter_answers_with_prefix()) with
		// { selected, qty } values. Sent as a JSON string and decoded
		// server-side with json_decode() — NOT WordPress bracket-notation,
		// and NOT cast with (array), which silently discards it (a bug that
		// meant every submission's answers were empty until this was found
		// and fixed on both ends).
		var answersForSubmit = {};
		Object.keys( state.answers ).forEach( function ( key ) {
			var a = state.answers[ key ];
			var contextParts = a.context.split( '_' ); // 'individual_0' -> ['individual','0'], 'business_1' -> ['business','1']
			var type = contextParts[ 0 ];
			var idx = contextParts[ 1 ] || '0';
			var prefixedKey = type + '-' + idx + '-' + a.question_key;
			answersForSubmit[ prefixedKey ] = {
				selected: a.answer === 'yes',
				qty: a.quantity || 1
			};
		} );
		params.append( 'answers', JSON.stringify( answersForSubmit ) );

		fetch( tqbData.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			body: params
		} )
		.then( function ( response ) {
			return response.json();
		} )
		.then( function ( data ) {
			submitBtn.disabled = false;
			if ( spinner ) {
				spinner.hidden = true;
			}

			if ( ! data.success ) {
				showError( data.data.message || 'An error occurred. Please try again.' );
				return;
			}

			// Display result
			displayQuoteResult( data.data );
			goToStep( STEP.RESULT );
		} )
		.catch( function ( error ) {
			submitBtn.disabled = false;
			if ( spinner ) {
				spinner.hidden = true;
			}
			showError( 'Network error: ' + error.message );
		} );
	}

	function displayQuoteResult( data ) {
		var resultContent = document.getElementById( 'tqb-result-content' );
		if ( ! resultContent ) return;

		resultContent.innerHTML = '';

		var wrapper = document.createElement( 'div' );

		if ( data.isCustomQuote ) {
			wrapper.className = 'tqb-result tqb-result--custom';
			wrapper.innerHTML =
				'<div class="tqb-result__eyebrow">Custom Quote</div>' +
				'<h3 class="tqb-result__custom-title">Thank You!</h3>' +
				'<p class="tqb-result__custom-body">Based on your responses, your quote requires custom pricing. Our team will review your information and contact you within 24 hours with a personalized proposal.</p>' +
				'<p class="tqb-result__next">A confirmation email has been sent to <strong>' + escapeHtml( state.contactEmail ) + '</strong></p>';
		} else {
			var totalAmount = parseFloat( data.total ) || 0;
			wrapper.className = 'tqb-result';
			wrapper.innerHTML =
				'<div class="tqb-result__eyebrow">Your Quote</div>' +
				'<div class="tqb-result__amount">$' + totalAmount.toFixed( 2 ) + '</div>' +
				( data.disclaimer ? '<div class="tqb-result__disclaimer">' + escapeHtml( data.disclaimer ) + '</div>' : '' ) +
				'<p class="tqb-result__next">A confirmation email has been sent to <strong>' + escapeHtml( state.contactEmail ) + '</strong>. Our team will follow up with you soon to discuss next steps.</p>';
		}

		resultContent.appendChild( wrapper );

		// Add another quote button
		var anotherBtn = document.createElement( 'button' );
		anotherBtn.type = 'button';
		anotherBtn.className = 'tqb-btn tqb-btn--primary';
		anotherBtn.textContent = 'Get Another Quote';
		anotherBtn.addEventListener( 'click', function () {
			resetForm();
			goToStep( STEP.TYPE );
		} );
		resultContent.appendChild( anotherBtn );
	}

	// =====================================================================
	// SUMMARY PANEL (RIGHT SIDEBAR)
	// =====================================================================
	// =====================================================================
	// PRICE CALCULATION HELPER (respects pricing_pattern from admin)
	// =====================================================================
	function calculateLinePrice( question, answer ) {
		var pattern = question.pricing_pattern || 'flat';

		if ( pattern === 'qty_times_fee' ) {
			var quantity = ( answer.quantity && answer.quantity > 0 ) ? answer.quantity : 1;
			return ( parseFloat( question.fee ) || 0 ) * quantity;
		}

		// 'flat' — fixed price regardless of quantity
		return parseFloat( question.fee ) || 0;
	}

	function updateSummaryPanel() {
		if ( ! state.summaryNeedsUpdate ) {
			return;
		}

		var summaryContent = document.getElementById( 'tqb-summary-content' );
		if ( ! summaryContent ) return;

		var html = '';
		var grandTotal = 0;

		// YOUR INFO
		if ( state.contactName || state.contactEmail || state.contactPhone ) {
			html += '<div class="tqb-summary__contact">';
			html += '<div class="tqb-summary__type">Your Info</div>';
			if ( state.contactName ) {
				html += '<div class="tqb-summary__contact-name">' + escapeHtml( state.contactName ) + '</div>';
			}
			if ( state.contactEmail ) {
				html += '<div class="tqb-summary__contact-email">' + escapeHtml( state.contactEmail ) + '</div>';
			}
			if ( state.contactPhone ) {
				html += '<div class="tqb-summary__contact-phone">' + escapeHtml( state.contactPhone ) + '</div>';
			}
			html += '</div>';
		}

		// PERSONAL TAX RETURN SECTION(S)
		if ( state.selectedTypes.includes( 'individual' ) && state.individualCount > 0 ) {
			for ( var p = 0; p < state.individualCount; p++ ) {
				var individualSubtotal = 0;
				var individualRows = '';
				var individualContextKey = 'individual_' + p;
				var personalFilingStatus = state.individualFilingStatuses[ p ];

				// Filing status surcharge, if selected
				if ( personalFilingStatus && tqbData.filing_status_prices ) {
					var filingLabel = ( tqbData.filing_status_labels && tqbData.filing_status_labels[ personalFilingStatus ] ) || personalFilingStatus;
					var filingPrice = parseFloat( tqbData.filing_status_prices[ personalFilingStatus ] || 0 );
					individualSubtotal += filingPrice;
					if ( filingPrice > 0 ) {
						individualRows += '<div class="tqb-summary__item"><span>' + escapeHtml( filingLabel ) + ' filing surcharge</span><span class="tqb-summary__item-amount">$' + filingPrice.toFixed( 2 ) + '</span></div>';
					}
				}

				Object.keys( state.answers ).forEach( function ( key ) {
					var answer = state.answers[ key ];
					if ( answer.context === individualContextKey && answer.answer === 'yes' ) {
						var question = tqbData.questions.find( function ( q ) { return q.item_key === answer.question_key; } );
						if ( question ) {
							var linePrice = calculateLinePrice( question, answer );
							individualSubtotal += linePrice;

							var displayText = question.label;
							if ( question.pricing_pattern === 'qty_times_fee' && answer.quantity > 1 ) {
								displayText += ' (\u00d7' + answer.quantity + ')';
							}
							individualRows += '<div class="tqb-summary__item"><span>' + escapeHtml( displayText ) + '</span><span class="tqb-summary__item-amount">$' + linePrice.toFixed( 2 ) + '</span></div>';
						}
					}
				} );

				if ( individualRows !== '' || state.individualNames[ p ] ) {
					var personalLabel = p === 0 ? 'Personal Tax Return' : 'Additional Personal Return #' + ( p + 1 );
					if ( state.individualNames[ p ] ) {
						personalLabel += ' \u2014 ' + state.individualNames[ p ];
					}
					html += '<div class="tqb-summary__section">';
					html += '<div class="tqb-summary__type">' + escapeHtml( personalLabel ) + '</div>';
					html += individualRows;
					if ( individualSubtotal > 0 ) {
						html += '<div class="tqb-summary__subtotal"><span>Subtotal</span><span>$' + individualSubtotal.toFixed( 2 ) + '</span></div>';
					}
					html += '</div>';
					grandTotal += individualSubtotal;
				}
			}
		}

		// BUSINESS SECTION(S)
		if ( state.selectedTypes.includes( 'business' ) && state.businessCount > 0 ) {
			for ( var b = 0; b < state.businessCount; b++ ) {
				var businessSubtotal = 0;
				var businessRows = '';
				var contextKey = 'business_' + b;

				// Base fee = matched asset band price + matched revenue band price
				var entityType = state.businessTypes[ b ];
				var assetBandLabel = state.businessAssetBands[ b ];
				var revenueBandLabel = state.businessRevenueBands[ b ];

				if ( entityType && assetBandLabel && tqbData.assetBands ) {
					var group = ( entityType === 'partnership' ) ? 'partnership' : 'c_s_corp';
					var assetBand = ( tqbData.assetBands[ group ] || [] ).find( function ( band ) { return band.label === assetBandLabel; } );
					if ( assetBand && assetBand.price !== null ) {
						businessSubtotal += parseFloat( assetBand.price ) || 0;
						businessRows += '<div class="tqb-summary__item"><span>Total assets (' + escapeHtml( assetBandLabel ) + ')</span><span class="tqb-summary__item-amount">$' + ( parseFloat( assetBand.price ) || 0 ).toFixed( 2 ) + '</span></div>';
					}
				}

				if ( revenueBandLabel && tqbData.revenueBands ) {
					var revenueBand = tqbData.revenueBands.find( function ( band ) { return band.label === revenueBandLabel; } );
					if ( revenueBand && revenueBand.price !== null ) {
						businessSubtotal += parseFloat( revenueBand.price ) || 0;
						businessRows += '<div class="tqb-summary__item"><span>Annual revenue (' + escapeHtml( revenueBandLabel ) + ')</span><span class="tqb-summary__item-amount">$' + ( parseFloat( revenueBand.price ) || 0 ).toFixed( 2 ) + '</span></div>';
					}
				}

				Object.keys( state.answers ).forEach( function ( key ) {
					var answer = state.answers[ key ];
					if ( answer.context === contextKey && answer.answer === 'yes' ) {
						var question = tqbData.questions.find( function ( q ) { return q.item_key === answer.question_key; } );
						if ( question ) {
							var linePrice = calculateLinePrice( question, answer );
							businessSubtotal += linePrice;

							var displayText = question.label;
							if ( question.pricing_pattern === 'qty_times_fee' && answer.quantity > 1 ) {
								displayText += ' (\u00d7' + answer.quantity + ')';
							}
							businessRows += '<div class="tqb-summary__item"><span>' + escapeHtml( displayText ) + '</span><span class="tqb-summary__item-amount">$' + linePrice.toFixed( 2 ) + '</span></div>';
						}
					}
				} );

				if ( businessRows !== '' || state.businessTypes[ b ] || state.businessNames[ b ] ) {
					var businessLabel = 'Business' + ( state.businessCount > 1 ? ' #' + ( b + 1 ) : '' );
					if ( state.businessNames[ b ] ) {
						businessLabel += ' \u2014 ' + state.businessNames[ b ];
					}
					html += '<div class="tqb-summary__section">';
					html += '<div class="tqb-summary__type">' + escapeHtml( businessLabel ) + '</div>';
					html += businessRows;
					if ( businessSubtotal > 0 ) {
						html += '<div class="tqb-summary__subtotal"><span>Subtotal</span><span>$' + businessSubtotal.toFixed( 2 ) + '</span></div>';
					}
					html += '</div>';
					grandTotal += businessSubtotal;
				}
			}
		}

		if ( html === '' ) {
			summaryContent.innerHTML = '<p class="tqb-summary__empty">Your selections will appear here as you go.</p>';
		} else {
			if ( grandTotal > 0 ) {
				html += '<div class="tqb-summary__total"><span class="tqb-summary__total-label">Estimated Total</span><span class="tqb-summary__total-amount">$' + grandTotal.toFixed( 2 ) + '</span></div>';
			}
			summaryContent.innerHTML = html;
		}

		state.summaryNeedsUpdate = false;
	}

	// =====================================================================
	// UTILITY FUNCTIONS
	// =====================================================================
	// `fieldSelectors` (optional): CSS selector(s) for the input(s) that
	// actually caused the error. Each gets wrapped with the red error
	// styling on its parent `.tqb-field` (or `.tqb-type-card` / a passed
	// container), not just the banner message at the top — so it's obvious
	// which field to fix, not just that something's wrong.
	function showError( message, fieldSelectors ) {
		var errorContainer = document.getElementById( 'tqb-form-error' );
		if ( errorContainer ) {
			errorContainer.textContent = message;
			errorContainer.hidden = false;
		}

		clearFieldHighlights();

		if ( fieldSelectors ) {
			var selectors = Array.isArray( fieldSelectors ) ? fieldSelectors : [ fieldSelectors ];
			selectors.forEach( function ( selector ) {
				wizard.querySelectorAll( selector ).forEach( function ( el ) {
					var fieldWrapper = el.closest( '.tqb-field' ) || el.closest( '.tqb-type-card' ) || el;
					fieldWrapper.classList.add( 'tqb-field--error' );
				} );
			} );
		}

		// Scroll the error into view last, and only if the browser supports
		// it — guarded so a missing/odd implementation can never block the
		// highlighting logic above from running.
		if ( errorContainer && typeof errorContainer.scrollIntoView === 'function' ) {
			try {
				errorContainer.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			} catch ( e ) {
				// Non-fatal — the banner and field highlights are already visible.
			}
		}
	}

	function clearFieldHighlights() {
		wizard.querySelectorAll( '.tqb-field--error' ).forEach( function ( el ) {
			el.classList.remove( 'tqb-field--error' );
		} );
	}

	function clearError() {
		var errorContainer = document.getElementById( 'tqb-form-error' );
		if ( errorContainer ) {
			errorContainer.hidden = true;
			errorContainer.textContent = '';
		}
		clearFieldHighlights();
	}

	function escapeHtml( text ) {
		if ( ! text ) return '';
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace( /[&<>"']/g, function ( m ) { return map[m]; } );
	}

	function resetForm() {
		state = {
			selectedTypes: [],
			filingStatus: null,
			businessCount: 0,
			businessNames: {},
			businessTypes: {},
			contactName: null,
			contactEmail: null,
			contactPhone: null,
			answers: {},
			completedSteps: [],
			partialSubmissionId: null,
			summaryNeedsUpdate: true
		};

		// Reset form inputs
		wizard.querySelectorAll( 'input[type="checkbox"], input[type="radio"], input[type="text"], input[type="email"], input[type="tel"], input[type="number"]' ).forEach( function ( input ) {
			if ( input.type === 'checkbox' || input.type === 'radio' ) {
				input.checked = false;
			} else {
				input.value = '';
			}
		} );

		clearError();
		updateSummaryPanel();
	}

	function savePartialProgress( stepNumber ) {
		// Don't save partial progress without contact email
		// The backend requires email and will reject without it
		if ( ! state.contactEmail ) {
			return;
		}

		// Format businesses array for backend
		var businesses = [];
		for ( var b = 0; b < state.businessCount; b++ ) {
			businesses.push({
				name: state.businessNames[ b ] || '',
				entity_type: state.businessTypes[ b ] || '',
				asset_band: state.businessAssetBands[ b ] || '',
				revenue_band: state.businessRevenueBands[ b ] || ''
			});
		}

		// Format answers in the same shape the submit handler uses
		var answersForSubmit = {};
		Object.keys( state.answers ).forEach( function ( key ) {
			var a = state.answers[ key ];
			var contextParts = a.context.split( '_' );
			var type = contextParts[ 0 ];
			var idx = contextParts[ 1 ] || '0';
			var prefixedKey = type + '-' + idx + '-' + a.question_key;
			answersForSubmit[ prefixedKey ] = {
				selected: a.answer === 'yes',
				qty: a.quantity || 1
			};
		} );

		var params = new URLSearchParams();
		params.append( 'action', 'tqb_save_partial' );
		params.append( 'nonce', tqbData.nonceSavePartial );
		params.append( 'step', stepNumber );
		params.append( 'email', state.contactEmail || '' );
		params.append( 'name', state.contactName || '' );
		params.append( 'phone', state.contactPhone || '' );
		params.append( 'quote_types', JSON.stringify( state.selectedTypes || [] ) );
		params.append( 'answers', JSON.stringify( answersForSubmit ) );
		params.append( 'businesses', JSON.stringify( businesses ) );

		fetch( tqbData.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			body: params
		} )
		.then( function ( response ) {
			return response.json();
		} )
		.then( function ( data ) {
			if ( data.success && data.data && data.data.submission_id ) {
				state.partialSubmissionId = data.data.submission_id;
			}
		} )
		.catch( function ( error ) {
			console.log( 'TQB: Failed to save partial progress:', error.message );
		} );
	}

	function isStepVisible( stepNumber ) {
		var step = wizard.querySelector( '[data-step="' + stepNumber + '"]' );
		return step && ! step.hidden;
	}

	// =====================================================================
	// INITIALIZATION
	// =====================================================================
	function init() {
		// Setup all step handlers
		setupStep1ReturnType();
		setupStep2FilingStatus();      // NEW
		initializeFilingStatusPrices(); // Load dynamic prices from database
		setupProgressBarNavigation();   // Make progress bar clickable backwards only
		setupStep3ContactInfo();       // Was setupStep2ContactInfo
		setupStep4QuestionsNav();      // Was setupStep3QuestionsNav
		setupStep5ReviewNav();         // Was setupStep4ReviewNav

		// Reset button
		wizard.querySelectorAll( '[data-action="reset-all"]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( confirm( 'Are you sure? This will clear all your answers.' ) ) {
					resetForm();
					goToStep( STEP.TYPE );
				}
			} );
		} );

		// Initial state
		goToStep( STEP.TYPE );
		updateSummaryPanel();
	}

	// Start the wizard
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
