# TAVOLA QUOTE BUILDER — COMPLETE IMPLEMENTATION GUIDE

**Status:** Full rebuild with all client feedback features
**Estimated time:** 1-2 hours (mostly copy-paste + testing)
**Difficulty:** Medium (database migration + file replacement)

---

## WHAT'S BEING FIXED

### Critical Issues
1. **Step numbering mismatch** - Filing Status (step 2) had no event handlers
2. **Missing backend configuration** - Filing statuses were hardcoded
3. **Missing help text** - Database has `tooltip` column but it was never populated or rendered
4. **Missing business name field** - No place to capture business name
5. **Missing conditional questions** - Followup questions didn't reveal/hide based on answers

### New Features Being Added
- Help text renders below each question from database
- Quantity followup questions appear only when answer is "Yes"
- Business name field in business sections
- Filing status pricing in admin UI
- Crypto custom quote routing (>100 transactions or >$100K)
- "Add another return" routing at end of sections
- Proper summary panel with filing status pricing

---

## STEP 1: DATABASE MIGRATION

### 1a. Add business_name column to submissions table

```sql
ALTER TABLE `wp_tqb_submissions` 
ADD COLUMN `business_name` varchar(255) DEFAULT NULL AFTER `contact_phone`;
```

Run this in phpMyAdmin → Database: tavola → SQL tab.

### 1b. Populate tooltip/help text columns

Run the complete SQL migration script: `/home/claude/01-db-migration.sql`

**What it does:**
- Populates empty `tooltip` column in `wp_tqb_line_items` with help text from client feedback
- Sets `reveal_followup = 1` for questions that have conditional followups
- Adds `threshold_rules` JSON for crypto (>100 transactions or >$100K)
- Marks crypto as `is_custom_quote_trigger = 1`
- Ensures all questions are set to `is_active = 1`

---

## STEP 2: REPLACE THE JAVASCRIPT FILE

### Replace `/public/js/tqb-public.js`

1. Backup current file:
   ```bash
   cp public/js/tqb-public.js public/js/tqb-public.js.backup
   ```

2. Copy new file from `/home/claude/tqb-public-FIXED.js` to `public/js/tqb-public.js`

**What changed:**
- Added `STEP.FILING_STATUS = 2` constant
- Renumbered all subsequent steps: CONTACT=3, QUESTIONS=4, REVIEW=5, RESULT=6
- Added `setupStep2FilingStatus()` function with event handlers
- Updated all step selectors to match new numbering
- Implemented conditional question reveal logic (checks `reveal_followup` flag)
- Added help text rendering from `question.tooltip`
- Added business name field capture
- Added quantity followup fields with proper naming
- Updated summary panel to show filing status with pricing
- Fixed pricing preview to include filing status surcharge

---

## STEP 3: VERIFY HTML TEMPLATE

### Check `/public/views/form-template.php`

The template already has the correct structure (6 steps with filing status as step 2).

**Verify these attributes exist:**

```html
<!-- Step 2 must have data-step="2" and filing status ID -->
<section class="tqb-step" data-step="2" hidden id="tqb-filing-status-step">
    <!-- ... filing status radios ... -->
    <button type="button" class="tqb-btn tqb-btn--primary" data-action="to-contact" disabled>Continue</button>
    <button type="button" class="tqb-btn tqb-btn--ghost" data-action="back">Back</button>
</section>

<!-- Step 3 must have data-step="3" (was step 2) -->
<section class="tqb-step" data-step="3" hidden>
    <!-- ... contact fields ... -->
    <button type="button" class="tqb-btn tqb-btn--primary" data-action="to-questions">Continue</button>
</section>

<!-- Step 4 must have data-step="4" (was step 3) -->
<section class="tqb-step" data-step="4" hidden>
    <!-- ... questions ... -->
    <button type="button" class="tqb-btn tqb-btn--primary" data-action="to-review">Review My Answers</button>
</section>

<!-- Step 5 must have data-step="5" (was step 4) -->
<section class="tqb-step" data-step="5" hidden>
    <!-- ... review ... -->
    <button type="button" class="tqb-btn tqb-btn--primary" data-action="submit">Get My Quote</button>
</section>

<!-- Step 6 must have data-step="6" (was step 5) -->
<section class="tqb-step" data-step="6" hidden>
    <!-- ... quote result ... -->
</section>
```

✅ **If these are correct, template is good. No changes needed.**

---

## STEP 4: ADD FILING STATUS DATA TO JAVASCRIPT

### Update the AJAX localization in `includes/class-tqb-public.php`

Find where `wp_localize_script()` is called for `tqb-public` and ensure it includes:

```php
$filing_status_prices = array(
    'single' => (int) get_option( 'tqb_filing_status_price_single', 0 ),
    'mfj'    => (int) get_option( 'tqb_filing_status_price_mfj', 200 ),
    'mfs'    => (int) get_option( 'tqb_filing_status_price_mfs', 300 ),
    'hoh'    => (int) get_option( 'tqb_filing_status_price_hoh', 150 )
);

$filing_status_labels = array(
    'single' => get_option( 'tqb_filing_status_label_single', 'Single' ),
    'mfj'    => get_option( 'tqb_filing_status_label_mfj', 'Married Filing Jointly' ),
    'mfs'    => get_option( 'tqb_filing_status_label_mfs', 'Married Filing Separately' ),
    'hoh'    => get_option( 'tqb_filing_status_label_hoh', 'Head of Household' )
);

wp_localize_script( 'tqb-public', 'tqbData', array(
    'ajaxUrl'                  => admin_url( 'admin-ajax.php' ),
    'nonce'                    => wp_create_nonce( 'tqb_quote_submission' ),
    'questions'                => $this->get_all_questions(),
    'filing_status_prices'     => $filing_status_prices,
    'filing_status_labels'     => $filing_status_labels,
    // ... other data ...
) );
```

**What this does:** Makes filing status pricing and labels available to the JavaScript so it can:
- Display the correct price next to each filing status option
- Show filing status in the summary panel
- Include the surcharge in pricing calculations

---

## STEP 5: ADD ADMIN UI FOR FILING STATUS CONFIGURATION

### Update `admin/views/general-tab.php`

Add the filing status configuration section from `/home/claude/admin-general-tab-ADDITIONS.php`.

This adds:
- Input fields for each filing status label
- Input fields for surcharge amounts (added to base price)
- Textarea for help text
- Display of total price calculation

### Update `includes/class-tqb-admin.php`

Add the sanitization and save function from the admin additions file.

This handles saving all filing status options to the database.

---

## STEP 6: VERIFY DATABASE HELPERS

### Check `includes/class-tqb-question-sets.php`

This class should have a function that loads all questions with their full metadata (including `tooltip`).

**It should return data like:**
```php
array(
    'item_key' => 'w2_wages',
    'label' => 'Did anyone in your household receive W-2 income from an employer?',
    'quote_type' => 'individual',
    'tooltip' => 'This includes wages, salaries, bonuses, ...',
    'reveal_followup' => 1,
    'threshold_rules' => null,
    'sort_order' => 0,
    // ... other fields ...
)
```

If this function doesn't exist, create it:

```php
public function get_all_questions_for_frontend() {
    global $wpdb;
    $table = $wpdb->prefix . 'tqb_line_items';
    
    $questions = $wpdb->get_results( "
        SELECT 
            id, item_key, label, quote_type, tooltip, 
            reveal_followup, threshold_rules, sort_order,
            is_custom_quote_trigger
        FROM $table
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC
    " );
    
    return $questions;
}
```

---

## STEP 7: TEST THE FORM END-TO-END

### Test Checklist

- [ ] **Form loads** - All 6 steps visible in progress bar
- [ ] **Step 1 works** - Can select Individual and/or Business, Continue button enables
- [ ] **Step 2 appears** - When Individual is selected, Filing Status step shows
- [ ] **Step 2 works** - Can select filing status, see pricing (e.g., "Married Filing Jointly - $700"), Continue button enables
- [ ] **Step 3 works** - Can enter name, email, phone
- [ ] **Step 4 works** - Questions render with help text below each one
- [ ] **Conditional reveal** - Answer "Yes" to "multiple states" question → quantity field appears
- [ ] **Conditional hide** - Answer "No" to same question → quantity field disappears
- [ ] **Business name** - When business section appears, business name field is first
- [ ] **Step 5 works** - Review shows all answers
- [ ] **Step 6 works** - After submit, shows quote result
- [ ] **Summary panel** - Shows selected return type, filing status, and pricing

### Test Crypto Routing

1. Select Individual
2. Select any filing status
3. Enter contact info
4. Answer "Yes" to crypto question
5. Enter quantity: 150 (above 100 threshold)
6. Submit
7. Result should say: "Based on your responses, your quote requires custom pricing" instead of showing an instant dollar amount

### Test Business Routing

1. Select Business
2. Enter business name
3. Select entity type
4. Answer questions
5. At end, should see routing questions: "Do you also need a quote for additional personal/business returns?"

---

## STEP 8: VERIFY PRICING CALCULATION

### Backend verification

The server-side pricing engine should still calculate correctly. Verify:

```php
// In includes/class-tqb-pricing-engine.php
// For individual returns, should add filing status surcharge:
$base_price = 500; // or from options
$filing_status_surcharge = get_option( 'tqb_filing_status_price_' . $filing_status, 0 );
$total = $base_price + $filing_status_surcharge + $line_items_total;
```

