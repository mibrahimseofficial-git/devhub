# Task 4 Phase 2: Filing Status Frontend Implementation

**Status:** Phase 2 COMPLETE ✅ (Ready for Testing)  
**Date:** August 1, 2026  
**Plugin Version:** 1.2  
**Form Steps:** 6 (Type → Filing Status → Contact → Questions → Review → Result)

---

## What's New in Phase 2

### ✅ Database
- All tables from Phase 1 working
- Run SQL script if tables didn't auto-create

### ✅ Form Template
- Step 2 added: Filing Status selector
- Filing status cards with pricing display
- Updated progress bar (6 steps)
- Step numbers corrected

### ✅ CSS
- Filing status card styling
- Responsive layout
- Radio button styling with checkmarks

### ✅ AJAX Backend
- `wp_ajax_tqb_load_questions` endpoint
- Returns personalized questions JSON
- Handles filing status variants with inheritance

### ✅ Constants & Helpers
- Pricing surcharges defined
- Filing status labels
- Question loading with filing status support

### ⏳ JavaScript (Requires Manual Integration)
- See TASK4-JS-ADDITIONS.md
- Needs integration into tqb-public.js

---

## File Changes in Phase 2

### New Files
- `includes/class-tqb-public-ajax.php` — AJAX question loader
- `TASK4-JS-ADDITIONS.md` — JavaScript code snippets
- `TASK4-CREATE-TABLES.sql` — Manual table creation (if needed)
- `TASK4-PHASE1-README.md` — Phase 1 docs
- This file

### Modified Files
- `public/views/form-template.php` — Added step 2 filing status
- `public/css/tqb-public.css` — Added filing status styling
- `tavola-quote-builder.php` — Added AJAX handler include
- `includes/class-tqb-activator.php` — Added table creation (Phase 1)
- `includes/class-tqb-question-sets.php` — Question loading (Phase 1)

---

## Installation & Testing Steps

### Step 1: Backup & Upload
1. Backup current WordPress installation
2. Replace plugin files with new ZIP
3. Deactivate & Reactivate plugin

### Step 2: Verify Database
Run in phpMyAdmin:
```sql
SELECT COUNT(*) FROM wp_tqb_question_sets;
SELECT COUNT(*) FROM wp_tqb_question_set_items;
```

Should return:
- Question Sets: 6 (Individual base + 4 filing + Business)
- Question Set Items: ~25+ (all questions mapped)

If tables don't exist, run `TASK4-CREATE-TABLES.sql` in phpMyAdmin.

### Step 3: Test AJAX Endpoint
Add this to browser console (on site with form):
```javascript
fetch(
  '/wp-admin/admin-ajax.php',
  {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=tqb_load_questions&return_type=individual&filing_status=mfj&security=nonce_here'
  }
).then(r => r.json()).then(d => console.log(d))
```

Should return JSON array of questions.

### Step 4: JavaScript Integration (MANUAL)
Edit `public/js/tqb-public.js`:

1. **Update STEP constant (line 30):**
```javascript
var STEP = { TYPE: 1, FILING: 2, CONTACT: 3, QUESTIONS: 4, REVIEW: 5, RESULT: 6 };
```

2. **Update state object (line ~39-45):**
Add after existing state:
```javascript
filingStatus: null,   // single, mfj, mfs, hoh
questions: {},        // Cached questions by type+status
```

3. **Add function handlers:**
Copy all functions from `TASK4-JS-ADDITIONS.md` into tqb-public.js (inside the IIFE, after state/STEP definitions)

4. **Update event listeners:**
Find the start-quote button handler (line ~381) and update it to call `handleStartQuote()`

5. **Add new listeners:**
Add filing status radio change listeners and to-contact button listeners (see TASK4-JS-ADDITIONS.md)

---

## How It Works (Flow)

### Step 1: Return Type Selection
- User selects "Individual" and/or "Business"
- Continue button → go to Step 2

### Step 2: Filing Status (Individual Only)
- If Individual selected: Show filing status cards (Single/MFJ/MFS/HOH)
- Display pricing for each status
- User selects one → Continue
- If Business only: Skip to Step 3 (Contact)

### Step 3: Contact Information
- Collect Name, Email, Phone
- Continue → Step 4 (Load questions via AJAX)

### AJAX: Load Questions
- Frontend calls `/wp-admin/admin-ajax.php?action=tqb_load_questions`
- Passes: return_type (individual/business), filing_status (if individual)
- Backend: `TQB_Question_Sets::get_questions()` loads + merges base + overrides
- Returns JSON: array of question objects
- Questions include personalized labels (e.g., "you or your spouse" for MFJ)

