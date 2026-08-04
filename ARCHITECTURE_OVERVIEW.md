# TAVOLA QUOTE BUILDER — ARCHITECTURE OVERVIEW

A visual guide to how all pieces fit together.

---

## FORM FLOW (6 STEPS)

```
┌─────────────────────────────────────────────────────────────────────┐
│                    TAVOLA QUOTE BUILDER FORM FLOW                   │
└─────────────────────────────────────────────────────────────────────┘

STEP 1: RETURN TYPE SELECTION
┌──────────────────────────────┐
│ "What kind of return?"       │
│ ☑ Individual                 │
│ ☑ Business                   │
│ [Continue →]                 │
└──────────────────────────────┘
                ↓
        (If Individual is selected)
                ↓
STEP 2: FILING STATUS (NEW) ✨
┌──────────────────────────────┐
│ "What's your filing status?" │
│ ○ Single ($500)              │
│ ○ Married Filing Jointly ($700) │
│ ○ Married Filing Separately ($800) │
│ ○ Head of Household ($650)   │
│ [Continue →]                 │
└──────────────────────────────┘
                ↓
STEP 3: CONTACT INFORMATION
┌──────────────────────────────┐
│ Full Name: _________________ │
│ Email: ____________________ │
│ Phone: ____________________ │
│ [Continue →]                 │
└──────────────────────────────┘
                ↓
STEP 4: QUESTIONS & DETAILS
┌──────────────────────────────┐
│ ≡ PERSONAL TAX RETURN        │
│                              │
│ Q1: W-2 income?              │
│ ○ Yes ○ No                   │
│ Help: "Reported on Form W-2" │
│                              │
│ Q2: Multiple states?         │
│ ○ Yes ○ No                   │
│ (If Yes →) How many? [  ]    │◄─ Conditional!
│                              │
│ ≡ BUSINESS 1 (Optional)      │
│ Business Name: _____________ │
│ Entity Type: [Select ▼]      │
│                              │
│ [+ Add Another Business]     │
│ [Continue →]                 │
└──────────────────────────────┘
                ↓
STEP 5: REVIEW ALL ANSWERS
┌──────────────────────────────┐
│ Contact Info ✓               │
│ Filing Status: MFJ ✓         │
│ Questions Answered: 12 ✓     │
│                              │
│ Name: John Smith             │
│ Filing: Married Filing Jointly│
│ W-2 income: Yes              │
│ States: Yes (2)              │
│ ...more answers...           │
│                              │
│ [Get My Quote →]             │
└──────────────────────────────┘
                ↓
STEP 6: QUOTE RESULT
┌──────────────────────────────┐
│ Your Quote: $2,450           │◄─ Instant
│ ________________             │   (or "Custom Quote" if >100
│ [Get Another Quote]          │    transactions, foreign accts, etc)
└──────────────────────────────┘
```

---

## DATA FLOW: Frontend → Backend → Database

