<?php
/**
 * Front-end shortcode template. Two-column layout: left = the step wizard,
 * right = a live-updating summary panel (client-side calculated — see
 * PROJECT_SPEC.md Section 9.1 for the accepted sync-risk tradeoff).
 *
 * 5 steps: Return Type -> Your Info -> Details -> Review -> Your Quote.
 * The Review step (added per client feedback, Section 9) shows every
 * answer back to the user before they submit anything.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tqb-layout" id="tqb-layout">

	<!-- Resume banner - shown when user has a partial submission -->
	<div id="tqb-resume-banner" class="tqb-resume-banner" hidden></div>

	<div class="tqb-wizard" id="tqb-wizard" data-step="1">

		<!-- Screen reader live region for step announcements -->
		<div class="tqb-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>

		<div class="tqb-wizard__header">
			<div class="tqb-progress" aria-hidden="true">
				<span class="tqb-progress__step is-active" data-step-indicator="1"><span>1</span><em>Return Type</em></span>
				<span class="tqb-progress__step" data-step-indicator="2"><span>2</span><em>Your Info</em></span>
				<span class="tqb-progress__step" data-step-indicator="3"><span>3</span><em>Details</em></span>
				<span class="tqb-progress__step" data-step-indicator="4"><span>4</span><em>Review</em></span>
				<span class="tqb-progress__step" data-step-indicator="5"><span>5</span><em>Your Quote</em></span>
			</div>
			<button type="button" class="tqb-reset-link" data-action="reset-all">Start Over</button>
		</div>

		<!-- Error/Notice container -->
		<div id="tqb-form-error" class="tqb-notice-banner tqb-notice-banner--error" hidden></div>

		<!-- Step 1: Individual vs Business (multi-select) -->
		<section class="tqb-step" data-step="1">
			<h2 class="tqb-step__title">What kind of return do you need?</h2>
			<p class="tqb-step__subtitle">Select all that apply.</p>

			<div class="tqb-type-choice">
				<label class="tqb-type-card tqb-type-card--checkbox" for="tqb-select-individual">
					<span class="tqb-type-card__check"></span>
					<input type="checkbox" id="tqb-select-individual" value="individual" class="tqb-quote-type-checkbox" />
					<span class="tqb-type-card__label">Individual</span>
					<span class="tqb-type-card__desc">Personal income tax return</span>
				</label>
				<label class="tqb-type-card tqb-type-card--checkbox" for="tqb-select-business">
					<span class="tqb-type-card__check"></span>
					<input type="checkbox" id="tqb-select-business" value="business" class="tqb-quote-type-checkbox" />
					<span class="tqb-type-card__label">Business</span>
					<span class="tqb-type-card__desc">C-Corp, S-Corp, or Partnership return</span>
				</label>
			</div>

			<div class="tqb-step__nav">
				<button type="button" class="tqb-btn tqb-btn--primary" data-action="start-quote" disabled>Continue</button>
			</div>
		</section>

		<!-- Step 2: Contact info -->
		<section class="tqb-step" data-step="2" hidden>
			<h2 class="tqb-step__title">Your Information</h2>
			<p class="tqb-step__subtitle">We'll send your quote and a confirmation here.</p>

			<div class="tqb-field">
				<label for="tqb-contact-name">Full Name <span style="color: var(--tqb-error);">*</span></label>
				<input type="text" id="tqb-contact-name" name="contact_name" autocomplete="name" placeholder="John Smith" required />
			</div>
			<div class="tqb-field">
				<label for="tqb-contact-email">Email Address <span style="color: var(--tqb-error);">*</span></label>
				<input type="email" id="tqb-contact-email" name="contact_email" autocomplete="email" placeholder="john@example.com" required />
			</div>
			<div class="tqb-field">
				<label for="tqb-contact-phone">Phone Number <span style="color: var(--tqb-error);">*</span></label>
				<input type="tel" id="tqb-contact-phone" name="contact_phone" autocomplete="tel" placeholder="(555) 123-4567" required />
			</div>

			<div class="tqb-step__nav">
				<button type="button" class="tqb-btn tqb-btn--ghost" data-action="back">Back</button>
				<button type="button" class="tqb-btn tqb-btn--primary" data-action="to-questions">Continue</button>
			</div>
		</section>

		<!-- Step 3: Questions (built by JS — supports personal + multiple businesses) -->
		<section class="tqb-step" data-step="3" hidden>
			<div id="tqb-question-sections">
				<!-- Populated by JS: one section per selected type (personal, business 1, business 2, etc.) -->
			</div>

			<div id="tqb-add-business-section" class="tqb-add-business" hidden>
				<button type="button" class="tqb-btn tqb-btn--outline" data-action="add-business">
					+ Add Another Business
				</button>
			</div>

			<div class="tqb-step__nav">
				<button type="button" class="tqb-btn tqb-btn--ghost" data-action="back">Back</button>
				<button type="button" class="tqb-btn tqb-btn--primary" data-action="to-review">Review My Answers</button>
			</div>
		</section>

		<!-- Step 4: Review (new, per client feedback — see all answers before submitting) -->
		<section class="tqb-step" data-step="4" hidden>
			<h2 class="tqb-step__title">Review your answers</h2>
			<p class="tqb-step__subtitle">Double-check everything below, then submit when you're ready.</p>

			<div id="tqb-review-content">
				<!-- Populated by JS: contact info + every selected answer -->
			</div>

			<div class="tqb-step__nav">
				<button type="button" class="tqb-btn tqb-btn--ghost" data-action="back">Back</button>
				<button type="button" class="tqb-btn tqb-btn--primary" data-action="submit">
					<span class="tqb-btn__label">Get My Quote</span>
					<span class="tqb-btn__spinner" hidden></span>
				</button>
			</div>

			<p class="tqb-error" id="tqb-form-error" role="alert" hidden></p>
		</section>

		<!-- Step 5: Result -->
		<section class="tqb-step" data-step="5" hidden>
			<div id="tqb-result-content">
				<!-- Populated by JS: either the instant proposal or the custom-quote message -->
			</div>
		</section>

	</div>

	<aside class="tqb-summary" id="tqb-summary" aria-live="polite">
		<div class="tqb-summary__inner">
			<h3 class="tqb-summary__title">Your Summary</h3>
			<div id="tqb-summary-content">
				<p class="tqb-summary__empty">Your selections will appear here as you go.</p>
			</div>
		</div>
	</aside>

</div>
