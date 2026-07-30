# Tavola Group — Self-Service Quote Builder Plugin
## Project Specification (source of truth — paste this into every new AI session)

Last updated: 2026-07-29

---

## 1. Project Overview

Custom WordPress plugin for tavola.group (Elementor-based site). Provides a self-service
questionnaire that lets prospects (Individual or Business tax clients) get an instant
price quote, or get routed to a "custom proposal required" path with a scheduling link
when their situation falls outside standard pricing.

**MVP scope includes:**
- Shortcode-based front-end questionnaire (usable on any page, e.g. `/get-a-quote`)
- Admin dashboard (Individual + Business tabs) to manage pricing/questions without code
- Pricing/rules engine matching the client's Excel calculator logic exactly
- Instant proposal display with a prominent disclaimer
- "Custom proposal required" path with scheduling link, for out-of-range cases
- Confirmation email to prospect + notification email to internal team
- Submission logging to a custom DB table
- HubSpot integration (contact/deal creation via private app token — API, not Zapier)

**Explicitly OUT of MVP scope (phase 2):**
- E-signature / engagement letter signing
- Automated onboarding after signature
- Automated HubSpot follow-up sequences (client will configure these in HubSpot itself
  once contacts start flowing in)

---

## 2. Architecture

- Single custom plugin, no third-party form-builder dependency.
- Shortcode: `[tavola_quote_builder]`
- Custom top-level admin menu (not nested under Settings), with two tabs: **Individual**
  and **Business**.
- Custom DB tables (not `wp_posts`) for submissions — enables clean reporting later.
- Pricing rules stored in DB / options table, editable via admin UI (repeater fields for
  line items, table editor for the Business rate bands) — front-end form reads live from
  these settings, nothing hardcoded in the template.
- Disclaimer text is also editable from the dashboard.

---

## 3. Individual Return Pricing Logic

Structure: flat base fee + additive line items. All gated by a Yes/No toggle.

**Base:**
- W-2 wage income — always applies. Flat **$350**. (Includes joint filing, direct
  deposit, e-file.)

**Line items — three formula patterns found in the client's actual Excel calculator:**

| # | Pattern | Formula | Applies to |
|---|---|---|---|
| 1 | Qty × Fee, gated by Yes | `IF(Yes, Qty*Fee, 0)` | Most rows (multi-state, brokerage sales, rental property, K-1s, etc.) |
| 2 | Flat fee, Qty ignored | `IF(Yes, Fee, 0)` | Interest/Dividends ($25 flat), Childcare ($25 flat — see open question below) |
| 3 | Hardcoded flat, ignores Fee column | `IF(Yes, 100, 0)` | Retirement Distributions — Fee column displays $25 but formula charges $100 (see open question below) |

**Full line item list:**

| Item | Fee | Pattern | Notes |
|---|---|---|---|
| Lived/worked in multiple states | $150 | Qty × Fee | Per additional state |
| Interest/dividend statements (1099-INT/DIV) | $25 | Flat | Qty ignored regardless of # of forms |
| Brokerage sales (1099-B) | $25 | Qty × Fee | Per sale |
| Rental property | $200 | Qty × Fee | Per property |
| Self-employed / small business / single-member LLC | $200 | Qty × Fee (qty effectively 1) | |
| Farm income | $275 | Qty × Fee (qty effectively 1) | |
| K-1 received | $50 | Qty × Fee | Per K-1 |
| Foreign bank accounts / FBAR | $250+ | **CUSTOM QUOTE TRIGGER** | "Starts at $250, may run higher" — do not auto-price |
| Crypto bought/sold/traded | **CHANGED — see Section 9** | Yes/No + volume threshold | Client (2026-07-23): should be a simple Yes/No, no quantity. If trading volume is **under $100K** → fixed price (amount **TBD, need from client**). If **over $100K** → custom quote. Supersedes the old "always custom quote" rule. |
| College tuition (1098-T) | $25 | **Flat (changed from Qty×Fee)** | Client (2026-07-23): should be a simple Yes/No question, not a quantity field |
| Childcare / dependent care | $25 | Flat (per current formula) | Note says "per child" — formula does NOT multiply. **Open question — see Section 6.** |
| HSA (1099-SA / 5498-SA) | $25 | Qty × Fee (qty effectively 1) | |
| Sold a home (1099-S) | $150 | Qty × Fee (qty effectively 1) | |
| Retirement distributions (1099-R) | Displays $25, formula charges $100 | Hardcoded flat $100 | **Open question — see Section 6.** |
| Meetings | $250 | Qty × Fee | Internal-use item — **exclude from public-facing form** (staff-added only, per earlier discussion) |

**Grand total** = SUM of all applicable line totals.

---

