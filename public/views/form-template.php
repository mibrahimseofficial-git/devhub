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

	<div class="tqb-wizard" id="tqb-wizard" data-step="1">

		<div class="tqb-wizard__header">
			<div class="tqb-progress" aria-hidden="true">
				<span class="tqb-progress__step is-active" data-step-indicator="1">1<em>Return Type</em></span>
				<span class="tqb-progress__step" data-step-indicator="2">2<em>Your Info</em></span>
				<span class="tqb-progress__step" data-step-indicator="3">3<em>Details</em></span>
				<span class="tqb-progress__step" data-step-indicator="4">4<em>Review</em></span>
				<span class="tqb-progress__step" data-step-indicator="5">5<em>Your Quote</em></span>
			</div>
			<button type="button" class="tqb-reset-link" data-action="reset-all">Start Over</button>
		</div>

		<!-- Step 1: Individual vs Business -->
		<section class="tqb-step" data-step="1">
			<h2 class="tqb-step__title">What kind of return do you need?</h2>
			<p class="tqb-step__subtitle">This tells us which questions to ask next.</p>

			<div class="tqb-type-choice">
				<button type="button" class="tqb-type-card" data-quote-type="individual">
					<span class="tqb-type-card__label">Individual</span>
					<span class="tqb-type-card__desc">Personal income tax return</span>
				</button>
				<button type="button" class="tqb-type-card" data-quote-type="business">
					<span class="tqb-type-card__label">Business</span>
					<span class="tqb-type-card__desc">C-Corp, S-Corp, or Partnership return</span>
				</button>
			</div>
		</section>

		<!-- Step 2: Contact info -->
		<section class="tqb-step" data-step="2" hidden>
			<h2 class="tqb-step__title">A few details about you</h2>
			<p class="tqb-step__subtitle">We'll send your quote and a confirmation here.</p>

			<div class="tqb-field">
				<label for="tqb-contact-name">Full name</label>
				<input type="text" id="tqb-contact-name" name="contact_name" autocomplete="name" required />
			</div>
			<div class="tqb-field">
				<label for="tqb-contact-email">Email address</label>
				<input type="email" id="tqb-contact-email" name="contact_email" autocomplete="email" required />
			</div>
			<div class="tqb-field">
				<label for="tqb-contact-phone">Phone number</label>
				<input type="tel" id="tqb-contact-phone" name="contact_phone" autocomplete="tel" required />
			</div>

			<div class="tqb-step__nav">
				<button type="button" class="tqb-btn tqb-btn--ghost" data-action="back">Back</button>
				<button type="button" class="tqb-btn tqb-btn--primary" data-action="to-questions">Continue</button>
			</div>
		</section>

		<!-- Step 3: Questions (built by JS — different content for individual vs business) -->
		<section class="tqb-step" data-step="3" hidden>
			<h2 class="tqb-step__title" id="tqb-questions-title">Tell us about your situation</h2>
			<p class="tqb-step__subtitle">Select everything that applies.</p>

			<div id="tqb-business-basics" hidden>
				<!-- Populated by JS: entity type, asset band, revenue band dropdowns -->
			</div>

			<div id="tqb-questions-list">
				<!-- Populated by JS: checklist of line items for the selected quote type -->
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
