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

	// State for multi-select + multiple businesses
	var state = {
		selectedTypes: [], // ['individual', 'business', 'business', ...]
		businessCount: 0,  // Number of businesses selected
		businessFieldsTouched: {}, // Track if business dropdowns have been interacted with
		completedSteps: [], // Track which steps have been completed
		partialSubmissionId: null, // ID from partial save
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
		var stepLabels = {
			1: 'Return Type Selection',
			2: 'Contact Information',
			3: 'Filing Details',
			4: 'Review Your Quote',
			5: 'Your Quote Results'
		};

		steps.forEach( function ( section ) {
			var isTarget = parseInt( section.getAttribute( 'data-step' ), 10 ) === stepNumber;
			section.hidden = ! isTarget;
			
			// Update aria-current for screen readers
			if ( isTarget ) {
				section.setAttribute( 'aria-current', 'step' );
			} else {
				section.removeAttribute( 'aria-current' );
			}
		} );

		var indicators = wizard.querySelectorAll( '.tqb-progress__step' );
		indicators.forEach( function ( indicator ) {
			var indicatorStep = parseInt( indicator.getAttribute( 'data-step-indicator' ), 10 );
			
			// Previous steps (1 to current-1): always is-complete (blue line)
			var isPrevStep = indicatorStep < stepNumber;
			// Current step: is-active
			var isCurrentStep = indicatorStep === stepNumber;
			
			indicator.classList.toggle( 'is-active', isCurrentStep );
			indicator.classList.toggle( 'is-complete', isPrevStep );
			
			// Update aria-current for progress indicators
			if ( isCurrentStep ) {
				indicator.setAttribute( 'aria-current', 'step' );
			} else {
				indicator.removeAttribute( 'aria-current' );
			}
		} );

		wizard.setAttribute( 'data-step', stepNumber );
		
		// Announce step change to screen readers
		var liveRegion = wizard.querySelector( '.tqb-sr-only' );
		if ( liveRegion ) {
			liveRegion.textContent = 'Step ' + stepNumber + ' of 5: ' + ( stepLabels[ stepNumber ] || '' );
		}
		
		wizard.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		
		// Focus management: focus the first input or heading in the step
		var currentStep = wizard.querySelector( '.tqb-step:not([hidden])' );
		if ( currentStep ) {
			var focusTarget = currentStep.querySelector( 'input:not([type="hidden"]), button, [tabindex="0"]' );
			if ( focusTarget ) {
				setTimeout( function() { focusTarget.focus(); }, 100 );
			}
		}
	}

	// ---------------------------------------------------------------------
	// Reset — clears everything and returns to Step 1
	// ---------------------------------------------------------------------

	function resetAll() {
		state.selectedTypes = [];
		state.businessCount = 0;
		state.businessFieldsTouched = {};
		state.completedSteps = [];
		state.partialSubmissionId = null;

		var nameEl = document.getElementById( 'tqb-contact-name' );
		var emailEl = document.getElementById( 'tqb-contact-email' );
		var phoneEl = document.getElementById( 'tqb-contact-phone' );
		[ nameEl, emailEl, phoneEl ].forEach( function ( field ) {
			field.value = '';
			field.style.borderColor = '';
		} );

		document.getElementById( 'tqb-question-sections' ).innerHTML = '';
		document.getElementById( 'tqb-add-business-section' ).hidden = true;
		document.getElementById( 'tqb-review-content' ).innerHTML = '';
		document.getElementById( 'tqb-result-content' ).innerHTML = '';

		// Reset checkboxes
		document.querySelectorAll( '.tqb-quote-type-checkbox' ).forEach( function ( cb ) {
			cb.checked = false;
		} );
		document.querySelectorAll( '.tqb-type-card--checkbox' ).forEach( function ( card ) {
			card.classList.remove( 'is-selected' );
		} );

		// Disable continue button
		var continueBtn = wizard.querySelector( '[data-action="start-quote"]' );
		if ( continueBtn ) {
			continueBtn.disabled = true;
		}

		var errorEl = document.getElementById( 'tqb-form-error' );
		errorEl.hidden = true;

		updateSummaryPanel();

		// Call server to mark partial as abandoned (if any exists)
		if ( state.partialSubmissionId ) {
			var data = new FormData();
			data.append( 'action', 'tqb_dismiss_partial' );
			data.append( 'nonce', tqbData.nonceDismissPartial || '' );
			fetch( tqbData.ajaxUrl, { method: 'POST', body: data } );
		}

		goToStep( STEP.TYPE );
	}

	/**
	 * Save partial form progress for abandoned quote follow-up.
	 * Called when user completes contact info (step 2+).
	 */
	function savePartialProgress( currentStep, buttonEl ) {
		var emailEl = document.getElementById( 'tqb-contact-email' );
		var nameEl = document.getElementById( 'tqb-contact-name' );
		var phoneEl = document.getElementById( 'tqb-contact-phone' );

		if ( ! emailEl || ! emailEl.value || ! isValidEmail( emailEl.value ) ) {
			return; // Don't save without email
		}

		// Show loading state on button if provided
		var originalButtonText = '';
		if ( buttonEl ) {
			originalButtonText = buttonEl.textContent;
			buttonEl.disabled = true;
			buttonEl.classList.add( 'tqb-loading' );
			buttonEl.textContent = 'Saving...';
		}

		var data = new FormData();
		data.append( 'action', 'tqb_save_partial' );
		data.append( 'nonce', tqbData.nonceSavePartial || '' );
		data.append( 'email', emailEl.value );
		data.append( 'name', nameEl ? nameEl.value : '' );
		data.append( 'phone', phoneEl ? phoneEl.value : '' );
		data.append( 'step', currentStep );
		data.append( 'quote_types', JSON.stringify( state.selectedTypes ) );
		data.append( 'answers', JSON.stringify( collectAnswers() ) );
		data.append( 'businesses', JSON.stringify( collectBusinessData() ) );

		fetch( tqbData.ajaxUrl, {
			method: 'POST',
			body: data,
		} )
		.then( function ( response ) {
			return response.json();
		} )
		.then( function ( result ) {
			// Reset button state
			if ( buttonEl ) {
				buttonEl.disabled = false;
				buttonEl.classList.remove( 'tqb-loading' );
				buttonEl.textContent = originalButtonText;
			}

			if ( result.success && result.data.submission_id ) {
				state.partialSubmissionId = result.data.submission_id;
				hideFormError();
			} else if ( result.data && result.data.validation_errors ) {
				// Server-side validation errors
				showServerValidationErrors( result.data.validation_errors );
			} else if ( result.data && result.data.duplicate ) {
				showDuplicateEmailWarning( emailEl.value );
			} else if ( result.data && result.data.message && result.data.message.indexOf( 'does not match' ) !== -1 ) {
				showContactMismatchWarning();
			} else if ( result.data && result.data.ip_conflict ) {
				showIPConflictWarning( result.data.message );
			} else if ( result.data && result.data.message ) {
				showFormError( result.data.message );
			}
		} )
		.catch( function ( error ) {
			// Reset button state
			if ( buttonEl ) {
				buttonEl.disabled = false;
				buttonEl.classList.remove( 'tqb-loading' );
				buttonEl.textContent = originalButtonText;
			}
			console.log( 'Partial save failed:', error );
		} );
	}

	/**
	 * Show warning when email already has a completed submission.
	 */
	function showDuplicateEmailWarning( email ) {
		var errorEl = document.getElementById( 'tqb-form-error' );
		if ( errorEl ) {
			errorEl.innerHTML = '<strong>Notice:</strong> This email (' + email + ') already has a completed quote submission. You can still continue, but a team member will be in touch regarding your previous quote.';
			errorEl.style.background = '#fff3cd';
			errorEl.style.borderColor = '#ffc107';
			errorEl.style.color = '#856404';
			errorEl.hidden = false;
		}
	}

	/**
	 * Show warning when contact info doesn't match existing submission.
	 */
	function showContactMismatchWarning() {
		var errorEl = document.getElementById( 'tqb-form-error' );
		if ( errorEl ) {
			errorEl.innerHTML = '<strong>Warning:</strong> The name and phone number don\'t match the existing submission for this email. Your changes cannot be saved. Please use the same name and phone number that was used originally, or contact us directly.';
			errorEl.style.background = '#ffebee';
			errorEl.style.borderColor = '#f44336';
			errorEl.style.color = '#b71c1c';
			errorEl.hidden = false;
		}
	}

	/**
	 * Show warning when same IP has different email.
	 */
	function showIPConflictWarning( message ) {
		var errorEl = document.getElementById( 'tqb-form-error' );
		if ( errorEl ) {
			errorEl.innerHTML = '<strong>Warning:</strong> ' + message;
			errorEl.style.background = '#ffebee';
			errorEl.style.borderColor = '#f44336';
			errorEl.style.color = '#b71c1c';
			errorEl.hidden = false;
		}
	}

	/**
	 * Show the form error message.
	 */
	function showFormError( message ) {
		var errorEl = document.getElementById( 'tqb-form-error' );
		if ( errorEl ) {
			errorEl.innerHTML = '<strong>Error:</strong> ' + message;
			errorEl.style.background = '#ffebee';
			errorEl.style.borderColor = '#dc3545';
			errorEl.style.color = '#dc3545';
			errorEl.hidden = false;
		}
	}

	/**
	 * Hide the form error message.
	 */
	function hideFormError() {
		var errorEl = document.getElementById( 'tqb-form-error' );
		if ( errorEl ) {
			errorEl.hidden = true;
		}
	}

	// Note: Resume functionality removed - can be re-added later if needed

	/**
	 * Check if email is valid.
	 */
	function isValidEmail( email ) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email );
	}

	/**
	 * Collect business data for partial save.
	 */
	function collectBusinessData() {
		var businesses = [];
		var bizIndex = 0;
		state.selectedTypes.forEach( function ( type ) {
			if ( type === 'business' ) {
				var bizId = 'tqb-business-' + bizIndex;
				var entityEl = document.getElementById( bizId + '-entity' );
				var assetEl = document.getElementById( bizId + '-assets' );
				var revenueEl = document.getElementById( bizId + '-revenue' );

				businesses.push( {
					entity_type: entityEl ? entityEl.value : '',
					asset_band: assetEl ? assetEl.value : '',
					revenue_band: revenueEl ? revenueEl.value : '',
				} );
				bizIndex++;
			}
		} );
		return businesses;
	}

	wizard.querySelectorAll( '[data-action="reset-all"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			resetAll();
		} );
	} );

	// ---------------------------------------------------------------------
	// Step 1: multi-select quote type
	// ---------------------------------------------------------------------

	function updateTypeCardStyles() {
		document.querySelectorAll( '.tqb-type-card--checkbox' ).forEach( function ( card ) {
			var checkbox = card.querySelector( 'input[type="checkbox"]' );
			if ( checkbox.checked ) {
				card.classList.add( 'is-selected' );
			} else {
				card.classList.remove( 'is-selected' );
			}
		} );
	}

	function updateContinueButton() {
		var continueBtn = wizard.querySelector( '[data-action="start-quote"]' );
		if ( continueBtn ) {
			var anyChecked = document.querySelector( '.tqb-quote-type-checkbox:checked' );
			continueBtn.disabled = ! anyChecked;
		}
	}

	document.querySelectorAll( '.tqb-quote-type-checkbox' ).forEach( function ( checkbox ) {
		checkbox.addEventListener( 'change', function () {
			updateTypeCardStyles();
			updateContinueButton();
		} );
	} );

	wizard.querySelectorAll( '[data-action="start-quote"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( btn.disabled ) {
				return;
			}

			// Build selected types array
			state.selectedTypes = [];
			state.businessCount = 0;

			document.querySelectorAll( '.tqb-quote-type-checkbox:checked' ).forEach( function ( cb ) {
				if ( cb.value === 'business' ) {
					state.businessCount++;
				}
				state.selectedTypes.push( cb.value );
			} );

			// Build question sections
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
			// Remove step 1 from completed (going back)
			state.completedSteps = state.completedSteps.filter( function ( s ) { return s !== STEP.TYPE; } );
			goToStep( STEP.TYPE );
			updateSummaryPanel();
		} );
	} );

	wizard.querySelectorAll( '[data-step="2"] [data-action="to-questions"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( ! validateContactFields() ) {
				return;
			}
			// Mark contact step as completed
			if ( ! state.completedSteps.includes( STEP.CONTACT ) ) {
				state.completedSteps.push( STEP.CONTACT );
			}
			// Save partial progress for abandoned quote follow-up
			savePartialProgress( STEP.CONTACT, btn );
			goToStep( STEP.QUESTIONS );
			updateSummaryPanel();
		} );
	} );

	[ 'tqb-contact-name', 'tqb-contact-email', 'tqb-contact-phone' ].forEach( function ( id ) {
		var field = document.getElementById( id );
		field.addEventListener( 'input', updateSummaryPanel );
		
		// Real-time validation as user types
		field.addEventListener( 'input', function() {
			clearFieldError( field );
			hideFormError();
		} );
		
		// Validate on blur
		field.addEventListener( 'blur', function() {
			validateSingleField( field );
		} );
	} );

	// Save partial progress when email is entered (capture leads who leave without clicking Continue)
	var emailField = document.getElementById( 'tqb-contact-email' );
	if ( emailField ) {
		emailField.addEventListener( 'blur', function () {
			if ( isValidEmail( emailField.value ) ) {
				savePartialProgress( 1 ); // Step 1 = type selected, contact info entered
			}
		} );
	}

	/**
	 * Validate a single field and show inline error if invalid.
	 */
	function validateSingleField( field ) {
		var fieldId = field.id;
		var value = field.value.trim();
		
		if ( fieldId === 'tqb-contact-name' ) {
			if ( ! value ) {
				showFieldError( field, 'Full name is required' );
				return false;
			} else if ( value.length < 2 ) {
				showFieldError( field, 'Please enter a valid name' );
				return false;
			}
		} else if ( fieldId === 'tqb-contact-email' ) {
			if ( ! value ) {
				showFieldError( field, 'Email address is required' );
				return false;
			} else if ( ! isValidEmail( value ) ) {
				showFieldError( field, 'Please enter a valid email (e.g. name@example.com)' );
				return false;
			}
		} else if ( fieldId === 'tqb-contact-phone' ) {
			if ( ! value ) {
				showFieldError( field, 'Phone number is required' );
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Validate contact form fields with clear error messages.
	 * @returns {boolean} Whether all fields are valid
	 */
	function validateContactFields() {
		var name = document.getElementById( 'tqb-contact-name' );
		var email = document.getElementById( 'tqb-contact-email' );
		var phone = document.getElementById( 'tqb-contact-phone' );
		
		var errors = [];
		var valid = true;
		
		// Reset field styles
		[ name, email, phone ].forEach( function ( field ) {
			field.style.borderColor = '';
			clearFieldError( field );
		} );
		
		// Validate name
		if ( ! name.value.trim() ) {
			errors.push( 'Please enter your full name.' );
			showFieldError( name, 'Full name is required' );
			valid = false;
		} else if ( name.value.trim().length < 2 ) {
			errors.push( 'Please enter a valid name.' );
			showFieldError( name, 'Please enter a valid name' );
			valid = false;
		}
		
		// Validate email
		if ( ! email.value.trim() ) {
			errors.push( 'Please enter your email address.' );
			showFieldError( email, 'Email address is required' );
			valid = false;
		} else if ( ! isValidEmail( email.value.trim() ) ) {
			errors.push( 'Please enter a valid email address.' );
			showFieldError( email, 'Please enter a valid email (e.g. name@example.com)' );
			valid = false;
		}
		
		// Validate phone
		if ( ! phone.value.trim() ) {
			errors.push( 'Please enter your phone number.' );
			showFieldError( phone, 'Phone number is required' );
			valid = false;
		}
		
		// Show summary error if any fields are invalid
		if ( ! valid ) {
			showFormError( 'Please fill in all required fields correctly before continuing.' );
		} else {
			hideFormError();
		}
		
		return valid;
	}

	/**
	 * Show error message below a field.
	 */
	function showFieldError( field, message ) {
		field.style.borderColor = '#dc3545';
		field.classList.add( 'tqb-field--error' );
		
		// Remove existing error message
		var existingError = field.parentNode.querySelector( '.tqb-field-error' );
		if ( existingError ) {
			existingError.remove();
		}
		
		// Add error message
		var errorDiv = document.createElement( 'div' );
		errorDiv.className = 'tqb-field-error';
		errorDiv.textContent = message;
		errorDiv.style.color = '#dc3545';
		errorDiv.style.fontSize = '12px';
		errorDiv.style.marginTop = '4px';
		field.parentNode.appendChild( errorDiv );
	}

	/**
	 * Show server-side validation errors.
	 */
	function showServerValidationErrors( errors ) {
		// Map error keys to field IDs
		var fieldMap = {
			'name': 'tqb-contact-name',
			'email': 'tqb-contact-email',
			'phone': 'tqb-contact-phone'
		};
		
		// Clear all existing errors first
		[ 'tqb-contact-name', 'tqb-contact-email', 'tqb-contact-phone' ].forEach( function( id ) {
			var field = document.getElementById( id );
			if ( field ) {
				clearFieldError( field );
			}
		} );
		
		// Show errors for each field
		Object.keys( errors ).forEach( function( key ) {
			var fieldId = fieldMap[ key ];
			if ( fieldId ) {
				var field = document.getElementById( fieldId );
				if ( field ) {
					showFieldError( field, errors[ key ] );
				}
			}
		} );
		
		// Show summary error
		showFormError( 'Please fill in all required fields correctly.' );
	}

	/**
	 * Clear error message from a field.
	 */
	function clearFieldError( field ) {
		field.classList.remove( 'tqb-field--error' );
		var existingError = field.parentNode.querySelector( '.tqb-field-error' );
		if ( existingError ) {
			existingError.remove();
		}
	}

	/**
	 * Validate phone number (check if not empty).
	 */
	function isValidPhone( phone ) {
		// Just check if there's content - no format validation
		return phone.trim().length > 0;
	}

	// ---------------------------------------------------------------------
	// Step 3: build multiple question sections (multi-select support)
	// ---------------------------------------------------------------------

	function buildQuestionsStep() {
		var container = document.getElementById( 'tqb-question-sections' );
		var addBusinessSection = document.getElementById( 'tqb-add-business-section' );

		container.innerHTML = '';

		// Build section for each selected type
		var businessIndex = 0;
		state.selectedTypes.forEach( function ( type ) {
			var sectionIndex = type === 'business' ? businessIndex++ : 0;
			var section = createQuestionSection( type, sectionIndex );
			container.appendChild( section );
		} );

		// Show "Add Another Business" button if business is selected
		addBusinessSection.hidden = ! state.selectedTypes.includes( 'business' );

		// Populate asset dropdowns after all sections are in the DOM
		businessIndex = 0;
		state.selectedTypes.forEach( function ( type ) {
			if ( type === 'business' ) {
				updateAssetBandOptionsForBusiness( 'tqb-business-' + businessIndex );
				businessIndex++;
			}
		} );

		updateSummaryPanel();
	}

	function createQuestionSection( type, businessIndex ) {
		var section = document.createElement( 'div' );
		section.className = 'tqb-question-section';
		section.setAttribute( 'data-section-type', type );
		section.setAttribute( 'data-section-index', businessIndex );

		var header = document.createElement( 'div' );
		header.className = 'tqb-question-section__header';

		var title = document.createElement( 'h3' );
		title.className = 'tqb-question-section__title';

		var badge = document.createElement( 'span' );
		badge.className = 'tqb-question-section__badge';

		if ( type === 'individual' ) {
			title.textContent = 'Personal Tax Return';
			badge.textContent = 'Individual';
		} else {
			title.textContent = 'Business Tax Return #' + ( businessIndex + 1 );
			badge.textContent = 'Business';
		}

		header.appendChild( title );
		header.appendChild( badge );

		// Add remove button for additional businesses (not the first one)
		if ( type === 'business' && businessIndex > 0 ) {
			var removeBtn = document.createElement( 'button' );
			removeBtn.type = 'button';
			removeBtn.className = 'button tqb-remove-business';
			removeBtn.textContent = 'Remove';
			removeBtn.setAttribute( 'data-business-index', businessIndex );
			removeBtn.style.marginLeft = '10px';
			removeBtn.style.color = '#b32d2e';
			removeBtn.addEventListener( 'click', function () {
				removeBusinessSection( businessIndex );
			} );
			header.appendChild( removeBtn );
		}

		section.appendChild( header );

		// Business basics (entity type, assets, revenue) for business sections
		if ( type === 'business' ) {
			var businessId = 'tqb-business-' + businessIndex;
			var basicsDiv = document.createElement( 'div' );
			basicsDiv.id = businessId;
			basicsDiv.className = 'tqb-business-basics';

			basicsDiv.appendChild(
				buildSelectField( businessId + '-entity', 'Business type', ENTITY_OPTIONS, function () {
					state.businessFieldsTouched[ businessId ] = true;
					updateAssetBandOptionsForBusiness( businessId );
					updateSummaryPanel();
				} )
			);

			basicsDiv.appendChild(
				buildSelectField( businessId + '-assets', 'Total business assets', [], function () {
					state.businessFieldsTouched[ businessId ] = true;
					updateSummaryPanel();
				} )
			);

			basicsDiv.appendChild(
				buildSelectField( businessId + '-revenue', 'Annual revenue / total receipts', tqbData.revenueBands.map( function ( b ) {
					return { value: b.label, label: b.label };
				} ), function () {
					state.businessFieldsTouched[ businessId ] = true;
					updateSummaryPanel();
				} )
			);

			section.appendChild( basicsDiv );
			// Note: updateAssetBandOptionsForBusiness is called after sections are added to DOM
		}

		// Questions list
		var questionsList = document.createElement( 'div' );
		questionsList.className = 'tqb-questions-list';
		section.appendChild( questionsList );

		// Render items
		var items = type === 'individual' ? tqbData.individualItems : tqbData.businessItems;
		renderQuestionRows( questionsList, items, type, businessIndex );

		// Pre-select W-2 for individual
		if ( type === 'individual' ) {
			var w2Row = questionsList.querySelector( '[data-item-key="w2_wages"]' );
			if ( w2Row ) {
				var w2Checkbox = w2Row.querySelector( 'input[type="checkbox"]' );
				w2Checkbox.checked = true;
				w2Checkbox.disabled = true;
			}
		}

		return section;
	}

	function buildBusinessBasics( container, businessId ) {
		container.appendChild(
			buildSelectField( businessId + '-entity', 'Business type', ENTITY_OPTIONS, function () {
				updateAssetBandOptionsForBusiness( businessId );
				updateSummaryPanel();
			} )
		);

		container.appendChild(
			buildSelectField( businessId + '-assets', 'Total business assets', [], updateSummaryPanel )
		);

		container.appendChild(
			buildSelectField( businessId + '-revenue', 'Annual revenue / total receipts', tqbData.revenueBands.map( function ( b ) {
				return { value: b.label, label: b.label };
			} ), updateSummaryPanel )
		);

		updateAssetBandOptionsForBusiness( businessId );
	}

	function updateAssetBandOptionsForBusiness( businessId ) {
		var entitySelect = document.getElementById( businessId + '-entity' );
		if ( ! entitySelect ) return;

		var entityType = entitySelect.value;
		var entityGroup = ( 'partnership' === entityType ) ? 'partnership' : 'c_s_corp';
		var bands = tqbData.assetBands[ entityGroup ] || [];

		var select = document.getElementById( businessId + '-assets' );
		if ( ! select ) return;

		select.innerHTML = '';
		bands.forEach( function ( band ) {
			var opt = document.createElement( 'option' );
			opt.value = band.label;
			opt.textContent = band.label + ( band.isCustom ? ' (requires custom quote)' : '' );
			select.appendChild( opt );
		} );
	}

	// Remove business section
	function removeBusinessSection( indexToRemove ) {
		// Find and remove the business section from the DOM
		var sections = wizard.querySelectorAll( '.tqb-question-section[data-section-type="business"]' );
		sections.forEach( function ( section ) {
			var sectionIndex = parseInt( section.getAttribute( 'data-section-index' ), 10 );
			if ( sectionIndex === indexToRemove ) {
				section.remove();
			}
		} );

		// Rebuild selected types array (remove one 'business')
		var newTypes = [];
		var businessCount = 0;
		state.selectedTypes.forEach( function ( type ) {
			if ( type === 'business' ) {
				// Keep this business if it's before the removed one, or skip it if it's the removed one
				if ( businessCount < indexToRemove ) {
					newTypes.push( type );
					businessCount++;
				} else if ( businessCount > indexToRemove ) {
					// This is after the removed one, add it
					newTypes.push( type );
					businessCount++;
				} else {
					// This is the one we're removing
					businessCount++;
				}
			} else {
				newTypes.push( type );
			}
		} );

		state.selectedTypes = newTypes;
		state.businessCount = businessCount - 1; // Adjust for the removed one

		// Rebuild question sections with correct indices
		rebuildQuestionSections();
		updateSummaryPanel();
	}

	// Rebuild question sections with correct business indices
	function rebuildQuestionSections() {
		var container = document.getElementById( 'tqb-question-sections' );
		container.innerHTML = '';

		var businessIndex = 0;
		state.selectedTypes.forEach( function ( type ) {
			var sectionIndex = type === 'business' ? businessIndex++ : 0;
			var section = createQuestionSection( type, sectionIndex );
			container.appendChild( section );

			if ( type === 'business' ) {
				updateAssetBandOptionsForBusiness( 'tqb-business-' + sectionIndex );
			}
		} );
	}

	// Add Another Business button handler
	wizard.querySelectorAll( '[data-action="add-business"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			state.selectedTypes.push( 'business' );
			state.businessCount++;

			// Add new business section
			var container = document.getElementById( 'tqb-question-sections' );
			var section = createQuestionSection( 'business', state.businessCount - 1 );
			container.appendChild( section );

			// Populate asset dropdown for the new section
			updateAssetBandOptionsForBusiness( 'tqb-business-' + ( state.businessCount - 1 ) );

			updateSummaryPanel();
		} );
	} );

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

	function renderQuestionRows( container, items, type, businessIndex ) {
		var prefix = type + '-' + businessIndex;
		items.forEach( function ( item ) {
			var row = document.createElement( 'div' );
			row.className = 'tqb-question-row';
			row.setAttribute( 'data-item-key', item.key );
			row.setAttribute( 'data-section-type', type );
			row.setAttribute( 'data-section-index', businessIndex );

			var main = document.createElement( 'div' );
			main.className = 'tqb-question-row__main';

			var checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.id = 'tqb-item-' + prefix + '-' + item.key;
			checkbox.setAttribute( 'data-item-key', item.key );
			checkbox.setAttribute( 'data-section-type', type );
			checkbox.setAttribute( 'data-section-index', businessIndex );

			var textWrap = document.createElement( 'div' );
			textWrap.className = 'tqb-question-row__text';

			var label = document.createElement( 'label' );
			label.className = 'tqb-question-row__label';
			label.setAttribute( 'for', 'tqb-item-' + prefix + '-' + item.key );
			label.textContent = item.label;
			textWrap.appendChild( label );

			// Add tooltip icon if tooltip text exists
			if ( item.tooltip ) {
				var tooltipWrap = document.createElement( 'span' );
				tooltipWrap.className = 'tqb-question-row__tooltip-wrap';

				var tooltipIcon = document.createElement( 'span' );
				tooltipIcon.className = 'tqb-tooltip-icon';
				tooltipIcon.textContent = 'ℹ️';
				tooltipIcon.setAttribute( 'aria-label', 'More information' );

				var tooltipText = document.createElement( 'span' );
				tooltipText.className = 'tqb-tooltip-text';
				tooltipText.textContent = item.tooltip;

				tooltipWrap.appendChild( tooltipIcon );
				tooltipWrap.appendChild( tooltipText );
				label.appendChild( tooltipWrap );
			}

			main.appendChild( checkbox );
			main.appendChild( textWrap );
			row.appendChild( main );

			checkbox.addEventListener( 'change', updateSummaryPanel );

			if ( item.showQty ) {
				var qtyWrap = document.createElement( 'div' );
				qtyWrap.className = 'tqb-question-row__qty-wrap';

				// Add threshold label if applicable
				if ( item.thresholdQty && item.thresholdTrigger ) {
					var thresholdLabel = document.createElement( 'span' );
					thresholdLabel.className = 'tqb-question-row__qty-label';
					var thresholdText = item.thresholdTrigger === 'above'
						? 'Trading volume (above ' + item.thresholdQty + ' = custom quote)'
						: 'Trading volume (below ' + item.thresholdQty + ' = custom quote)';
					thresholdLabel.textContent = thresholdText;
					qtyWrap.appendChild( thresholdLabel );
				}

				var qty = document.createElement( 'input' );
				qty.type = 'number';
				qty.className = 'tqb-question-row__qty';
				qty.min = '1';
				qty.value = '1';
				qty.setAttribute( 'data-item-key-qty', item.key );
				qty.setAttribute( 'data-section-type', type );
				qty.setAttribute( 'data-section-index', businessIndex );
				qty.disabled = true;
				qtyWrap.appendChild( qty );
				row.appendChild( qtyWrap );

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
			// Remove contact step from completed (going back)
			state.completedSteps = state.completedSteps.filter( function ( s ) { return s !== STEP.CONTACT; } );
			goToStep( STEP.CONTACT );
			updateSummaryPanel();
		} );
	} );

	wizard.querySelectorAll( '[data-step="3"] [data-action="to-review"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			// Mark questions step as completed
			if ( ! state.completedSteps.includes( STEP.QUESTIONS ) ) {
				state.completedSteps.push( STEP.QUESTIONS );
			}
			// Save partial progress for abandoned quote follow-up
			savePartialProgress( STEP.QUESTIONS, btn );
			buildReviewStep();
			goToStep( STEP.REVIEW );
			updateSummaryPanel();
		} );
	} );

	wizard.querySelectorAll( '[data-step="4"] [data-action="back"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			// Remove questions step from completed (going back)
			state.completedSteps = state.completedSteps.filter( function ( s ) { return s !== STEP.QUESTIONS; } );
			goToStep( STEP.QUESTIONS );
			updateSummaryPanel();
		} );
	} );

	wizard.querySelectorAll( '[data-step="4"] [data-action="submit"]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', submitQuote );
	} );

	function collectAnswers() {
		var answers = {};
		wizard.querySelectorAll( '.tqb-question-row input[type="checkbox"]' ).forEach( function ( checkbox ) {
			var type = checkbox.getAttribute( 'data-section-type' );
			var sectionIndex = checkbox.getAttribute( 'data-section-index' );
			var key = checkbox.getAttribute( 'data-item-key' );
			var compositeKey = type + '-' + sectionIndex + '-' + key;
			var qtyField = wizard.querySelector( '[data-item-key-qty="' + key + '"][data-section-type="' + type + '"][data-section-index="' + sectionIndex + '"]' );
			answers[ compositeKey ] = {
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

		// Business basics for each business section
		var businessIndex = 0;
		state.selectedTypes.forEach( function ( type ) {
			if ( type === 'business' ) {
				var bizId = 'tqb-business-' + businessIndex;
				var basicsSection = document.createElement( 'div' );
				basicsSection.className = 'tqb-review-section';
				var basicsTitle = document.createElement( 'div' );
				basicsTitle.className = 'tqb-review-section__title';
				basicsTitle.textContent = 'Business #' + ( businessIndex + 1 ) + ' Details';
				basicsSection.appendChild( basicsTitle );

				var entitySelect = document.getElementById( bizId + '-entity' );
				if ( entitySelect ) {
					var entityLabel = entitySelect.options[ entitySelect.selectedIndex ].textContent;
					basicsSection.appendChild( buildReviewRow( 'Business type', entityLabel ) );
				}
				basicsSection.appendChild( buildReviewRow( 'Total assets', document.getElementById( bizId + '-assets' ).value ) );
				basicsSection.appendChild( buildReviewRow( 'Annual revenue', document.getElementById( bizId + '-revenue' ).value ) );
				container.appendChild( basicsSection );
				businessIndex++;
			}
		} );

		// Selected items for each section
		var answers = collectAnswers();
		businessIndex = 0;
		state.selectedTypes.forEach( function ( type ) {
			var items = type === 'business' ? tqbData.businessItems : tqbData.individualItems;
			var sectionLabel = type === 'individual' ? 'Personal' : 'Business #' + ( businessIndex + 1 );
			var sectionIndex = type === 'business' ? businessIndex++ : 0;

			var itemsSection = document.createElement( 'div' );
			itemsSection.className = 'tqb-review-section';
			var itemsTitle = document.createElement( 'div' );
			itemsTitle.className = 'tqb-review-section__title';
			itemsTitle.textContent = sectionLabel + ' Tax Return';
			itemsSection.appendChild( itemsTitle );

			var anySelected = false;
			items.forEach( function ( item ) {
				var answerKey = type + '-' + sectionIndex + '-' + item.key;
				var answer = answers[ answerKey ];
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
		} );
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
		return calculateIndividualPreviewForAnswers( answers, 'individual', 0 );
	}

	/** Calculate individual preview for a specific section (supports multi-select) */
	function calculateIndividualPreviewForAnswers( answers, type, sectionIndex ) {
		var prefix = type + '-' + sectionIndex;
		var items = tqbData.individualItems;
		var total = 0;
		var lineItems = [];
		var isCustomQuote = false;
		var customReason = null;

		items.forEach( function ( item ) {
			var answer = answers[ prefix + '-' + item.key ];
			if ( ! answer || ! answer.selected ) {
				return;
			}

			// Check threshold-based custom quote trigger
			var qty = ( typeof answer.qty === 'number' ) ? answer.qty : 1;
			if ( shouldTriggerCustomQuote( item, qty ) ) {
				isCustomQuote = true;
				customReason = item.key;
				lineItems.push( {
					label: item.label,
					amount: null,
					qty: item.showQty ? qty : null,
					isCustomQuote: true,
					thresholdNote: getThresholdNote( item, qty ),
				} );
				return;
			}

			// Hard custom quote trigger (e.g. foreign accounts)
			if ( item.isCustomQuoteTrigger ) {
				isCustomQuote = true;
				customReason = item.key;
				lineItems.push( {
					label: item.label,
					amount: null,
					qty: null,
					isCustomQuote: true,
				} );
				return;
			}

			var amount = calculateLineAmount( item, qty );
			total += amount;
			lineItems.push( {
				label: item.label,
				amount: amount,
				qty: item.showQty ? qty : null,
			} );
		} );

		return { total: total, lineItems: lineItems, isCustomQuote: isCustomQuote, customReason: customReason };
	}

	/**
	 * Check if an item should trigger a custom quote based on threshold.
	 */
	function shouldTriggerCustomQuote( item, qty ) {
		if ( ! item.thresholdQty || ! item.thresholdTrigger ) {
			return false;
		}
		var threshold = item.thresholdQty;
		if ( item.thresholdTrigger === 'above' ) {
			return qty > threshold;
		} else if ( item.thresholdTrigger === 'below' ) {
			return qty < threshold;
		}
		return false;
	}

	/**
	 * Get a human-readable note about the threshold.
	 */
	function getThresholdNote( item, qty ) {
		if ( ! item.thresholdQty || ! item.thresholdTrigger ) {
			return '';
		}
		if ( item.thresholdTrigger === 'above' ) {
			return 'Custom quote (trading above ' + item.thresholdQty + ')';
		} else {
			return 'Custom quote (trading below ' + item.thresholdQty + ')';
		}
	}

	function findBandByLabel( bands, label ) {
		return bands.filter( function ( b ) {
			return b.label === label;
		} )[ 0 ] || null;
	}

	/** Mirrors TQB_Pricing_Engine::calculate_business(). */
	function calculateBusinessPreview( entityType, assetBandLabel, revenueBandLabel, answers, type, sectionIndex ) {
		var prefix = type + '-' + sectionIndex;
		var entityGroup = ( 'partnership' === entityType ) ? 'partnership' : 'c_s_corp';
		var assetBand = findBandByLabel( tqbData.assetBands[ entityGroup ] || [], assetBandLabel );
		var revenueBand = findBandByLabel( tqbData.revenueBands, revenueBandLabel );

		var extrasResult = calculateBusinessItemsForSection( tqbData.businessItems, answers, prefix );

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

	function calculateBusinessItemsForSection( items, answers, prefix ) {
		var total = 0;
		var lineItems = [];
		var isCustomQuote = false;

		items.forEach( function ( item ) {
			var answer = answers[ prefix + '-' + item.key ];
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

		var currentStep = parseInt( wizard.getAttribute( 'data-step' ), 10 );

		if ( ! state.selectedTypes.length ) {
			var empty = document.createElement( 'p' );
			empty.className = 'tqb-summary__empty';
			empty.textContent = 'Your selections will appear here as you go.';
			content.appendChild( empty );
			return;
		}

		// Helper to check if a step is completed or current
		var isStepVisible = function ( stepNum ) {
			return state.completedSteps.includes( stepNum ) || currentStep >= stepNum;
		};

		// Show contact info only if on step 2 or beyond
		if ( isStepVisible( STEP.CONTACT ) ) {
			var nameEl = document.getElementById( 'tqb-contact-name' );
			var emailEl = document.getElementById( 'tqb-contact-email' );
			var phoneEl = document.getElementById( 'tqb-contact-phone' );

			if ( nameEl && nameEl.value ) {
				// Contact info header badge
				var contactHeader = document.createElement( 'div' );
				contactHeader.className = 'tqb-summary__type';
				contactHeader.textContent = 'Your Info';
				content.appendChild( contactHeader );

				var contactSection = document.createElement( 'div' );
				contactSection.className = 'tqb-summary__contact';

				var contactName = document.createElement( 'div' );
				contactName.className = 'tqb-summary__contact-name';
				contactName.textContent = nameEl.value;
				contactSection.appendChild( contactName );

				if ( emailEl && emailEl.value ) {
					var contactEmail = document.createElement( 'div' );
					contactEmail.className = 'tqb-summary__contact-email';
					contactEmail.textContent = emailEl.value;
					contactSection.appendChild( contactEmail );
				}

				if ( phoneEl && phoneEl.value ) {
					var contactPhone = document.createElement( 'div' );
					contactPhone.className = 'tqb-summary__contact-phone';
					contactPhone.textContent = phoneEl.value;
					contactSection.appendChild( contactPhone );
				}

				content.appendChild( contactSection );
			}
		}

		// Only show question sections if on step 3 or beyond
		if ( ! isStepVisible( STEP.QUESTIONS ) ) {
			// Don't show question sections yet
			return;
		}

		// Calculate combined total from all sections
		var answers = collectAnswers();
		var grandTotal = 0;
		var isCustomQuote = false;
		var businessIndex = 0;

		state.selectedTypes.forEach( function ( type ) {
			var sectionIndex = type === 'business' ? businessIndex++ : 0;

			// Section container
			var sectionWrap = document.createElement( 'div' );
			sectionWrap.className = 'tqb-summary__section';

			// Section header
			var sectionHeader = document.createElement( 'div' );
			sectionHeader.className = 'tqb-summary__type';
			sectionHeader.textContent = type === 'individual' ? 'Personal Tax Return' : 'Business #' + ( sectionIndex + 1 );
			sectionWrap.appendChild( sectionHeader );

			var sectionTotal = 0;

			if ( type === 'business' ) {
				var bizId = 'tqb-business-' + sectionIndex;
				var entityEl = document.getElementById( bizId + '-entity' );
				var assetEl = document.getElementById( bizId + '-assets' );
				var revenueEl = document.getElementById( bizId + '-revenue' );

				// Show business details if fields have been interacted with OR if they have values
				var hasBusinessValues = entityEl && entityEl.value && assetEl && assetEl.value && revenueEl && revenueEl.value;
				if ( hasBusinessValues ) {
					var result = calculateBusinessPreview( entityEl.value, assetEl.value, revenueEl.value, answers, type, sectionIndex );

					// Business basics summary
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
					sectionWrap.appendChild( basicsWrap );

					if ( result.baseFee !== null ) {
						var baseRow = document.createElement( 'div' );
						baseRow.className = 'tqb-summary__item';
						var baseLabel = document.createElement( 'span' );
						baseLabel.textContent = 'Base Return Fee';
						var baseAmount = document.createElement( 'span' );
						baseAmount.className = 'tqb-summary__item-amount';
						baseAmount.textContent = formatCurrency( result.baseFee );
						baseRow.appendChild( baseLabel );
						baseRow.appendChild( baseAmount );
						sectionWrap.appendChild( baseRow );
						sectionTotal += result.baseFee;
					}

					// Calculate section total from line items (handles null total for custom quotes)
					result.lineItems.forEach( function ( li ) {
						sectionTotal += li.amount;
					} );
					isCustomQuote = isCustomQuote || result.isCustomQuote;

					// Line items for this section
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
						sectionWrap.appendChild( row );
					} );
				}
			} else {
				var result = calculateIndividualPreviewForAnswers( answers, type, sectionIndex );
				if ( result.total !== null ) {
					sectionTotal += result.total;
				}
				isCustomQuote = isCustomQuote || result.isCustomQuote;

				// Line items for this section
				result.lineItems.forEach( function ( li ) {
					var row = document.createElement( 'div' );
					row.className = 'tqb-summary__item';
					var label = document.createElement( 'span' );
					var labelText = li.label;
					if ( li.qty && li.qty !== 1 ) {
						labelText += ' (\u00d7' + li.qty + ')';
					}
					if ( li.thresholdNote ) {
						labelText += ' - ' + li.thresholdNote;
					}
					label.textContent = labelText;
					var amount = document.createElement( 'span' );
					amount.className = 'tqb-summary__item-amount';
					if ( li.isCustomQuote ) {
						amount.textContent = 'Custom quote';
						amount.style.fontWeight = '600';
						amount.style.color = 'var(--tqb-gold)';
					} else {
						amount.textContent = formatCurrency( li.amount );
					}
					row.appendChild( label );
					row.appendChild( amount );
					sectionWrap.appendChild( row );
				} );
			}

			// Section subtotal
			var sectionSubtotalRow = document.createElement( 'div' );
			sectionSubtotalRow.className = 'tqb-summary__subtotal';
			var subtotalLabel = document.createElement( 'span' );
			subtotalLabel.textContent = 'Subtotal';
			var subtotalAmount = document.createElement( 'span' );
			subtotalAmount.className = 'tqb-summary__item-amount';
			subtotalAmount.textContent = formatCurrency( sectionTotal );
			sectionSubtotalRow.appendChild( subtotalLabel );
			sectionSubtotalRow.appendChild( subtotalAmount );
			sectionWrap.appendChild( sectionSubtotalRow );

			grandTotal += sectionTotal;
			content.appendChild( sectionWrap );
		} );

		// Grand total
		var totalRow = document.createElement( 'div' );
		totalRow.className = 'tqb-summary__total';
		var totalLabel = document.createElement( 'span' );
		totalLabel.textContent = 'Estimated Total';
		var totalAmount = document.createElement( 'span' );
		totalAmount.className = 'tqb-summary__total-amount';
		totalAmount.textContent = formatCurrency( grandTotal );
		totalRow.appendChild( totalLabel );
		totalRow.appendChild( totalAmount );
		content.appendChild( totalRow );
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

		// Send all selected types as JSON
		body.append( 'quote_types', JSON.stringify( state.selectedTypes ) );
		body.append( 'contact_name', document.getElementById( 'tqb-contact-name' ).value );
		body.append( 'contact_email', document.getElementById( 'tqb-contact-email' ).value );
		body.append( 'contact_phone', document.getElementById( 'tqb-contact-phone' ).value );

		// Send answers
		Object.keys( answers ).forEach( function ( itemKey ) {
			body.append( 'answers[' + itemKey + '][selected]', answers[ itemKey ].selected ? '1' : '' );
			body.append( 'answers[' + itemKey + '][qty]', answers[ itemKey ].qty );
		} );

		// Send business data for each business section
		var businessIndex = 0;
		state.selectedTypes.forEach( function ( type ) {
			if ( type === 'business' ) {
				var bizId = 'tqb-business-' + businessIndex;
				body.append( 'businesses[' + businessIndex + '][entity_type]', document.getElementById( bizId + '-entity' ).value );
				body.append( 'businesses[' + businessIndex + '][asset_band]', document.getElementById( bizId + '-assets' ).value );
				body.append( 'businesses[' + businessIndex + '][revenue_band]', document.getElementById( bizId + '-revenue' ).value );
				businessIndex++;
			}
		} );

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

			// Create CTA wrapper for both buttons
			var ctaWrapper = document.createElement( 'div' );
			ctaWrapper.className = 'tqb-result__cta';

			var againBtn = document.createElement( 'button' );
			againBtn.type = 'button';
			againBtn.className = 'tqb-btn tqb-btn--ghost';
			againBtn.textContent = 'Get Another Quote';
			againBtn.addEventListener( 'click', function () {
				resetAll();
			} );
			ctaWrapper.appendChild( againBtn );

			// Add Schedule Call button if available (custom quote case)
			if ( data.schedulingLink ) {
				var link = document.createElement( 'a' );
				link.href = data.schedulingLink;
				link.className = 'tqb-btn tqb-btn--primary';
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				link.textContent = 'Schedule a Call';
				ctaWrapper.appendChild( link );
			}

			container.appendChild( ctaWrapper );
	}

	function formatCurrency( amount ) {
		var num = parseFloat( amount ) || 0;
		return '$' + num.toLocaleString( 'en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
	}

	updateSummaryPanel();
} )();