## 4. Business Return Pricing Logic

Structure: Part A (base fee via rules) + Part B (additive extras, same pattern as
Individual). `Total = IFERROR(base + extras, "See note above")` — the IFERROR exists
in the original sheet to catch cases where base = "Custom" (text), which would
otherwise throw a math error.

### Part A — Base Fee Rules Engine

Three dropdowns drive this: **Entity Type**, **Total Assets**, **Annual Revenue**.

**Step 1 — Custom quote check (highest priority):**
If Total Assets = **$5M–$10M** or **Over $10M** → base = "Custom". Route to
custom-proposal path. Do not evaluate further rules.

**Step 2 — Schedule L threshold check:**
If Schedule L is NOT required, base = flat **$999**. Thresholds are entity-specific:

| Entity Type | Schedule L NOT required when |
|---|---|
| C-Corporation | Receipts under $250K **AND** assets under $250K |
| S-Corporation | Receipts under $250K **AND** assets under $250K |
| Partnership | Receipts under $250K **AND** assets do not exceed $1M* |

*Partnership must also meet other Form 1065 conditions to omit Schedules L, M-1, M-2
(per source sheet footnote — not further specified).

Important: this is a **combined** condition (assets AND revenue), not asset band alone.
A business with small assets but revenue over the threshold does NOT qualify for the
$999 flat fee — it falls through to Step 3.

**Step 3 — Asset-band + revenue-add-on lookup (only reached if Step 1 and Step 2 don't apply):**

Base price by asset band (from Rate Reference, `INDEX/MATCH`, column C&S-Corp vs
column Partnership):

| Asset Band | C-Corp / S-Corp | Partnership |
|---|---|---|
| Under $250K | $1,250 | $1,250 |
| $250K–$500K | $1,250 | $1,250 |
| $500K–$1M | $1,500 | $1,250 |
| $1M–$2M | $1,500 | $1,500 |
| $2M–$5M | $1,750 | $1,700 |
| $5M–$10M | Custom | Custom |
| Over $10M | Custom | Custom |

Plus revenue add-on:

| Revenue Band | Add-On |
|---|---|
| Under $250K | +$0 |
| $250K–$1M | +$0 |
| Over $1M | +$200 |

**Base fee = asset-band price + revenue add-on** (when Schedule L is required and
assets are under $5M).

### Part B — Extras (same Yes-gated Qty × Fee pattern as Individual)

| Item | Fee | Notes |
|---|---|---|
| Multiple partners/owners (extra K-1s) | $25 | Per K-1 beyond the first |
| Operates in more than one state | $250 | Per additional state |
| Fixed asset / depreciation schedule needed | $250 | New business, large acquisition, or no schedule from prior CPA |
| Foreign partner/owner | $350 | |
| Books don't match tax records | $250 | Note says "per hour charge" — **open question, treat as flat $250 for now, see Section 6** |
| More than 25 pieces of equipment/fixed assets | $250 | |
| Under IRS audit / audit support | $350/hr | **CONFIRMED: exclude from public form entirely** — client confirmed this is covered in ToS/Client Service Agreement, audit rep is out of scope for standard engagement |

**Grand total** = base fee (Part A) + sum of extras (Part B). If base = "Custom",
skip total calculation and route to custom-proposal path instead.

---

## 5. Rate Reference (data backbone — not a user-facing step)

This is config data only, feeds the Business rules engine. Needs to live in the admin
dashboard as an editable table (asset bands, entity types, revenue bands, and their
associated prices), not hardcoded, since James will need to update it over time.

Dropdown source lists (Entity, Asset Band, Revenue Band) also come from this sheet —
these become the `<select>` options in the front-end form.

---

## 6. Open Questions (assumptions made for now — confirm before final build)

These two remain unconfirmed. Building with the following ASSUMPTIONS, clearly flagged
as configurable in the admin dashboard so they're a one-click fix either way:

1. **Childcare line item** — Note says "per child," but current formula is flat
   (`IF(Yes, 25, 0)`, ignores Qty). **ASSUMPTION: building as flat $25, Qty field
   hidden/ignored for this row**, matching the actual formula rather than the note.
   Flag to client/James for final confirmation — easy to switch to Qty × Fee later
   since it's just a config toggle per line item.

2. **Retirement Distributions line item** — Fee column displays $25, but formula
   hardcodes $100. **ASSUMPTION: building with $100 as the actual charged amount**
   (trusting the formula over the displayed label, since formulas drive the real
   calculator behavior). Flag to client/James to confirm which number is correct.

Both of these are single-value settings in the admin dashboard, so correcting either
one post-launch takes seconds, no code change needed.

3. **Crypto fixed price (under $100K volume)** — client's 2026-07-23 feedback
   introduces a new rule (Yes/No + $100K volume threshold, see Section 9) but did
   not provide the actual dollar amount for the "under $100K" fixed price. Need
   this from the client before the pricing engine can be updated.

4. **"Multiple businesses" scenario** — client asked "what happens if they have
   multiple businesses to quote?" (Section 9). Not yet answered by the client —
   need to know whether this means: (a) repeat the Business questionnaire N times
   and sum/list separately, or (b) something else. Flagged as open in Section 9.

5. **Deployed-vs-documented drift** — the version the client tested (Section 9)
   has bugs (Get Quote / Reset Quote not working) that don't match what's
   described as built in PROGRESS_LOG.md as of Session 8. Needs reconciliation:
   confirm exactly which code is live on the site the client tested before
   continuing, since work may have happened outside this documented process
   (e.g. in a different AI tool/session) and diverged from this spec.

---

## 7. Confirmed Decisions (from client correspondence)

- Audit support line: **excluded from public form**, internal-only if needed later.
- Meetings line (Individual sheet): **excluded from public form**, internal-use only.
- Disclaimer text (client-approved, to display prominently on proposal screen):
  > "This quote is an estimate and is subject to change based on your specific facts
  > and circumstances. For example, if the number of properties, accounts, or services
  > involved is different from what was entered, or if additional work is needed once
  > we review your documents, the final price may be adjusted."
- Custom-quote triggers (both sheets) route to a message + scheduling link, no price
  shown, submission still logged and team still notified.
- HubSpot connection: direct API integration via private app access token (not
  Zapier), client will generate token on their end and share it securely.

---

## 8. Integration Requirements

- WordPress site access (already have)
- Email sending method (existing service or SMTP) for reliable delivery
- HubSpot private app access token (client to generate and share)
- Scheduling link: **CONFIRMED** — `https://scheduler.zoom.us/matt-schumacher/personal-tax-return-consultation`,
  set as the plugin's default and used on the custom-quote-required screen

---

## 9. UX/UI Redesign & Functional Feedback (Client + Developer — 2026-07-23)

This is a significant round of feedback covering layout, wording, logic, bugs, and
a new feature request (abandoned-lead follow-up). Documented here in full before
any code changes, per developer's request. Nothing in this section is built yet.

### 9.1 Developer's (Ibrahim's) own UX requirements
- Calculator should be **full width** (currently constrained to 640px max)
- Use the **host site's fonts and colors** (tavola.group brand — colors/font not
  yet supplied to developer as of this writing; CSS currently uses a placeholder
  navy/gold palette pending real brand assets)
