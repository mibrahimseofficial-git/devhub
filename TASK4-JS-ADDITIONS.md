/**
 * TASK 4 ADDITIONS: Filing Status Support
 * Add these functions to tqb-public.js
 * Location: After the STEP and ENTITY_OPTIONS definitions, within the IIFE
 */

// ===== UPDATE THE STEP CONSTANT =====
// REPLACE:
//   var STEP = { TYPE: 1, CONTACT: 2, QUESTIONS: 3, REVIEW: 4, RESULT: 5 };
// WITH:
//   var STEP = { TYPE: 1, FILING: 2, CONTACT: 3, QUESTIONS: 4, REVIEW: 5, RESULT: 6 };

// ===== ADD TO STATE OBJECT =====
// After existingstate definition, ADD:
//   filingStatus: null,   // single, mfj, mfs, hoh
//   questions: {},        // Cached questions by type+status

// ===== ADD THESE NEW FUNCTIONS =====

/**
 * Loads questions for a given return type and filing status via AJAX
 */
function loadQuestionsForFilingStatus( returnType, filingStatus ) {
	return fetch( tqbData.ajaxUrl + '?action=tqb_load_questions', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'X-WP-Nonce': tqbData.nonce,
		},
		body: 'action=tqb_load_questions&return_type=' + encodeURIComponent( returnType ) + '&filing_status=' + encodeURIComponent( filingStatus || '' ),
	} )
		.then( function ( response ) {
			return response.json();
		} )
		.then( function ( data ) {
			if ( ! data.success || ! data.data ) {
				console.error( 'Failed to load questions:', data );
				return [];
			}
			return data.data; // Array of question objects
		} )
		.catch( function ( err ) {
			console.error( 'AJAX error loading questions:', err );
			return [];
		} );
}

/**
 * Handles transition from type selection to filing status (for individual)
 * or directly to contact info (for business only)
 */
function handleStartQuote() {
	var selectedTypes = [];
	wizard.querySelectorAll( '.tqb-quote-type-checkbox:checked' ).forEach( function ( checkbox ) {
		selectedTypes.push( checkbox.value );
	} );

	if ( selectedTypes.length === 0 ) {
		showError( 'Please select at least one return type.' );
		return;
	}

	state.selectedTypes = selectedTypes;

	// If individual is selected, show filing status step
	if ( selectedTypes.includes( 'individual' ) ) {
		goToStep( STEP.FILING );
	} else {
		// Business only — skip to contact
		goToStep( STEP.CONTACT );
	}
}

/**
 * Handles filing status selection and transitions to contact info
 */
function handleFilingStatusSelected() {
	var selectedRadio = wizard.querySelector( 'input[name="filing_status"]:checked' );
	var continueBtn = wizard.querySelector( '[data-step="' + STEP.FILING + '"] [data-action="to-contact"]' );

	if ( selectedRadio ) {
		state.filingStatus = selectedRadio.value;
		if ( continueBtn ) {
			continueBtn.disabled = false;
		}
	} else {
		if ( continueBtn ) {
			continueBtn.disabled = true;
		}
	}
}

/**
 * Handles transition from contact info to questions
 * Pre-loads questions for individual type with chosen filing status
 */
function handleToQuestions() {
	var name = document.getElementById( 'tqb-contact-name' ).value.trim();
	var email = document.getElementById( 'tqb-contact-email' ).value.trim();
	var phone = document.getElementById( 'tqb-contact-phone' ).value.trim();

	if ( ! name || ! email || ! phone ) {
		showError( 'Please fill in all contact information.' );
		return;
	}

	state.contactName = name;
	state.contactEmail = email;
	state.contactPhone = phone;

	// If individual is selected, load questions for their filing status
	if ( state.selectedTypes.includes( 'individual' ) && state.filingStatus ) {
		loadQuestionsForFilingStatus( 'individual', state.filingStatus )
			.then( function ( questions ) {
				state.questions['individual_' + state.filingStatus] = questions;
				renderQuestionSections();
				goToStep( STEP.QUESTIONS );
			} )
			.catch( function ( err ) {
				showError( 'Failed to load questions. Please try again.' );
				console.error( err );
			} );
	} else if ( state.selectedTypes.includes( 'business' ) ) {
		// Load business questions
		loadQuestionsForFilingStatus( 'business', null )
			.then( function ( questions ) {
				state.questions.business = questions;
				renderQuestionSections();
				goToStep( STEP.QUESTIONS );
			} )
			.catch( function ( err ) {
				showError( 'Failed to load questions. Please try again.' );
				console.error( err );
			} );
	} else {
		goToStep( STEP.QUESTIONS );
	}
}

/**
 * Renders question sections based on selected types
 * Uses pre-loaded questions from state.questions
 */
function renderQuestionSections() {
	var container = document.getElementById( 'tqb-question-sections' );
	if ( ! container ) return;

	container.innerHTML = '';

	state.selectedTypes.forEach( function ( type, index ) {
		var section = document.createElement( 'div' );
		section.className = 'tqb-question-section';
		section.dataset.type = type;
		section.dataset.index = index;

		var title = document.createElement( 'h3' );
		title.className = 'tqb-section-title';

		if ( type === 'individual' ) {
			title.textContent = 'Personal Tax Return (' + filingStatusLabel( state.filingStatus ) + ')';
		} else {
			title.textContent = 'Business Return #' + ( index ); // Will have entity selector
		}

		section.appendChild( title );

		// Get questions for this type
		var questionsKey = type === 'individual' ? 'individual_' + state.filingStatus : type;
		var questions = state.questions[ questionsKey ] || [];

		questions.forEach( function ( question, qIndex ) {
			var questionDiv = renderQuestion( question, type, qIndex );
			section.appendChild( questionDiv );
		} );

		container.appendChild( section );
	} );
}