### Step 4: Questions
- Render questions for each selected return type
- Questions display with personalized wording
- Quantity fields hidden by default (reveal on checkbox)
- User answers questions

### Step 5: Review
- Show all answers + contact info
- Summary panel updates with pricing (includes filing status surcharge)

### Step 6: Result
- Submit to server
- Backend applies filing status surcharge to total
- Display quote or custom-quote message

---

## Pricing with Filing Status

### Base Individual Prices
```
Single: $500
MFJ:    $500 + $200 = $700
MFS:    $500 + $300 = $800
HOH:    $500 + $150 = $650
```

### Application Points
1. **Frontend Summary** (for preview): Calculate with surcharge
2. **Backend Calculation** (on submit): Apply via `TQB_Question_Sets::apply_filing_status_price()`
3. **Final Quote**: Server-calculated total is authoritative

---

## Personalized Wording (MFJ Example)

### Default (Individual Base)
- "Did anyone in your household receive W-2 income?"
- "Did anyone in your household have a rental property?"

### MFJ Override
- "Did you or your spouse receive W-2 income?"
- "Did you or your spouse have a rental property?"

All overrides are in `tqb_question_set_items.override_label` for the MFJ set.

---

## Testing Checklist

- [ ] Database tables created
- [ ] AJAX endpoint returns questions
- [ ] Form Step 1: Type selection works
- [ ] Form Step 2: Filing status shows only for Individual
- [ ] Form Step 2: Filing status radio selection works
- [ ] Form Step 3: Contact info required validation works
- [ ] AJAX: Questions load on Step 3 → 4 transition
- [ ] Questions render with personalized wording
- [ ] Quantity fields hidden/show on checkbox
- [ ] Summary shows filing status pricing surcharge
- [ ] Form submit works end-to-end
- [ ] Quote displays with correct total (base + surcharge + questions)

---

## Known Limitations (Phase 2)

1. **JavaScript Not Auto-Integrated**: Must manually add to tqb-public.js
2. **Business Entity Selection**: Not yet implemented (coming in Phase 2b)
3. **Upsell Questions**: Not yet implemented (coming Phase 3)
4. **Business Name Field**: Not yet added (coming Phase 3)
5. **Admin UI**: Override management via code/SQL only (manual for now)

---

## Troubleshooting

### Tables Don't Exist After Activation
→ Run `TASK4-CREATE-TABLES.sql` in phpMyAdmin

### AJAX returns 403 error
→ Check nonce in `tqbData.nonce` (should be in wp_localize_script call)

### Questions don't load on Step 4
→ Check browser console for errors
→ Verify `tqbData.ajaxUrl` is set correctly
→ Confirm AJAX endpoint nonce matches frontend

### Personalized wording not showing
→ Check if override_label is set in question_set_items
→ Verify frontend is using `question.label` (which resolves overrides)

### Pricing doesn't include surcharge
→ Check if `filingStatus` is saved to submission
→ Verify `TQB_Question_Sets::apply_filing_status_price()` is called
→ Check `TQB_FILING_STATUS_PRICES` constant

---

## What's Not Yet Done (Phase 3)

1. **Business Entity Selection** — Business questions + entity dropdown
2. **Upsell Questions** — End-of-individual questions to add business/additional
3. **Business Name Field** — Capture business name in submission
4. **Admin Panel** — UI to manage question overrides (currently code-only)
5. **Multiple Returns** — Support adding another individual/business return

---

## Code References

### Key Functions
- `TQB_Question_Sets::get_questions()` — Load questions with inheritance
- `TQB_Public_AJAX::ajax_load_questions()` — AJAX endpoint
- `handleFilingStatusSelected()` — Frontend filing status handler
- `loadQuestionsForFilingStatus()` — Frontend AJAX call
- `renderQuestion()` — Frontend question renderer

### Key Files
- `includes/class-tqb-question-sets.php` — Question loading logic
- `includes/class-tqb-public-ajax.php` — AJAX endpoint
- `public/views/form-template.php` — Form HTML
- `public/css/tqb-public.css` — Styling
- `public/js/tqb-public.js` — Frontend logic (needs manual update)

---

## Next Steps (After Testing)

1. ✅ Confirm database tables created
2. ✅ Test form flow (Steps 1-6)
3. ✅ Verify filing status surcharge in final quote
4. → Then proceed to Phase 3 (business entity, upsells, business name)

---

## Support Notes

- Filing status is **required** for individual quotes
- Business quotes **don't use** filing status (ignored)
- Questions are **loaded via AJAX** after contact info
- Pricing surcharge is **added server-side** (backend authoritative)
- Personalization uses **database inheritance** (no hardcoded logic)

---

**Ready for testing!** After you verify all checkboxes above, we can move to Phase 3 (business entity selection, upsell questions, business name field).