- **Two-column layout**: left = step-by-step form, right = running summary/preview,
  updated in real time
- Live summary total will be **calculated client-side in JS**, mirroring the
  server's pricing engine logic. Chosen over calling the server on every checkbox
  change for responsiveness. **Risk, explicitly accepted**: the JS copy of the
  pricing logic must be kept in sync with `class-tqb-pricing-engine.php` by hand —
  if one is edited without the other, the live preview can silently drift from the
  real server-calculated price shown at the end. The final submitted price is
  always server-calculated regardless, so this only risks the *preview* being
  briefly wrong, never the actual quote — but should be flagged in code comments
  and re-checked any time pricing logic changes.
- **Reset button** to clear the entire form and start over
- **Final review/summary screen** showing all answers before the user submits
  (confirmed with developer: this means a review step right before final submit,
  not an intro screen before the questionnaire starts)
- After submission, replace the form with a button with **meaningful text**
  (e.g. "Get Another Quote") that **resets the form in place via JS**, no full
  page reload

### 9.2 Client feedback — Initial screen (quote type selection)
- Currently forces a single choice: Individual **or** Business. Client wants
  **both to be selectable**, each questionnaire completed separately, resulting
  in **one combined quote**.
- **Open question (unanswered by client):** what happens when someone has
  **multiple businesses** to quote? Repeat the Business questionnaire per
  business? Need clarification before this can be designed, let alone built.

### 9.3 Client feedback — "Your Info" step
- Remove anything below the live summary on this step — client felt it added
  clutter/confusion. (Note: this implies the live-summary panel should already be
  visible during the contact-info step, not just during the questions step —
  worth confirming with developer's two-column layout plan, since the summary
  panel would presumably be visible throughout the whole flow, not just Step 3.)
- General note: "interface feels somewhat clunky" — layout needs an overall
  cleanup pass, not just this one step.

### 9.4 Client feedback — Wording overhaul (large scope item)
- The original Excel matrix was written for **internal Tavola staff use**, not
  clients. Almost every question needs to be rewritten in **customer-friendly
  language** — this is a content rewrite across every line item label in both
  the Individual and Business questionnaires, not just a couple of items.
