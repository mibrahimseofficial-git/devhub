# TAVOLA QUOTE BUILDER — RUTHLESS ISSUE AUDIT

## THE CORE PROBLEM
The front-end HTML template was updated to add a Filing Status step (step 2), but **the JavaScript was never updated to match the new step numbering**. Result: the form appears broken because buttons have no handlers.

---

## ISSUES FOUND

### 1. **CRITICAL: Step Numbering Mismatch**
**What's broken:**  Filing Status step (step 2) buttons do nothing.

**Why:**  
- HTML template has 6 steps: 1=Return Type, 2=Filing Status, 3=Contact, 4=Questions, 5=Review, 6=Quote
- JS `STEP` object hardcoded: `STEP.TYPE=1, STEP.CONTACT=2, STEP.QUESTIONS=3, STEP.REVIEW=4, STEP.RESULT=5`
- JS looks for `[data-step="2"] [data-action="to-questions"]` — but step 2 in HTML is Filing Status, not Contact
- **None of the Filing Status buttons have handlers defined**

**Evidence:**
- Line 418 of tqb-public.js: `wizard.querySelectorAll( '[data-step="2"] [data-action="to-questions"]' )` — this selector is WRONG
- Line 30 of tqb-public.js: Step constants don't include `FILING_STATUS`
- Template line 62-101: Filing Status step exists but has `data-action="to-contact"` which has no handler

**Fix required:**  
1. Add `STEP.FILING_STATUS = 2` to STEP object
2. Renumber all subsequent steps in JS: CONTACT=3, QUESTIONS=4, REVIEW=5, RESULT=6
3. Add handler for `[data-step="2"] [data-action="to-contact"]` (Continue from Filing Status)
4. Add handler for `[data-step="2"] [data-action="back"]` (Back from Filing Status)
5. Update ALL remaining step selectors to match new numbering
6. Store selected filing status in state (needed for pricing calculation)

---

### 2. **MISSING: Filing Status Backend Configuration**
**What's broken:**  You told the client there's a backend settings page for filing status, but there isn't.

**Current state:**  
- Filing statuses are hardcoded in PHP constants (tavola-quote-builder.php lines 35-48)
- Admin interface has no page to configure filing status labels or prices
- If client wants to change pricing later, requires code edit

**Fix required:**  
- Add filing status configuration to the admin "General" tab (admin/views/general-tab.php)
- Store filing status configs in `wp_options` (e.g., `tqb_filing_status_prices`)
- Load from DB at runtime instead of hardcoded constants
- Add form fields to admin panel for each filing status: label, price surcharge, help text

---

### 3. **INCOMPLETE: Question Flow for Business Section**
**What's broken:**  Business section is missing critical questions from client feedback.

**Client requirements (from feedback doc) — Business section needs:**
1. "What is your business name?" (general info, not a line item — needed to personalize quote)
2. Help text under each business question explaining what's needed
3. Conditional questions: e.g., if "Do you need depreciation?" = Yes, then show "How many assets?" followup
4. "Do you also need quotes for additional personal tax returns?" (end of business section)
5. "Do you also need quotes for any additional business tax returns?" (end of business section)

**Current state:**  
- Business section exists (tqb_line_items rows 17-23 show business questions)
- No "business name" field anywhere (should be in general info section or added to DB)
- Business questions in DB but front-end doesn't show help text from DB
- No conditional followup questions implemented (e.g., depreciation assets count)
- No routing to "add another return" question at end

**Fix required:**  
- Add `business_name` field to submissions table and form state
- Update question rendering to display `help_text` from line items
- Implement conditional question logic (check `reveal_followup` and threshold_rules)
- Add routing buttons at end of personal and business sections

---

### 4. **INCOMPLETE: Personal Section Questions & Help Text**
**What's broken:**  Personal questions are in the database but front-end doesn't load or display them correctly.

**Client requirements:**  
1. First question should be filing status (ALREADY ADDED TO TEMPLATE ✓)
2. All 16 personal questions from client feedback need help text
3. Questions should be reworded to be conversational ("Did anyone in your household...") — currently loading from DB labels which might be generic
4. Conditional followups: e.g., "Did anyone... crypto?" → if YES → "How many transactions?"

**Current state:**  
- Line items 1-16 exist in DB (tqb_line_items) with basic labels
- Database has `tooltip` field but it's empty (NULL)
- JavaScript loads questions from `tqbData.questions` but where does that come from?
- No help text rendering in the form UI

**Fix required:**  
- Populate `tooltip` column in tqb_line_items with help text from client feedback
- Update JavaScript to render tooltip as help text below each question
- Implement conditional reveal logic for followup questions (check `reveal_followup` and `threshold_rules`)
- Test that questions render in correct order

---

### 5. **BROKEN: Summary Panel (Right Sidebar)**
**What's broken:**  The "Your Summary" panel probably shows placeholder text or doesn't update as user progresses.