/**
 * Renders a single question with conditional reveal for followup fields
 */
function renderQuestion( question, returnType, questionIndex ) {
	var wrapper = document.createElement( 'div' );
	wrapper.className = 'tqb-question';
	wrapper.dataset.itemKey = question.item_key;
	wrapper.dataset.questionIndex = questionIndex;

	// Main checkbox
	var checkLabel = document.createElement( 'label' );
	checkLabel.className = 'tqb-question-label';

	var checkbox = document.createElement( 'input' );
	checkbox.type = 'checkbox';
	checkbox.name = returnType + '_' + question.item_key;
	checkbox.value = 'yes';
	checkbox.className = 'tqb-question-checkbox';

	var labelText = document.createElement( 'span' );
	labelText.className = 'tqb-question-text';
	labelText.textContent = question.label;

	checkLabel.appendChild( checkbox );
	checkLabel.appendChild( labelText );

	// Help text / tooltip
	if ( question.tooltip ) {
		var helpText = document.createElement( 'div' );
		helpText.className = 'tqb-question-help';
		helpText.textContent = question.tooltip;
		wrapper.appendChild( helpText );
	}

	wrapper.appendChild( checkLabel );

	// Conditional followup field (quantity/amount)
	if ( question.pricing_pattern !== 'flat' && question.pricing_pattern !== 'hardcoded' ) {
		var followupWrapper = document.createElement( 'div' );
		followupWrapper.className = 'tqb-question-followup';

		// Hidden by default if reveal_followup is true
		if ( question.reveal_followup ) {
			followupWrapper.style.display = 'none';
		}

		var followupLabel = document.createElement( 'label' );
		followupLabel.htmlFor = returnType + '_' + question.item_key + '_qty';

		var followupLabelText = document.createElement( 'span' );
		followupLabelText.textContent = question.followup_label || 'How many?';

		var followupInput = document.createElement( 'input' );
		followupInput.type = 'number';
		followupInput.id = returnType + '_' + question.item_key + '_qty';
		followupInput.name = returnType + '_' + question.item_key + '_qty';
		followupInput.className = 'tqb-question-qty';
		followupInput.placeholder = '0';
		followupInput.min = '0';
		followupInput.step = '1';

		followupLabel.appendChild( followupLabelText );
		followupLabel.appendChild( followupInput );
		followupWrapper.appendChild( followupLabel );

		// Show followup on checkbox change (if reveal_followup is true)
		checkbox.addEventListener( 'change', function () {
			if ( question.reveal_followup ) {
				followupWrapper.style.display = this.checked ? 'block' : 'none';
			}
		} );

		wrapper.appendChild( followupWrapper );
	}

	return wrapper;
}

/**
 * Gets human-readable label for filing status
 */
function filingStatusLabel( status ) {
	var labels = {
		'single': 'Single',
		'mfj': 'Married Filing Jointly',
		'mfs': 'Married Filing Separately',
		'hoh': 'Head of Household'
	};
	return labels[ status ] || status;
}

/**
 * Collects answers from the questions form
 */
function collectAnswers() {
	var answers = {};
	var questions = document.querySelectorAll( '.tqb-question-checkbox:checked' );

	questions.forEach( function ( checkbox ) {
		var name = checkbox.name; // e.g., 'individual_w2_wages'
		var parts = name.split( '_' );
		var returnType = parts[ 0 ]; // 'individual' or 'business'
		var itemKey = parts.slice( 1 ).join( '_' ); // Rejoin in case item_key has underscores

		var qty = 1; // Default to 1 for flat pricing
		var qtyInput = document.querySelector( '[name="' + name + '_qty"]' );
		if ( qtyInput && qtyInput.value ) {
			qty = parseInt( qtyInput.value, 10 ) || 1;
		}

		var key = returnType + ':' + itemKey;
		answers[ key ] = { selected: true, qty: qty };
	} );

	return answers;
}

// ===== UPDATE EVENT LISTENERS =====

// Find this line (around 381):
//   wizard.querySelectorAll( '[data-action="start-quote"]' ).forEach...
// REPLACE the entire handler with:

// wizard.querySelectorAll( '[data-action="start-quote"]' ).forEach( function ( btn ) {
// 	btn.addEventListener( 'click', handleStartQuote );
// } );

// Find the "to-questions" handler (around 418):
// REPLACE with:
// wizard.querySelectorAll( '[data-action="to-questions"]' ).forEach( function ( btn ) {
// 	btn.addEventListener( 'click', handleToQuestions );
// } );

// ADD after the above:
// Filing status radio change listener
wizard.querySelectorAll( '.tqb-filing-status-radio' ).forEach( function ( radio ) {
	radio.addEventListener( 'change', handleFilingStatusSelected );
} );

// Filing status to contact button
wizard.querySelectorAll( '[data-action="to-contact"]' ).forEach( function ( btn ) {
	btn.addEventListener( 'click', function () {
		goToStep( STEP.CONTACT );
	} );
} );