```
┌─────────────────────────────────────────────────────────────────────┐
│                         FRONTEND (JavaScript)                        │
│  tqb-public.js (1,500 lines, 32KB)                                  │
└─────────────────────────────────────────────────────────────────────┘
    │
    ├─ Step 1: Manages checkboxes for return type selection
    │           Stores in state.selectedTypes
    │
    ├─ Step 2: Manages filing status radio buttons (NEW!)
    │           Stores in state.filingStatus
    │           Calculates price: $base + $surcharge
    │
    ├─ Step 3: Manages contact form inputs
    │           Validates email format
    │           Stores in state.contactName/Email/Phone
    │
    ├─ Step 4: Dynamically builds questions from tqbData
    │           Manages Yes/No radio buttons
    │           Shows/hides quantity inputs conditionally
    │           Stores all answers in state.answers
    │
    ├─ Step 5: Builds review display from all state data
    │           Shows every answer before submit
    │
    └─ Step 6: Submits via AJAX to WordPress backend
                │
                ↓
┌─────────────────────────────────────────────────────────────────────┐
│                    WORDPRESS BACKEND (PHP)                          │
│  Handles: public-ajax.js, quote-handler.php                         │
└─────────────────────────────────────────────────────────────────────┘
    │
    ├─ Receives AJAX request with all form data
    │
    ├─ Validates nonce (security check)
    │
    ├─ Calculates price using TQB_Pricing_Engine
    │   ├─ Base price (from options): $500
    │   ├─ Filing status surcharge (from options): $0-300
    │   ├─ Line items (qty × fee): ...
    │   └─ Total: $500 + $surcharge + items
    │
    ├─ Checks for custom quote triggers:
    │   ├─ Crypto > 100 transactions? → custom
    │   ├─ Foreign accounts? → custom
    │   ├─ Assets > thresholds? → custom
    │   └─ ...
    │
    ├─ Sends email to customer (confirmation)
    │
    ├─ Sends notification to admin
    │
    ├─ Syncs to HubSpot (if configured)
    │
    └─ Saves to database
                │
                ↓
┌─────────────────────────────────────────────────────────────────────┐
│                     DATABASE (MySQL/MariaDB)                         │
│  tavola database (WordPress)                                         │
└─────────────────────────────────────────────────────────────────────┘
    │
    ├─ wp_tqb_submissions (NEW business_name column added)
    │   ├─ id, quote_type, contact_name, contact_email, contact_phone
    │   ├─ business_name ← NEW
    │   ├─ answers (JSON)
    │   ├─ calculated_total, is_custom_quote
    │   ├─ status, created_at, updated_at
    │   └─ hubspot_synced, confirmation_email_sent, ...
    │
    ├─ wp_tqb_line_items (tooltips populated)
    │   ├─ id, item_key, label
    │   ├─ quote_type ('individual' or 'business')
    │   ├─ fee, pricing_pattern
    │   ├─ tooltip ← POPULATED (help text)
    │   ├─ reveal_followup ← SET for conditional questions
    │   ├─ threshold_rules (JSON) ← POPULATED
    │   ├─ is_custom_quote_trigger
    │   └─ is_active
    │
    ├─ wp_tqb_question_sets
    │   └─ Groups questions by filing status (base + individual sets)
    │
    ├─ wp_tqb_question_set_items
    │   └─ Links questions to question sets
    │
    ├─ wp_tqb_rate_bands
    │   └─ Business pricing by asset size / revenue
    │
    └─ wp_options (NEW settings added)
        ├─ tqb_individual_base_price: 500
        ├─ tqb_filing_status_label_single: "Single"
        ├─ tqb_filing_status_label_mfj: "Married Filing Jointly"
        ├─ tqb_filing_status_label_mfs: "Married Filing Separately"
        ├─ tqb_filing_status_label_hoh: "Head of Household"
        ├─ tqb_filing_status_price_single: 0
        ├─ tqb_filing_status_price_mfj: 200
        ├─ tqb_filing_status_price_mfs: 300
        ├─ tqb_filing_status_price_hoh: 150
        └─ tqb_filing_status_help_text: {...}
```

---

## STATE MANAGEMENT (Frontend)

```
state = {
    selectedTypes: ['individual', 'business'],
    filingStatus: 'mfj',              ← NEW
    businessCount: 1,
    businessNames: {
        0: 'John Smith Consulting'    ← NEW
    },
    businessTypes: {
        0: 'c_corp'
    },
    contactName: 'John Smith',
    contactEmail: 'john@example.com',
    contactPhone: '(555) 123-4567',
    answers: {
        'individual_w2_wages': {
            question_key: 'w2_wages',
            context: 'individual',
            answer: 'yes',
            quantity: 1
        },
        'individual_multi_state': {
            question_key: 'multi_state',
            context: 'individual',
            answer: 'yes',
            quantity: 2                ← Followup answer
        },
        'individual_crypto': {
            question_key: 'crypto',
            context: 'individual',
            answer: 'yes',
            quantity: 150              ← Triggers custom quote (>100)
        },
        ...
    },
    completedSteps: [1, 2, 3, 4, 5],
    summaryNeedsUpdate: false
}
```

---

## CONDITIONAL QUESTION FLOW