- This should happen **before or alongside** the help-text work in 9.6, since
  both involve rewriting the same line-item content.

### 9.5 Client feedback — Questionnaire logic changes
- **Crypto**: change from "any Yes = custom quote" to Yes/No + volume threshold:
  - Under $100K trading volume → **fixed price** (amount not yet provided by
    client — see Section 6, Open Question #3)
  - Over $100K trading volume → custom quote
  - This means crypto becomes a **two-part question** (Yes/No, then — if Yes —
    a volume threshold selection), not a single checkbox anymore. UI implication:
    this item now needs conditional sub-fields, similar to how the Business
    questionnaire already has conditional dropdowns.
- **College tuition**: change from Qty×Fee to a plain Yes/No (see Section 3 update)
- Client explicitly flagged that **there are likely other items with similar
  hidden conditional logic** that haven't been identified yet — this needs a
  full audit of every line item on both questionnaires with the client/James
  before the logic can be considered final. Not a one-time fix, an ongoing risk
  until that audit happens.

### 9.6 Client feedback — Help text / tooltips (client's top priority item)
- Add a short explanation under (or as a tooltip on) every question, covering:
  1. What we're actually asking for, in plain language
  2. Where the client can typically find that information (e.g. "look for a
     1099-DIV form" vs. just "interest/dividend statements")
- The original Excel matrix's **Notes column** (already stored per-item as the
  `notes` field in `wp_tqb_line_items`, and already rendered under each question
  in the current front-end) has a lot of this content already — but it's written
  in **internal shorthand** ("Office ref: Schedule A") rather than customer-facing
  explanation. This needs to be rewritten per item, not just displayed as-is.
- This ties directly into 9.4 (wording overhaul) — both are fundamentally a
  content-rewrite pass across every line item, best done together.

### 9.7 Client-reported bugs (client's live test)
- **"Get Quote" button does not work**
- **"Reset Quote" button does not work** — note: no Reset button exists yet in
  the documented/shipped build as of Session 8. This suggests either (a) the
  client tested a version that already had some of Ibrahim's 9.1 changes applied
  outside this documented process, or (b) client terminology doesn't map 1:1 to
  current button labels ("Get My Quote" being read as "Get Quote"). **Needs
  reconciliation before further front-end work** — see Section 6, Open Question #5.
- **"Back" button works** (confirms at least some JS event wiring is functioning)
- **Business questionnaire**: several labels/words are cut off (styling/overflow
  bug), some buttons don't function, client was unable to complete the Business
  flow at all. Root cause not yet diagnosed — needs to be tested directly on
  whatever the actual live/staging environment is once 9.7's version confusion
  is resolved.

### 9.8 Client feedback — Abandoned-lead capture & follow-up (new feature, not in original MVP scope)
Client wants to capture and follow up on **incomplete** submissions, not just
completed ones:
- Save the prospect's information **progressively as they go**, not only on
  final submit (implies saving a partial/draft record as soon as contact info
  is captured in Step 2, then updating it as they answer more questions)
- **Automatically email a reminder** if someone starts but doesn't finish
  (needs a time-based trigger — e.g. WP-Cron checking for stale in-progress
  submissions past some threshold, not yet defined)
- Optionally, a **follow-up offering to schedule a call** instead of finishing
  the form themselves
- This is a genuinely new feature area (partial-submission capture + scheduled
  reminder emails), not a fix to something already built. Needs its own design
  pass: new submission status field (in-progress / abandoned / completed), a
  cron job, reminder email templates, and a defined "how long is too long"
  threshold (needs client input).

### 9.9 HubSpot — client needs training, not more code
Client asked for a walkthrough of how the HubSpot integration works and what to
look for in HubSpot, so nothing gets missed. This is a **deliverable/service
item** (a call, or a short written guide), not a coding task — flagged here so
it doesn't get lost, but doesn't belong on the same task list as the code fixes
above.

### 9.10 Client's stated priority order (their words)
1. Clean up UI/formatting
2. Customer-facing wording (not internal jargon)
3. Explanatory text throughout the questionnaire
4. Fix functionality bugs on both Individual and Business flows
5. Improve lead capture / automated follow-up for incomplete submissions

(HubSpot training was mentioned separately, not numbered in their priority list.)

---

## 10. Changelog

### v0.4.1 (2026-07-29)
- Added View Details modal in admin submissions list
- Professional modal UI with sections: Contact, Quote, Notifications, Timeline, Form Responses
- All submission data displayed with proper formatting
- Responsive design for mobile devices

### v0.4.0 (2026-07-29)
- Initial clean release from main branch
- Individual and Business pricing configuration
- HubSpot integration
- Line items and rate bands management
- Quote submission tracking
