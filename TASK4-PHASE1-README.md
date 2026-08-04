# Task 4 Phase 1: Filing Status System — Implementation Guide

**Status:** Database & Backend Infrastructure COMPLETE ✅  
**Date:** August 1, 2026  
**Plugin Version:** 1.2

---

## What's Been Implemented (Phase 1)

### ✅ Database Schema
- Added `filing_status` column to `wp_tqb_submissions` table
- Created `wp_tqb_question_sets` table (base sets + filing status variants)
- Created `wp_tqb_question_set_items` table (question mappings with overrides)
- Full inheritance support: base set + variant overrides

### ✅ Plugin Constants
Added to `tavola-quote-builder.php`:
```php
define( 'TQB_FILING_STATUSES', array( 'single', 'mfj', 'mfs', 'hoh' ) );
define( 'TQB_FILING_STATUS_LABELS', [...] );
define( 'TQB_FILING_STATUS_PRICES', [...] );  // Surcharges
```

### ✅ Question Sets Architecture
- **TQB_Question_Sets** class created
- `get_questions()` — loads questions for return type + filing status with inheritance
- `get_filing_status_surcharge()` — returns pricing surcharge
- `apply_filing_status_price()` — adds surcharge to base price

### ✅ Database Migration
Auto-migration in `TQB_Activator::upgrade()`:
- Adds `filing_status` column on activation
- Creates new tables
- Seeds question sets with base + variants
- Pre-populates MFJ wording overrides

### ✅ Pricing Constants
```php
'single' => +$0
'mfj'    => +$200
'mfs'    => +$300
'hoh'    => +$150
```

---

## What's NOT Yet Done (Phase 2 - Frontend)

These require JavaScript & form flow changes:

### Phase 2: Form Flow & Frontend

1. **Form Steps Reordering**
   - Current: Type → Contact Info → Questions  
   - New: Type → Filing Status → Contact Info → Questions

2. **Filing Status Step UI**
   - Add new form step with filing status radio buttons
   - Single, MFJ, MFS, HOH
   - Store selection in form state

3. **Question Loading by Filing Status**
   - In `public/js/tqb-public.js`:
   - After form submits filing status, call AJAX to load questions
   - `wp_ajax_tqb_load_questions` endpoint (new)
   - Backend: Use `TQB_Question_Sets::get_questions()` to load + merge

4. **Pricing with Surcharge**
   - When calculating total, add filing status surcharge
   - In `public/js/tqb-public.js` calculation logic
   - OR in backend: `class-tqb-quote-handler.php`

5. **Personalized Wording**
   - Questions with overrides already loaded from DB
   - Just render the `override_label` if set, else use base `label`
   - Help text from `tooltip` field (already working from earlier task)

6. **Quantity Field Conditional Reveal**
   - Already implemented in schema (`reveal_followup` column)
   - Just need to apply in JS: hide quantity until checkbox checked
   - Show with custom label from `followup_label` or override

7. **Business Name Field**
   - Add text input to business section
   - Collect and store in `answers` JSON
   - Display in admin modal

8. **Upsell Questions**
   - At end of Individual section, add:
     - "Do you need quote for additional personal returns?"
     - "Do you need quote for business returns?"
   - Logic to add another Individual or Business section

---

## File Changes in This Release

### New Files
- `includes/class-tqb-question-sets.php` — Question set manager

### Modified Files
- `tavola-quote-builder.php` — Version bump, constants, include
- `includes/class-tqb-activator.php` — New table creation, migration, seeding

### NOT Modified Yet (Phase 2)
- `public/js/tqb-public.js` — Frontend form flow
- `public/views/form-template.php` — Form HTML
- `public/css/tqb-public.css` — Additional styling (minimal needed)
- `admin/` files — Admin UI for managing overrides (optional, can be manual for now)

---

## Testing Phase 1

### Database Check
After activation, verify:
```sql
SHOW TABLES LIKE 'wp_tqb_question%';  -- Should show 2 new tables
SELECT * FROM wp_tqb_question_sets;  -- Should have 6 sets (Individual base + 4 filing status + Business)
SELECT * FROM wp_tqb_question_set_items;  -- Should have all items mapped
```

### Verify Seeding
```sql
-- Check MFJ overrides exist
SELECT COUNT(*) FROM wp_tqb_question_set_items 
WHERE question_set_id = (SELECT id FROM wp_tqb_question_sets WHERE name = 'Individual_MFJ')
AND override_label IS NOT NULL;  -- Should be 14 MFJ overrides
```

### Test Question Loading
```php
$questions = TQB_Question_Sets::get_questions( 'individual', 'mfj' );
var_dump( $questions );  // Should show merged base + MFJ overrides
```

---

## Phase 2 Work (Next Steps)

After you test Phase 1, we'll build Phase 2 which includes:

1. **AJAX Endpoint** — `wp_ajax_tqb_load_questions`
   - Load questions based on type + filing status
   - Return JSON with full question data

2. **Form Flow Update** — Major JS changes to `tqb-public.js`
   - Add filing status step after return type
   - Call load-questions AJAX
   - Render personalized questions
   - Apply pricing with surcharge

3. **Business Name** — Add to business data collection

4. **Upsells** — Add end-of-section questions

5. **Admin Panel** (Optional) — UI to manage overrides

---

## Deployment Notes

1. **Backup first!** New tables will be created automatically.
2. **Reactivate plugin** to ensure migrations run.
3. **Check DB** for new tables and data.
4. **Test form** (won't work fully until Phase 2 JS updates).

---

## Questions Before Phase 2?

Review Phase 1 and test:
- Do tables exist?
- Are question sets seeded?
- Can you query MFJ overrides?
- Does TQB_Question_Sets class load?

If all ✅, we're ready for Phase 2 (frontend).

---

## Pricing Summary (Implemented)

| Filing Status | Individual Price | Surcharge |
|---------------|------------------|-----------|
| Single        | $500             | $0        |
| MFJ           | $700             | +$200     |
| MFS           | $800             | +$300     |
| HOH           | $650             | +$150     |
| Business      | $700             | N/A       |

These are applied at calculation time via `TQB_Question_Sets::apply_filing_status_price()`.

---

**Ready for testing!** After verification, I'll build Phase 2 (frontend) with full form flow, AJAX, and personalized questions.