```
┌─────────────────────┐
│ Q: "Multiple states?" │
│ ○ Yes ○ No            │
└─────────────────────┘
        │
        ├─ User selects: YES
        │   └─ Check database: reveal_followup = 1?
        │       └─ YES → Show quantity field
        │           └─ "How many states?" [  ]
        │               └─ User enters: 3
        │                   └─ Store in state.answers
        │
        └─ User selects: NO
            └─ Check database: reveal_followup = 1?
                └─ YES → Hide quantity field
                    └─ Clear quantity input
                        └─ Don't store quantity
```

---

## PRICING CALCULATION

```
INDIVIDUAL RETURN
═════════════════════════════════════════════════════════════════

Base Price (from options):           $500
    + Filing Status Surcharge:       $200 (if Married Filing Jointly)
    ────────────────────────────
Subtotal:                            $700

    + Line Items:
      • W-2 income (qty_times_fee):  1 × $350 = $350
      • Multiple states (qty 2):      2 × $150 = $300
      • Crypto (qty 150 > 100):       CUSTOM QUOTE ✓
    ────────────────────────────
Total Would Be:                      $1,350 (if not custom)
But Crypto > 100 → Custom Quote      ← Route to custom instead of showing price


BUSINESS RETURN
═════════════════════════════════════════════════════════════════

Base Price (from Asset Bands):
    Assets < $250K:                  $1,250
    $250K-$500K:                     $1,250
    $500K-$1M:                       $1,500
    $1M-$2M:                         $1,500
    $2M-$5M:                         $1,750
    $5M-$10M:                        CUSTOM QUOTE
    > $10M:                          CUSTOM QUOTE

    + Revenue Addon:
      < $250K:                       $0
      $250K-$1M:                     $0
      > $1M:                         $200

    + Line Items:
      • K-1s (qty 2):                2 × $25 = $50
      • Foreign partner:             CUSTOM QUOTE ✓
    ────────────────────────────
Total Would Be:                      (Calculated)
But Foreign Partner = Yes → Custom Quote ← Special routing
```

---

## DATABASE SCHEMA RELATIONSHIPS

```
wp_tqb_question_sets
┌──────────────────────────────┐
│ id: 1, name: "Individual"    │ ← Base set
│ return_type: "individual"    │
└──────────────────────────────┘
           │
           ├─ Links to:
           │
┌──────────────────────────────────────┐
│ wp_tqb_question_set_items            │
├──────────────────────────────────────┤
│ question_set_id: 1                   │
│ line_item_id: 1 (w2_wages)           │
│ line_item_id: 2 (multi_state)        │
│ line_item_id: 15 (retirement)        │
│ line_item_id: 16 (meetings)          │
└──────────────────────────────────────┘
           │
           ├─ Resolves to:
           │
┌──────────────────────────────────────┐
│ wp_tqb_line_items                    │
├──────────────────────────────────────┤
│ id: 1                                │
│ item_key: 'w2_wages'                 │
│ label: "Did anyone... W-2 income?"   │
│ tooltip: "This includes wages..."    │◄─ HELP TEXT
│ fee: 350.00                          │
│ reveal_followup: 1                   │◄─ CONDITIONAL
│ threshold_rules: null                │
│                                      │
│ id: 10                               │
│ item_key: 'crypto'                   │
│ label: "Buy/sell/trade crypto?"      │
│ tooltip: "Include Bitcoin, Eth..."   │
│ fee: 250.00                          │
│ reveal_followup: 1                   │◄─ Shows qty field
│ threshold_rules: {                   │◄─ CUSTOM QUOTE TRIGGER
│   "logic": "OR",                     │
│   "conditions": [                    │
│     {"type": "qty", "op": "above", "val": 100},
│     {"type": "value", "op": "above", "val": 100000}
│   ]                                  │
│ }                                    │
│ is_custom_quote_trigger: 1           │◄─ MARKED AS TRIGGER
└──────────────────────────────────────┘
```

---

## ADMIN PANEL CONFIGURATION FLOW