**Current state:**  
- Template has sidebar (form-template.php line 174-181) with `id="tqb-summary-content"`
- JavaScript must populate this via `updateSummaryPanel()` or similar
- No verification that this is being called or data is being passed correctly

**Fix required:**  
- Trace through `updateSummaryPanel()` calls in tqb-public.js
- Verify it runs after filing status selection, contact info entry, and each question answer
- Ensure it reflects filing status price (e.g., "Single: $500")
- Test on multiple form states

---

### 6. **MISSING: Database Column for Help Text/Tooltips**
**What's broken:**  Database structure exists but isn't being used.

**Current state:**  
- `wp_tqb_line_items` has `tooltip` column (currently unused/NULL)
- No migration or seeding logic to populate tooltips from client feedback

**Fix required:**  
- Write migration SQL to populate all tooltips
- Or add them via admin UI in the Line Items tab

---

### 7. **MISSING: Business Name Field**
**What's broken:**  No business name capture.

**Client requirements:**  "let's also ask for the business name. This will help personalize the experience throughout the questionnaire"

**Current state:**  
- No field in form template
- No column in `wp_tqb_submissions` table

**Fix required:**  
- Add `business_name` field to HTML form (appears after business type selection or in business details section)
- Add `business_name` column to `wp_tqb_submissions` table (or store in JSON `answers` field)
- Update form state to capture it

---

### 8. **BROKEN: Conditional Question Logic**
**What's broken:**  Followup questions don't appear conditionally.

**Client examples from feedback:**
- "Did anyone... live or work in more than one state?" → YES → "How many states?" (quantity input)
- "Does your business have... brokerage account?" → YES → "How many accounts?" (quantity input)
- Crypto transactions: if YES → "How many crypto transactions?" → if >100 OR >$100K → CUSTOM QUOTE

**Current state:**  
- Database schema has `reveal_followup` (tinyint) and `threshold_rules` (JSON) columns
- JavaScript doesn't use these to conditionally show/hide followup questions
- Quantity inputs appear always, not conditionally

**Fix required:**  
- Implement conditional reveal logic in JavaScript
- When answer to a question is "Yes", check `reveal_followup` and show followup question
- Parse `threshold_rules` JSON and route to custom quote if threshold exceeded

---

### 9. **MISSING: Custom Quote Routing for Crypto**
**What's broken:**  High crypto transaction counts should trigger custom quote, but no logic for it.

**Client requirement:**  "If more than 100 transactions or $100K [total], route to custom quote"

**Current state:**  
- Line item 10 (crypto) in DB has `is_custom_quote_trigger=0` (should be 1 or handled via threshold_rules)
- `threshold_rules` shows: `{"logic":"AND","conditions":[{"type":"qty","operator":"above","value":100}]}`
- No JavaScript logic to detect threshold breach and set `is_custom_quote = 1`

**Fix required:**  
- Implement threshold checking in calculateIndividualPreview() or in AJAX submit handler
- If crypto transactions > 100 OR total value > $100K, set is_custom_quote flag
- Route to custom quote step instead of showing price quote

---

### 10. **UNCLEAR: Pricing Calculation Logic**
**What's broken:**  Not sure if client-side and server-side pricing match.

**Risk:**  Live summary panel shows one price, submitted quote shows different price.

**Current state:**  
- Client-side: `calculateIndividualPreview()` and `calculateBusinessPreview()` in tqb-public.js
- Server-side: `TQB_Pricing_Engine::calculate_individual()` and `calculate_business()` in includes/class-tqb-pricing-engine.php
- Comments say "BOTH places need updating" if logic changes

**Fix required:**  
- Run side-by-side test: submit same answers on client-side (check summary) and submit form (check server result)
- If prices don't match, identify which is wrong and fix it
- Add unit tests (or at least manual test cases) for pricing

---

## PRIORITY FIX ORDER
1. **STEP NUMBERING** (blocks all form navigation)
2. **FILING STATUS BACKEND CONFIG** (backend requirement for client)
3. **CONDITIONAL QUESTION LOGIC** (feature requirement)
4. **HELP TEXT RENDERING** (UX requirement)
5. **BUSINESS NAME FIELD** (personalization)
6. **CUSTOM QUOTE ROUTING** (pricing logic)

---

## SUMMARY
This plugin has a **broken step numbering architecture**. The front-end was updated to add Filing Status as step 2, but the JavaScript still thinks Contact is step 2. Until this is fixed, the form is non-functional. Additionally, the backend admin interface is missing configuration options for filing statuses, and the front-end is missing help text, conditional questions, and business name capture.

**Trash assessment:** The previous AI fixes were superficial (added HTML) but didn't trace through the entire stack (HTML + CSS + JS + PHP + DB). This is why the form "looks right" but doesn't work. Your instinct to start over is correct.
