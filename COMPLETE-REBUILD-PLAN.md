# COMPLETE TAVOLA QUOTE BUILDER REBUILD AUDIT

## CURRENT STATE ANALYSIS

### ✅ WHAT WORKS
1. Database schema has `filing_status` column
2. Database migration prepared
3. Admin dropdown added to line-items-tab.php
4. Admin save handler captures filing_status
5. TQB_DB::update_line_item() saves filing_status
6. get_all_questions() fetches filing_status from DB

### ❌ WHAT'S BROKEN OR MISSING

#### 1. **FRONTEND DATA NOT COMPLETE**
- `wp_localize_script` passes `tqbData.questions` with filing_status ✅
- BUT `tqbData.individualItems` and `tqbData.businessItems` do NOT include filing_status ❌
- JS filtering uses `tqbData.questions` not `individualItems` (correct)
- **FIX**: Ensure filing_status is in tqbData.questions properly

#### 2. **JS FILTERING LOGIC BROKEN**
- Filter checks `q.filing_status` on questions  
- But logic should be:
  - If `filing_status` is NULL/empty → show for ALL filers ✅
  - If `filing_status === 'single'` → show ONLY for Single filers ✅
  - If `filing_status === 'mfj'` → show ONLY for MFJ filers ✅
  - etc.
- Current logic is correct BUT must be tested

#### 3. **RETURN TYPE BOX STYLING**
- Need selected state styling: thick border + filled checkbox
- CSS added but may not be complete

#### 4. **PROGRESS BAR CLICKABLE**
- Setup in init but may have issues
- Need to verify backwards-only clicking works

#### 5. **SUMMARY WITH BULLET POINTS**
- New updateSummaryPanel() written
- Need to verify it calculates prices correctly
- Must include filing status price in total

#### 6. **CHECKBOX LOGIC**
- Yes/No radios replaced with single checkbox ✅
- Quantity reveal on check/uncheck ✅
- But need to verify state.answers handling

---

## IMPLEMENTATION CHECKLIST

### STEP 1: VERIFY ADMIN SAVE FLOW
- [ ] Dropdown value POSTs correctly
- [ ] PHP captures: `$filing_status = sanitize_key(...)`
- [ ] TQB_DB::update_line_item gets `filing_status` field
- [ ] Database UPDATE statement includes filing_status
- [ ] Value persists in DB

**Files to check:**
- admin/views/line-items-tab.php (dropdown HTML)
- includes/class-tqb-admin.php (capture + pass)
- includes/class-tqb-db.php (save to DB)

### STEP 2: VERIFY FRONTEND DATA FLOW
- [ ] get_all_questions() fetches filing_status ✅
- [ ] wp_localize_script includes tqbData.questions ✅
- [ ] JavaScript receives filing_status in questions

**Files to check:**
- includes/class-tqb-public.php (localize)
- public/js/tqb-public.js (state initialization)

### STEP 3: VERIFY FILTERING LOGIC
- [ ] buildIndividualQuestionsSection filters by filing_status
- [ ] Logic: empty = show all, 'single' = show only single, etc.
- [ ] Questions re-filter when filing status changes
- [ ] Questions appear/disappear immediately

**Files to check:**
- public/js/tqb-public.js (buildIndividualQuestionsSection)

### STEP 4: VERIFY UI/UX
- [ ] Return type box styling (border, checkbox, shadow)
- [ ] Checkbox + quantity field behavior
- [ ] Progress bar clickable backwards only
- [ ] Summary shows bullet points + total

**Files to check:**
- public/css/tqb-public.css (all styling)
- public/js/tqb-public.js (all event handlers)
- public/views/form-template.php (HTML structure)

---

## KNOWN ISSUES TO FIX

### Issue #1: filing_status column in individualItems format
**Problem**: format_items_for_js() doesn't include filing_status
**Impact**: If JS uses individualItems instead of questions, filing_status won't be available
**Fix**: Add filing_status to format_items_for_js()
```php
'filing_status'    => ! empty( $item['filing_status'] ) ? $item['filing_status'] : null,
```