```
WordPress Dashboard (Admin)
│
└─ Settings → Quote Builder → General Tab
   │
   ├─ Filing Status Configuration
   │   ├─ Individual Base Price: [500]
   │   │
   │   ├─ Single
   │   │   ├─ Label: [Single]
   │   │   ├─ Surcharge: [0]
   │   │   ├─ Total Price: $500
   │   │   └─ Help Text: [textarea...]
   │   │
   │   ├─ Married Filing Jointly
   │   │   ├─ Label: [Married Filing Jointly]
   │   │   ├─ Surcharge: [200]
   │   │   ├─ Total Price: $700
   │   │   └─ Help Text: [textarea...]
   │   │
   │   ├─ Married Filing Separately
   │   │   ├─ Label: [Married Filing Separately]
   │   │   ├─ Surcharge: [300]
   │   │   ├─ Total Price: $800
   │   │   └─ Help Text: [textarea...]
   │   │
   │   └─ Head of Household
   │       ├─ Label: [Head of Household]
   │       ├─ Surcharge: [150]
   │       ├─ Total Price: $650
   │       └─ Help Text: [textarea...]
   │
   ├─ [Save Changes]
   │
   └─ Confirmation: Settings saved. Form updated.
       (All changes reflected on front-end immediately)
```

---

## CONDITIONAL REVEAL LOGIC (Simplified)

```javascript
// When user answers a question:
onQuestionAnswered(questionContext, question, answer) {
    if ( question.reveal_followup && answer === 'yes' ) {
        // Show the followup quantity field
        followupDiv.style.display = 'block';
    } else if ( question.reveal_followup && answer === 'no' ) {
        // Hide the followup quantity field
        followupDiv.style.display = 'none';
    }

    // Check if answer triggers custom quote
    if ( question.is_custom_quote_trigger ) {
        if ( checkThresholds(question, answer, quantity) ) {
            // Mark as custom quote → show custom quote screen instead
        }
    }
}
```

---

## ERROR HANDLING & VALIDATION

```
Form Submission
    │
    ├─ Frontend validation (JavaScript)
    │   ├─ All required fields filled?
    │   ├─ Email format valid?
    │   ├─ At least one question answered?
    │   └─ If any fail → Show error in red box
    │
    ├─ AJAX request to server
    │   │
    │   └─ Backend validation (PHP)
    │       ├─ Nonce valid? (security)
    │       ├─ All data received and clean?
    │       ├─ Calculate price correctly?
    │       ├─ Check for custom quote triggers?
    │       └─ If any fail → Return error JSON
    │
    └─ Result
        ├─ Success: Save to DB, send emails, show quote
        └─ Error: Display error message on Step 5
```

---

## FILES & THEIR ROLES

```
JavaScript (Frontend Logic)
├─ public/js/tqb-public.js (32 KB, 1,500 lines)
│  └─ Everything about the wizard UI and user interaction
│
├─ public/js/tqb-public.js.BACKUP
│  └─ Original version (for rollback)
│
└─ public/css/tqb-public.css
   └─ Styling for all form elements + NEW additions

PHP (Backend Logic)
├─ includes/class-tqb-public.php
│  └─ Loads questions from DB, passes to frontend
│
├─ includes/class-tqb-pricing-engine.php
│  └─ Calculates prices on submission
│
├─ includes/class-tqb-quote-handler.php
│  └─ Saves submissions, triggers emails, checks custom quote
│
├─ includes/class-tqb-email.php
│  └─ Sends confirmation/follow-up emails
│
├─ includes/class-tqb-hubspot.php
│  └─ Syncs data to HubSpot CRM (if configured)
│
└─ includes/class-tqb-admin.php
   └─ Admin panel, settings, submissions dashboard

Admin Interface
└─ admin/views/general-tab.php
   └─ Settings for filing status, pricing, config

Database
├─ wp_tqb_submissions
├─ wp_tqb_line_items
├─ wp_tqb_question_sets
├─ wp_tqb_question_set_items
└─ wp_tqb_rate_bands
```

---

## SUMMARY: WHAT MAKES THIS WORK

1. **JavaScript manages all UX** — Form steps, conditional reveals, summary
2. **Database stores configuration** — Questions, pricing, help text
3. **Admin panel lets client change things** — No code editing needed
4. **Backend validates and prices** — Server-side authority
5. **Emails + CRM** — Customer confirmation + admin notification
6. **Custom quote routing** — Automatic for complex returns

Everything is wired together. Every piece has a job. Zero ambiguity.

✅ **This is production-ready code.**