The JavaScript mirrors this for the live preview panel. **Both must match** or the summary will show a different price than the submitted quote.

---

## STEP 9: VERIFY CUSTOM QUOTE ROUTING

### Backend verification

In `includes/class-tqb-quote-handler.php`, the submission should check:

```php
// Check if answers trigger custom quote
$is_custom = false;
$custom_reason = null;

// Crypto: if qty > 100 or value > $100K
if ( isset( $answers['crypto'] ) && $answers['crypto']['answer'] === 'yes' ) {
    $qty = $answers['crypto']['quantity'];
    if ( $qty > 100 ) {
        $is_custom = true;
        $custom_reason = 'crypto_high_volume';
    }
}

// Foreign accounts: auto custom quote
if ( isset( $answers['foreign_accounts'] ) && $answers['foreign_accounts']['answer'] === 'yes' ) {
    $is_custom = true;
    $custom_reason = 'foreign_accounts';
}

// ... other triggers ...
```

---

## TROUBLESHOOTING

### Issue: Filing Status step doesn't appear
- **Check:** Is Individual selected in Step 1?
- **Check:** Does `form-template.php` have `id="tqb-filing-status-step"`?
- **Check:** Is JavaScript loaded without errors? (Browser console → F12)

### Issue: Help text doesn't show
- **Check:** Are `tooltip` columns populated in database? Run migration again.
- **Check:** Is JavaScript calling `question.tooltip` in `createQuestionElement()`?
- **Check:** Does CSS for `.tqb-help-text` exist? Should be in `public/css/tqb-public.css`

### Issue: Quantity fields don't appear
- **Check:** Are `reveal_followup` values set to 1 in database?
- **Check:** Is JavaScript checking `question.reveal_followup` before showing followup?

### Issue: Filing status pricing doesn't show
- **Check:** Are filing status prices saved in `wp_options`?
- **Check:** Is `wp_localize_script` passing `filing_status_prices` to `tqbData`?
- **Check:** Does JavaScript access `tqbData.filing_status_prices`?

### Issue: Business name field doesn't appear
- **Check:** Does Step 4 questions building include business name field?
- **Check:** Is `buildBusinessQuestionsSection()` being called?

---

## DEPLOYMENT CHECKLIST

Before going live:

- [ ] Database migration run successfully
- [ ] `public/js/tqb-public.js` replaced with new version
- [ ] `public/views/form-template.php` verified (or use existing)
- [ ] `includes/class-tqb-public.php` updated with filing status localization
- [ ] `admin/views/general-tab.php` updated with filing status admin UI
- [ ] `includes/class-tqb-admin.php` updated with filing status save handlers
- [ ] Filing status prices configured in admin panel
- [ ] Test form submission end-to-end
- [ ] Verify pricing matches on summary panel and submitted quote
- [ ] Test crypto routing (>100 transactions → custom quote)
- [ ] Test business routing (can add another personal/business return)
- [ ] Test on mobile (responsive)
- [ ] Browser console shows no errors or warnings

---

## CLIENT COMMUNICATION

After deployment, the client can:

1. **Configure filing status pricing** in admin panel (Settings → Quote Builder → General → Filing Status Configuration)
2. **Update help text** for any question from the Line Items tab
3. **Enable/disable questions** by toggling is_active in Line Items tab
4. **Users now see:**
   - Help text explaining what's needed for each answer
   - Followup questions appear only when relevant
   - Real-time summary showing progress and pricing
   - Business name field for personalization
   - Routing to additional returns at end of each section

---

## FILES CHANGED SUMMARY

| File | Change | Type |
|------|--------|------|
| `wp_tqb_submissions` | Add `business_name` column | Database |
| `wp_tqb_line_items` | Populate `tooltip` values | Database |
| `public/js/tqb-public.js` | Complete rebuild | JavaScript |
| `admin/views/general-tab.php` | Add filing status config UI | Admin |
| `includes/class-tqb-admin.php` | Add filing status save handlers | PHP |
| `includes/class-tqb-public.php` | Update localization | PHP |

---

## ESTIMATED EFFORT

- Database migration: 5 mins
- JS replacement: 5 mins
- Admin UI setup: 10 mins
- Verification: 15 mins
- Testing: 30 mins
- **Total: ~1 hour**

---

## WHAT THE CLIENT GETS

✅ Form is now fully functional (step numbering fixed)
✅ All client feedback features implemented
✅ Help text under every question
✅ Conditional followup questions
✅ Business name personalization
✅ Backend admin panel for configuration
✅ Proper pricing display with filing status
✅ Custom quote routing for complex returns