### Issue #2: Summary calculation needs filing_status price
**Problem**: updateSummaryPanel() calculates total but must include filing_status price
**Current**: Adds filing status price
**Verify**: Test with different filing statuses to ensure price is correct

### Issue #3: Progress bar styling for disabled forward steps
**Problem**: Forward steps should be visually disabled (greyed out)
**Current**: CSS added but need to verify
**Fix**: Update CSS for disabled state

### Issue #4: Return type selected state
**Problem**: Selected box needs thick border and filled checkbox
**Current**: CSS added
**Verify**: Visual appearance in browser

---

## DATABASE STATE

### Tables Required
- wp_tqb_line_items (with filing_status VARCHAR(50) column)
- wp_tqb_submissions (with business_name column)
- wp_tqb_rate_bands
- wp_tqb_question_sets
- wp_tqb_question_set_items

### Expected Column: wp_tqb_line_items.filing_status
- NULL = show for all filing statuses (default)
- 'single' = show only for single filers
- 'mfj' = show only for MFJ filers
- 'mfs' = show only for MFS filers
- 'hoh' = show only for HOH filers

---

## TESTING SCENARIOS

### Scenario 1: Admin Sets Question to "MFJ Only"
1. Go to Admin → Quote Builder → Individual tab
2. Find "W-2 wage income"
3. Set "Filing Status Filter" to "MFJ Only"
4. Click Save
5. **Expected DB**: `filing_status = 'mfj'`

### Scenario 2: Frontend Selects Single
1. Visit frontend form
2. Select "Individual"
3. Select "Single" filing status
4. Go to Questions step
5. **Expected**: "W-2 wage income" DOES NOT appear

### Scenario 3: Frontend Changes to MFJ
1. On Questions step (previous state: Single)
2. Go back to Filing Status step
3. Change to "MFJ"
4. Go back to Questions step
5. **Expected**: "W-2 wage income" NOW APPEARS

### Scenario 4: Summary Shows Correct Total
1. Select MFJ (price $700)
2. Check "W-2 wage income" ($350)
3. Check "Interest/Dividends" ($25)
4. **Expected Summary**:
   - YOUR INFO: Name, Email
   - PERSONAL TAX RETURN:
     - • W-2 wage income - $350.00
     - • Interest/Dividends - $25.00
   - Subtotal: $1,075.00
   - Estimated Total: $1,075.00

### Scenario 5: Click Back in Progress Bar
1. On Step 4 (Questions)
2. Click Step 2 (Filing Status) in progress bar
3. **Expected**: Jump back to Step 2, Step 3/4/5 grayed out

---

## FILES TO VERIFY/FIX

### ADMIN
- [ ] admin/views/line-items-tab.php
  - Dropdown HTML correct?
  - Name attribute correct?
- [ ] includes/class-tqb-admin.php
  - Captures filing_status?
  - Passes to TQB_DB?
- [ ] includes/class-tqb-db.php
  - update_line_item() includes filing_status?
  - SQL UPDATE statement correct?

### FRONTEND DATA
- [ ] includes/class-tqb-public.php
  - get_all_questions() fetches filing_status?
  - wp_localize_script passes questions?

### FRONTEND UI/JS
- [ ] public/js/tqb-public.js
  - buildIndividualQuestionsSection filters correctly?
  - Progress bar click handlers work?
  - updateSummaryPanel calculates correctly?
  - setupProgressBarNavigation() enabled?
- [ ] public/css/tqb-public.css
  - Return type styling complete?
  - Progress bar disabled state styled?
  - Summary styling correct?
- [ ] public/views/form-template.php
  - Progress bar HTML correct?
  - Summary panel HTML correct?

---

## FINAL DELIVERABLE

### The zip file should include:
- ✅ filing_status dropdown in admin
- ✅ filing_status saves to database
- ✅ Questions filter by filing status on frontend
- ✅ Checkboxes + quantity fields
- ✅ Progress bar clickable backwards only
- ✅ Summary with bullet points + total
- ✅ Return type box styling
- ✅ All CSS/JS properly implemented
- ✅ No old/redundant code
- ✅ Clean, production-ready

---

## NEXT ACTION

Run through EACH file systematically and verify/fix any issues found.
Start with admin save flow, then data flow, then UI/JS logic.
