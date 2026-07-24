# Tavola Group — Quote Builder Plugin
## Progress Log (update at the end of every session, paste alongside PROJECT_SPEC.md into every new AI session)

---

## How to use this file
At the start of a new AI session: paste this file + PROJECT_SPEC.md + any relevant
existing code files. Say "continue from here." At the end of a session (or when
about to hit a usage limit): ask the AI to update this file before stopping, so the
next session starts clean.

---

## Current Status: Added editable labels and tooltips feature. Label and tooltip fields are now editable from backend. Tooltip icon shows on hover in frontend. Ready to merge and test.

---

## Session Log

### Session 16 — 2026-07-24 (editable labels + tooltips)
**Done:**
- Added `tooltip` column to `wp_tqb_line_items` table
- **Label field is now editable** in admin dashboard — client can change question text
- **Tooltip field added** for customer-facing help text (shown on hover)
- Sample tooltip text added for all Individual and Business line items
- Tooltip icon (ℹ️) appears next to questions that have tooltip text
- CSS styling for tooltip popup (appears on hover)

**Files modified:**
- `includes/class-tqb-activator.php` — DB schema + seed data with tooltips
- `includes/class-tqb-db.php` — `update_line_item()` now saves label + tooltip
- `includes/class-tqb-admin.php` — save handler for new fields
- `includes/class-tqb-public.php` — sends tooltip to frontend JS
- `admin/views/line-items-tab.php` — editable label + tooltip fields
- `public/js/tqb-public.js` — renders tooltip icon and popup
- `public/css/tqb-public.css` — tooltip styling

**Not done yet:**
- Wording rewrite (can now be done from backend by editing labels)
- Same remaining items from previous sessions

**How to verify:**
- Admin: Go to Quote Builder > Individual Pricing — labels and tooltips are now editable fields
- Frontend: Questions with tooltip text show ℹ️ icon; hover to see tooltip popup

---

### Session 15 — 2026-07-24 (rate limiting security fix)
**Done:**
- Added **rate limiting** to the AJAX submit endpoint to prevent abuse
- Configurable settings via constants in `class-tqb-public.php`:
  - `RATE_LIMIT_MAX = 5` — Maximum submissions allowed per IP
  - `RATE_LIMIT_WINDOW = HOUR_IN_SECONDS` — Time window (1 hour)
- `get_client_ip()` — Gets visitor IP, handles Cloudflare/proxy headers correctly
- `check_rate_limit()` — Checks if IP has exceeded the limit
- `increment_rate_limit()` — Records successful submissions using WordPress transients
- Rate-limited requests return **HTTP 429** with a user-friendly message: "Too many submissions. Please wait about X minutes before trying again."
- Updated `public/js/tqb-public.js` to handle HTTP 429 responses and display the error message to users
- JS syntax verified clean with Node

**Files modified:**
- `includes/class-tqb-public.php` — Added rate limiting logic
- `public/js/tqb-public.js` — Added 429 error handling

**Not done yet:**
- Add `uninstall.php` for proper cleanup when plugin is deleted
- Same list from previous sessions: wording rewrite, help text, crypto/tuition logic changes, dual quote-type selection, abandoned-lead capture

**How to verify:**
- After merging, test by making 5 submissions quickly — 6th should be blocked with a friendly error
- Test behind a proxy/VPN to verify IP detection works correctly

---

### Session 14 — 2026-07-23 (summary panel improvements)
**Done — based on developer's screenshot review of Session 13's layout:**
- Added a **"Business Details" block** at the top of the summary panel
  (Business type, Total assets, Annual revenue) so the three dropdown
  selections from Step 3 are visible in the summary too, not just on the
  form itself
- Added a **"Base Return Fee" line item** — previously the summary only
  showed the extras (K-1s, states, etc.) and jumped straight to the
  total, making the total look like it only came from the checked items.
  Now the calculated base fee (from the Schedule L / asset-band logic)
  shows as its own line, so the math is fully visible and self-explanatory
- Added **quantity display** on line items — e.g. "Has a foreign
  partner/owner (\u00d73)" instead of just showing the dollar amount with
  no indication of how many units it represents
- Refactored `calculateIndividualPreview()` to just delegate to
  `calculateIndividualPreviewForItems()` (they were identical duplicated
  logic) — reduces future risk of the two drifting apart from each other,
  on top of the already-flagged PHP/JS drift risk
- Verified: JS syntax clean, CSS brace-balanced, all PHP lints clean,
  15/15 server-side pricing tests still passing. The total-calculation
  line itself was untouched by this change (only display/labeling data
  was added to the returned line items), so no regression risk to the
  actual math — confirmed by inspection rather than needing to re-run the
  full fixture check.
- Repackaged `tavola-quote-builder.zip`

**Not done yet:** Same list as Session 13 — wording rewrite, help text,
questionnaire logic changes (crypto/tuition), dual quote-type selection,
abandoned-lead capture, HubSpot training. Nothing here changed that list,
this was a refinement of what Session 13 already built.

**How to verify:** Reinstall, hard refresh, walk the Business path again
— the summary panel should now show the 3 business-detail selections at
top, a "Base Return Fee" line before the extras, and quantities in
parentheses next to any item where qty > 1.

---

### Session 13 — 2026-07-23 (layout overhaul: full-width, two-column, live summary, review step, reset)
**Done — this closes out items 3-6 of the Section 9 priority list in one
coherent, verified step:**

- **Full width**: removed the 640px cap on the wizard
- **Two-column layout**: new `.tqb-layout` grid — left column is the
  step wizard, right column is a sticky live summary panel. Collapses to
  a single column under 860px (summary panel moves above the form on
  mobile rather than disappearing)
- **Live summary panel**: shows the selected return type, a running list
  of selected items with their dollar amounts, and a running estimated
  total — updates instantly as the user checks boxes, changes quantities,
  or picks business dropdowns. Per developer's decision, this is
  calculated **client-side in JS**, not via server calls
- **New Review step (Step 4)**: shows every answer — contact info,
  business details if applicable, and every selected item — before the
  user can submit. Matches client's explicit request. Renumbered the
  whole flow: 1 Return Type → 2 Your Info → 3 Details → 4 Review → 5 Your
  Quote (progress indicator updated to 5 steps)
- **Reset button**: "Start Over" link in the wizard header, visible on
  every step, clears all fields/selections and returns to Step 1
- **In-place "Get Another Quote"**: after seeing a result, a button
  resets the whole form via JS and returns to Step 1 — no page reload
- Updated `class-tqb-public.php` to localize actual pricing data (fee,
  pricing pattern, hardcoded value, band prices) to the front-end, since
  the live summary needs real numbers to calculate with, not just labels

**Caught a real bug while verifying — not just inspected by eye:**
Before packaging, ran the new JS pricing-mirror logic through Node
against the exact same fixtures used in `tests/test-pricing-engine.php`.
The Individual test case failed (`1150` instead of the correct `1075`).
Root cause: JavaScript's `||` operator treats `0` as falsy, so
`answer.qty || 1` silently converted an explicit quantity of **0** into
**1** — meaning a checked item with qty 0 was incorrectly billed as if
qty were 1. Fixed by replacing the fallback with an explicit type check
(`typeof answer.qty === 'number' ? answer.qty : 1`). Re-ran the same
fixture test after the fix — all 3 cases now pass, matching the PHP
engine exactly. This is a good concrete example of the exact "JS/PHP
sync drift" risk flagged in PROJECT_SPEC.md Section 9.1 — worth
remembering to spot-check the JS mirror against real numbers any time
either the pricing engine or its JS copy changes, not just visually
review the code.

**Verification performed:**
- PHP: all files lint clean
- JS: both `tqb-public.js` and `tqb-admin.js` syntax-checked with Node
- CSS: brace-balanced (91 open / 91 close)
- Pricing engine: still 15/15 tests passing (server-side logic untouched)
- JS pricing mirror: separately verified against the same 3 known-correct
  fixtures via a standalone Node script (not part of the packaged
  plugin — a one-off check, see above)
- Repackaged `tavola-quote-builder.zip`

**Not done yet:**
- Wording rewrite + help text/tooltips (still needs real brand
  colors/fonts, and the crypto fixed-price dollar amount, from earlier
  open questions)
- Questionnaire logic changes (crypto Yes/No + threshold, tuition Yes/No)
- Dual quote-type selection + multiple businesses (blocked on client)
- Abandoned-lead capture + reminders
- HubSpot training for the client

**How to verify:** Reinstall the zip, hard-refresh (cache-busting is now
automatic per Session 12's fix, but a hard refresh the first time after
reinstalling is still good practice). Walk through the full flow on both
Individual and Business paths: confirm the form is full-width, the right
column updates live as you check boxes, the Review step shows everything
correctly, "Start Over" resets cleanly from any step, and after
submitting, "Get Another Quote" resets without a page reload.

---

### Session 12 — 2026-07-23 (asset cache-busting fix)
**Done:**
- Diagnosed why Session 11's CSS fix (quantity input width) didn't appear
  to work on the live site even after reinstalling: `TQB_VERSION` (used as
  the cache-busting `?ver=` query string on every enqueued CSS/JS file)
  has been hardcoded to `0.1.0` since Phase 1 and **never incremented
  across 11 sessions of changes**. Since the file URL never changes,
  browsers (and any caching plugin/CDN in front of the site) had no
  signal to fetch a fresh copy — very likely still serving the original
  CSS from Session 4, styling fixes included.
- Added `tqb_asset_version()` helper in the main plugin file — generates
  a version string from each file's actual last-modified time
  (`filemtime()`) instead of a manually-maintained constant. This means
  **every future save automatically busts cache correctly**, with no risk
  of this happening again.
- Updated all 3 enqueue calls (`class-tqb-public.php` ×2,
  `class-tqb-admin.php` ×1) to use this helper instead of `TQB_VERSION`
- Confirmed via direct grep that the plugin's own CSS has **no fixed
  `height` rules** on any content-bearing element (only small icon-sized
  heights on the checkbox and loading spinner) — so the "cutoff" the
  developer described is not coming from this plugin's CSS. Most likely
  either the host page's Elementor column/section has its own fixed
  height setting, or the shared screenshot simply didn't capture the
  full page. Needs developer to check the Elementor container's Advanced
  tab (Min Height setting) or share a full-page screenshot to confirm.
- All linted clean, 15/15 pricing tests still passing
- Repackaged `tavola-quote-builder.zip`

**Not done yet:** Full-width layout is still the placeholder 640px —
that's expected, it's the next planned step (Section 9), not a bug.

**How to verify:** Reinstall this zip. Because this fix changes how
assets are versioned, **also do a hard refresh (Ctrl+Shift+R / Cmd+Shift+R)**
or clear any caching plugin on the site, since old cached files may
persist even after the plugin itself updates. Then re-check the Business
flow quantity boxes from Session 11 — they should finally show the fix.

---

### Session 11 — 2026-07-23 (CSS theme-bleed fix)
**Done:**
- Diagnosed the quantity-input bug from the developer's screenshot: on the
  live site, the small 64px quantity boxes next to checkboxes were
  rendering full-width instead. Root cause: the host theme/Elementor
  likely applies broad global rules like `input { width: 100% }`, which
  were winning against our styling since our original CSS had no
  defenses against that (no `!important`, no scoped specificity)
- Added a defensive reset block scoped to `.tqb-wizard` for all form
  elements (inputs, selects, buttons, textareas), then re-applied every
  intentional width/sizing rule with `!important`: the quantity input
  (now locked to 64px via width + min-width + max-width + flex-basis),
  the text/email/phone/select fields (100%), the checkboxes (18px), and
  all buttons
- **Caught and fixed a specificity bug in my own fix while doing this**:
  an early version of the reset used `.tqb-wizard button { width: auto
  !important }`, which — because it combines a class with an element
  selector — is actually MORE specific than a single class like
  `.tqb-type-card`, and would have silently broken the Step 1 type-choice
  buttons' intended full-width layout. Removed the broad rule; each
  button class (`.tqb-btn`, `.tqb-type-card`) now protects its own width
  individually instead, which is safe regardless of specificity math
- Verified CSS brace-balance (57/57, no syntax errors) since there's no
  formal CSS linter available in this sandbox
- Re-ran full PHP lint + pricing-engine test suite (unaffected by this
  change, confirmed still 15/15 passing) as a general regression check
- Repackaged `tavola-quote-builder.zip`

**Not done yet:** This fixes the specific bug confirmed via screenshot.
The client also reported "some buttons don't appear to function
correctly" on Business — that could be this same styling issue (a
stretched/misplaced button might look broken or be hard to click even if
its JS handler is fine), or could be a separate, real JS bug. **Needs a
fresh test on the live site with this fix installed** to know if
anything else is still actually broken versus just a visual symptom of
the same root cause.

**How to verify:** Reinstall this zip, go through the Business flow
again, confirm the quantity boxes are now small and properly positioned
next to their checkboxes (matching the Individual flow's appearance),
and try every button on that path.

---

### Session 10 — 2026-07-23 (version-drift resolved)
**Done:**
- Confirmed with developer: the "Reset Quote" button the client saw was
  added by a different AI tool, working from the same PROJECT_SPEC.md /
  PROGRESS_LOG.md docs, but that attempt broke other functionality
- **Decision: discard those changes entirely.** Reverted to the last
  known-good state from Session 8 (re-verified clean: all PHP lints pass,
  JS syntax checks pass, 15/15 pricing tests pass)
- Developer will reinstall this exact package on the live/staging site,
  after removing the other AI's version and its database tables, for a
  clean slate
- **Going forward, all further changes to this plugin should happen only
  in this documented, tested, packaged, logged process** — not
  interchangeably across different AI tools mid-project, since that's
  what caused this exact problem. If a different tool is used for
  something in the future, its output should be brought back here and
  verified/reconciled before being treated as the source of truth.

**Not done yet:** Nothing from PROJECT_SPEC.md Section 9 has been built
yet — this session was purely about resolving the drift issue so the
next session starts from a clean, known state.

---

### Session 9 — 2026-07-23 (feedback intake — documentation only, no code)
**Done:**
- Logged a full round of feedback in **PROJECT_SPEC.md Section 9**:
  - Developer's (Ibrahim's) own UX requirements: full-width layout, site
    fonts/colors, two-column layout (form left, live running summary
    right, client-side calculated), reset button, review screen before
    final submit, in-place "Get Another Quote" reset after submission
  - Client feedback across 8 areas: dual quote-type selection (Individual
    + Business together, combined quote), simplifying the "Your Info"
    step, a full wording rewrite (internal → customer-facing language),
    questionnaire logic changes (crypto becomes Yes/No + $100K volume
    threshold instead of always-custom-quote; tuition becomes Yes/No
    instead of Qty), help text/tooltips sourced from the matrix's notes
    column (client's stated top priority), reported bugs (Get Quote and
    Reset Quote not working, Business flow largely broken — cut-off
    labels, non-functional buttons), and a new feature request:
    abandoned-quote capture + automated reminder emails
- Added 3 new open questions to PROJECT_SPEC.md Section 6: the actual
  dollar amount for the new crypto under-$100K fixed price (not yet
  provided by client), how "multiple businesses" should be handled
  (client asked, didn't answer), and the version-drift issue below
- Updated Section 3's crypto/tuition rows to reflect the new logic

**Important flag — please read before the next coding session:**
The client reported "Get Quote" and "Reset Quote" as broken, but **no
Reset button has been built yet** in what's documented here through
Session 8. This means one of two things: (a) the client tested a version
that already had some UI changes made outside this documented process
(e.g. in a different AI session/tool, given the earlier conversation
about hitting free-tier limits and working across multiple tools), or
(b) there's a terminology mismatch. **Before starting the redesign work,
worth confirming exactly what code is actually live on the site the
client tested**, so we're not debugging phantom bugs in one version while
building new features on top of a different one. If a different AI
session already made edits, those changes aren't reflected in this
Progress Log and should be pulled in / reconciled first.

**Not done yet:** Everything in PROJECT_SPEC.md Section 9 — this was a
pure documentation/planning session per explicit request, no code
touched. Next session should tackle these in small, individually
tested/packaged/logged steps (matching how Sessions 1–8 worked), not as
one giant rewrite — likely order given the client's stated priorities:
1. Resolve the version-drift question above
2. Fix Business flow bugs (client couldn't complete it at all — highest
   severity, blocks their ability to even evaluate the rest)
3. Layout/UX overhaul (full-width, two-column, live summary, reset,
   review screen) — this is Ibrahim's + client's shared ask
4. Wording + help-text rewrite (large content task, can happen in
   parallel with #3 once real brand colors/fonts are supplied)
5. Questionnaire logic changes (crypto threshold, tuition, and the
   broader "audit for other conditional logic" the client flagged)
6. Dual quote-type selection + multiple-businesses handling (blocked on
   client clarifying the multiple-businesses question)
7. Abandoned-lead capture + reminder emails (new feature, own design pass)
8. HubSpot training for the client (not code — a walkthrough/guide)

---

### Session 8 — 2026-07-22 (HubSpot dynamic pipeline/stage dropdowns)
**Done:**
- `TQB_Hubspot::get_pipelines()` — read-only call to HubSpot's
  `/crm/v3/pipelines/deals` endpoint, returns every pipeline + its stages
- New AJAX endpoint (`wp_ajax_tqb_fetch_hubspot_pipelines`) in
  `class-tqb-admin.php`, admin-only, nonce-protected
- `admin/js/tqb-admin.js` — "Refresh from HubSpot" button populates a real
  Pipeline dropdown, selecting a pipeline populates two Stage dropdowns
  (Instant Quote / Custom Quote Requested) with that pipeline's actual
  stages. Selections write into hidden fields that get saved normally.
  Auto-loads on page open if a pipeline was already saved, so labels show
  correctly instead of a bare ID.
- Deals now route to different stages depending on `is_custom_quote` —
  `class-tqb-hubspot.php`'s `create_deal()` picks `tqb_hubspot_stage_new`
  or `tqb_hubspot_stage_custom` accordingly (legacy single-stage option
  kept as a fallback, doesn't break older configs)
- All linted clean, JS syntax-checked, 15/15 pricing tests still passing
- Repackaged `tavola-quote-builder.zip`

**How to verify:** Save a Service Key in General Settings first, then
click "Refresh from HubSpot" — the client's real pipelines (ERC, Advanced
Tax Planning, Tavola Creative, etc., from the earlier screenshot) should
appear in the dropdown, including whatever new pipeline gets created for
this project.

---

### Session 7 — 2026-07-22 (Phase 7: HubSpot integration)
**Done:**
- `includes/class-tqb-hubspot.php` — syncs every submission to HubSpot:
  1. Searches for an existing contact by email (`/crm/v3/objects/contacts/search`)
  2. Updates it if found, otherwise creates a new one
     (`/crm/v3/objects/contacts`), mapping name/email/phone (full name is
     split into first/last for HubSpot's separate properties)
  3. Creates a deal (`/crm/v3/objects/deals`) associated with that contact
     in the same request, using HubSpot's standard "deal to contact"
     association type. Deal name reflects quote type + contact name;
     amount is set for instant quotes, omitted (with a note in the deal
     name) for custom-quote submissions since there's no number yet
  4. Uses **Service Keys** as the auth method (Bearer token) — client
     confirmed HubSpot is steering new integrations this way instead of
     legacy private apps, functionally identical for our purposes
  5. Uses WordPress's `wp_remote_post`/`wp_remote_request` (not raw curl),
     matching WP plugin conventions
- Added `hubspot_deal_id` column to `wp_tqb_submissions` (contact ID
  column already existed from Phase 1) and a `TQB_DB::mark_hubspot_synced()`
  method to record both IDs after a successful sync
- Added 3 new fields to the **General Settings** tab: HubSpot Service Key
  (password-masked input), optional Pipeline ID, optional Deal Stage ID
  (both blank by default — HubSpot uses the account's default
  pipeline/stage if left empty)
- Wired into `TQB_Public::handle_submit()` — fires right after the email
  step, same pattern as Phase 6
- **Designed to fail gracefully at every layer**: if the Service Key is
  blank, sync is silently skipped (submissions still save/email
  normally). If the contact sync fails, the deal step doesn't run, but
  logs the reason. If the deal step fails but the contact succeeded, the
  contact ID is still recorded rather than losing that progress. Nothing
  here can block the prospect from seeing their result on-screen — Sync
  problems only show up in `error_log`, not to the person filling out
  the form.
- All files linted clean, full pricing-engine test suite re-run, still
  15/15 passing (this phase doesn't touch pricing logic, but re-checked
  as standard practice)
- Repackaged `tavola-quote-builder.zip` — 25 files total now

**Not yet tested against a real HubSpot account** — this was built
against HubSpot's documented API shape, but hasn't been exercised with
the client's actual Service Key yet. First real submission after pasting
the key in is the real test.

**Not done yet (all optional, outside original MVP scope):**
- No "Submissions" list screen in wp-admin (flagged back in Session 5)
- No retry mechanism if a HubSpot sync fails (would need to re-submit,
  or a manual "resync" button — not built, since original scope didn't
  call for it)

**How to verify Phase 7 works:**
1. Paste the client's HubSpot Service Key into Quote Builder → General
   Settings → HubSpot Service Key, save
2. Submit a test quote through the front-end form
3. In HubSpot, search Contacts for the test email — should find/see a
   contact with the name and phone filled in
4. Check that contact's associated Deals — should see a new deal with
   a name like "Individual Tax Return Quote — [Name]" and the quote
   amount (or "(Custom Quote Needed)" in the name, for custom-quote cases)
5. In the WordPress database, confirm `hubspot_synced = 1` and both
   `hubspot_contact_id` / `hubspot_deal_id` are populated on that
   submission row
6. If anything doesn't show up in HubSpot, check the server's PHP error
   log for `TQB_Hubspot:` entries — they'll say exactly what HubSpot's
   API rejected and why (bad scope, invalid pipeline ID, etc.)

---

### Session 6 — 2026-07-22 (scheduling link confirmed)
**Done:**
- Client provided the real scheduling link:
  `https://scheduler.zoom.us/matt-schumacher/personal-tax-return-consultation`
- Set as the seeded default in `includes/class-tqb-activator.php`, so a
  fresh plugin install now comes pre-configured with it
- Still fully editable anytime from Quote Builder → General Settings if
  it ever needs to change
- **Important**: `add_option()` only sets a value if it doesn't already
  exist — if the plugin is already active on a staging site from an
  earlier phase (when this was blank), the new default won't
  automatically overwrite it. In that case, paste the link manually into
  General Settings once, or deactivate/reactivate the plugin on a fresh
  database.
- Repackaged `tavola-quote-builder.zip`

**Not done yet:** HubSpot integration (Phase 7) — still the only
remaining piece, blocked on the private app token.

---

### Session 5 — 2026-07-21 (Phase 6: email handling)
**Done:**
- `includes/class-tqb-email.php` — sends both emails from the client's
  original scope via `wp_mail()`:
  1. **Confirmation to the prospect** — short, reassuring, tells them
     someone will follow up. Wording differs slightly for the custom-quote
     case vs. a normal instant quote.
  2. **Notification to the team** — sent to the address set in General
     Settings (defaults to the site admin email), includes contact info,
     the calculated total (or the custom-quote flag + reason), and a
     plain-English list of every answer submitted, so no one has to log
     into WordPress to see what came in.
- Added `TQB_DB::get_submission()`, `mark_confirmation_sent()`,
  `mark_team_notified()` — used to fetch submission data for the emails
  and record what actually got sent (these flags already existed as
  columns on `wp_tqb_submissions` since Phase 1, just weren't being used
  yet)
- Wired into `TQB_Public::handle_submit()` — emails fire automatically
  right after a submission is saved, no separate step needed
- Mail failures are logged (`error_log`) rather than thrown, so a delivery
  problem never breaks the prospect's on-screen result — the calculation
  and database save already succeeded by that point regardless of whether
  the email goes through
- All files linted clean, full pricing-engine test suite re-run, still
  15/15 passing
- Repackaged `tavola-quote-builder.zip`

**Important limitation to know about:**
- This uses core WordPress `wp_mail()`, which on many hosts sends via
  PHP's default `mail()` function — this is often unreliable and can land
  in spam. Per PROJECT_SPEC.md Section 8, we still need to know what SMTP
  service or plugin (if any) is already configured on tavola.group, so
  deliverability is solid. If nothing is configured, recommend a proper
  SMTP plugin (e.g. WP Mail SMTP) or a transactional email service before
  go-live — this phase makes emails *send*, but doesn't guarantee they
  reliably *arrive* without that piece confirmed.

**Not done yet:**
- HubSpot integration (Phase 7) — blocked until the client provides a
  private app access token, per the earlier conversation.
- No admin screen to view past submissions yet (data is in the DB and
  emailed out, but there's no "Submissions" list in wp-admin). Wasn't part
  of the original MVP scope, but worth flagging as a nice-to-have if there
  is time.

**How to verify Phase 6 works:**
1. Make sure the site can send mail at all (test with any WP mail
   plugin's test-send feature first, if unsure)
2. Submit a test quote through the front-end form
3. Check the email inbox used as `contact_email` for the confirmation
4. Check the team notification address (Quote Builder → General
   Settings) for the internal notification
5. In the database, confirm `confirmation_email_sent` and
   `team_notified` are both `1` on that submission row

---

### Session 4 — 2026-07-20 (Phase 4: front-end shortcode + multi-step form)
**Done:**
- `includes/class-tqb-public.php` — registers `[tavola_quote_builder]`
  shortcode, enqueues CSS/JS only on pages that use it, localizes pricing
  data (line items, rate bands, nonce) to JS, and handles the AJAX
  submission (`wp_ajax_tqb_submit_quote` / `..._nopriv_...`) which calls
  `TQB_Quote_Handler` and returns JSON
- `public/views/form-template.php` — the 4-step wizard shell (Return Type
  → Your Info → Details → Your Quote). Steps 1–2 are server-rendered;
  Step 3's question list is built client-side since it depends on which
  type was chosen
- `public/css/tqb-public.css` — full visual design: navy/gold financial-
  advisory palette (deliberately not the generic cream+terracotta AI-tell
  look), numbered step indicator (justified since it's a real sequential
  flow), serif treatment on the final quote number as the one "moment" in
  the design, inherits host theme's font-family elsewhere so it sits
  naturally inside the existing Elementor site. Respects
  `prefers-reduced-motion`, visible focus states throughout
- `public/js/tqb-public.js` — vanilla JS (no framework, matches the
  "no third-party form builder" architecture decision): step navigation,
  dynamic question-row rendering from localized data, business
  entity-type → asset-band dropdown cascading, contact field validation,
  AJAX submit, and result rendering (instant price vs. custom-quote
  message + scheduling link)
- Added a **General Settings tab** to the admin dashboard (not originally
  in the Phase 3 plan, pulled forward because Step 4 needed somewhere to
  read the disclaimer text and scheduling link from): disclaimer text,
  scheduling link, team notification email — all stored as WP options,
  seeded with defaults on activation (disclaimer defaults to the
  client-approved wording from PROJECT_SPEC.md Section 7)
- W-2 wages (Individual) is auto-selected and its checkbox disabled on
  the form, since it's mandatory per the pricing sheet, not an optional
  question
- All PHP linted clean, JS syntax-checked with Node, full pricing-engine
  test suite re-run and still 15/15 passing
- Repackaged `tavola-quote-builder.zip` (24 files total now)

**Not done yet:**
- Emails are NOT sent yet — a submission is calculated and saved to the
  database, but no confirmation email to the prospect or notification
  email to the team goes out. That's Phase 6.
- No HubSpot sync yet (Phase 7).
- No visual screenshot was possible in this sandbox (no headless browser
  available) — the CSS was hand-reviewed carefully, but genuinely seeing
  it render on a real page is the first thing to check once installed.
- Scheduling link is still blank (client hasn't provided one yet) — the
  custom-quote result screen simply omits the "Schedule a Call" button
  when it's empty, rather than showing a broken link.

**How to verify Phase 4 works:**
1. Install/reactivate the plugin on a staging site
2. Create a page (or use an existing one), add the shortcode
   `[tavola_quote_builder]` via a Shortcode block or Elementor's
   Shortcode widget
3. Walk through: pick Individual or Business → fill contact info → answer
   the questions → submit → confirm a price appears (or the custom-quote
   message, if you select crypto/foreign accounts/large assets)
4. Check the `wp_tqb_submissions` table — a new row should appear with
   the submission you just made
5. Optional: go to Quote Builder → General Settings and paste in a real
   Calendly link (even a placeholder one) to see the "Schedule a Call"
   button appear on the custom-quote result

---

### Session 3 — 2026-07-17 (Phase 3: admin dashboard)
**Done:**
- `includes/class-tqb-admin.php` — registers a top-level "Quote Builder" menu
  in the WP admin sidebar with two tabs (Individual / Business), handles
  both save actions via `admin_post` with nonce + capability checks
- `admin/views/line-items-tab.php` — reusable table UI for editing any set
  of line items (fee, pricing pattern, hardcoded value, active toggle).
  Used by both the Individual tab and the Business "extras" section, so
  there's one editing UI, not two
- `admin/views/business-tab.php` — Rate Reference grid (editable asset-band
  prices for C/S-Corp vs Partnership side by side, editable revenue
  add-ons), followed by the Business extras (reuses line-items-tab.php),
  followed by a read-only reference table explaining the Schedule L logic
  (flagged as NOT editable from this screen — that logic lives in code,
  changing it needs a developer, this is intentional so a dashboard edit
  can't accidentally break the custom-quote routing)
- Wired `TQB_Admin` into the bootstrap, loaded only when `is_admin()` is
  true, so front-end page loads aren't affected
- All files linted clean, full pricing-engine test suite re-run and still
  15/15 passing (confirms nothing broke)
- Repackaged `tavola-quote-builder.zip`

**Design decisions worth knowing about:**
- Band *ranges* (e.g. what counts as "$250K-$500K") and the `is_custom`
  flag on the $5M+ bands are NOT editable from this dashboard — only
  prices are. This is deliberate: those structural values are wired into
  the pricing engine's logic, and letting a non-technical edit break the
  custom-quote routing would be a bigger risk than the convenience is
  worth for MVP. If James needs a band range changed, that's a
  developer-assisted change.
- Custom-quote-trigger items (crypto, foreign accounts) show a locked
  indicator in the line-items table rather than an editable toggle — same
  reasoning.

**Not done yet:**
- No front-end shortcode/form yet — there is still nothing a website
  visitor can interact with. This dashboard only affects data that
  Phase 4's form will eventually read.
- No email handling, no HubSpot integration.

**How to verify Phase 3 works:**
1. Install/activate the plugin on a staging WP site (or reactivate if
   already installed, to pick up the new files)
2. Go to WP Admin → Quote Builder (sidebar, calculator icon)
3. Individual tab: should show all 15 individual line items with editable
   fee/pattern/active fields
4. Business tab: should show the asset-band grid (7 bands × 2 entity
   columns), revenue add-ons, then the 6 business extras below
5. Try changing a fee, save, confirm it persists after reload (check the
   DB directly, or just reload the page and see the new value)

---

### Session 2 — 2026-07-17 (Phase 2: pricing/rules engine)
**Done:**
- `includes/class-tqb-pricing-engine.php` — the core calculation logic,
  deliberately written with ZERO WordPress dependencies (no `$wpdb`, no
  `get_option`, etc.) so it's independently testable. Implements:
  - `calculate_individual()` — the 3 formula patterns (qty×fee, flat,
    hardcoded), plus custom-quote-trigger short-circuiting for crypto/FBAR
  - `calculate_business()` — full 3-step priority logic: (1) asset band
    = Custom → route to custom quote, (2) Schedule L threshold check
    (entity-specific: $250K for C/S-Corp, $1M for Partnership) → flat
    $999, (3) otherwise asset-band price + revenue add-on, then adds
    Part B extras on top
- `includes/class-tqb-quote-handler.php` — WordPress-facing bridge: pulls
  config from `TQB_DB`, feeds it to the engine, saves the result as a
  submission via `TQB_DB::insert_submission()`
- `tests/test-pricing-engine.php` — standalone PHP test suite (no
  WordPress needed, run with `php tests/test-pricing-engine.php`).
  **15/15 tests passing**, including:
  - The client's own real example numbers: Individual → **$1,075** ✅,
    Business (S-Corp, small) → **$1,074** ✅
  - Crypto/foreign-account custom-quote triggering
  - Assets Over $10M custom-quote triggering
  - The Schedule L entity-specific threshold edge case (confirmed a
    C-Corp with $250K-$500K assets correctly does NOT get the $999 flat
    fee, while a Partnership with the same asset range DOES, since its
    threshold is $1M not $250K — this is the trickiest piece of logic in
    the whole spec, now locked in and verified)
  - Revenue add-on calculation (Over $1M → +$200)
- Wired both new files into the main plugin bootstrap
- All PHP files linted clean (`php -l`), full test suite passes
- Repackaged `tavola-quote-builder.zip`

**Not done yet:**
- No admin UI to edit line items/rate bands yet (still DB-only)
- No front-end form — the engine has no way to receive real user input yet
- No email handling, no HubSpot integration

**How to verify Phase 2 works:**
Run `php tests/test-pricing-engine.php` from the plugin root — no
WordPress or database needed for this test, it validates the pure logic
in isolation. All 15 assertions should pass.

---

### Session 1 — 2026-07-17 (Phase 1: plugin skeleton + DB schema)
**Done:**
- Created plugin folder structure: `tavola-quote-builder/` with `includes/`,
  `admin/`, `public/` subfolders (admin/public are empty, reserved for
  Phase 3 and Phase 4)
- `tavola-quote-builder.php` — main bootstrap file, plugin header, constants,
  activation/deactivation hook registration
- `includes/class-tqb-activator.php` — creates 3 custom tables via `dbDelta()`:
  - `wp_tqb_submissions` — every form submission (contact info, JSON answers,
    calculated total, custom-quote flag/reason, HubSpot sync status, email
    status flags)
  - `wp_tqb_line_items` — the editable checklist items for both Individual
    and Business (Part B), including a `pricing_pattern` column that encodes
    the 3 formula patterns found in the client's real Excel calculator
    (`qty_times_fee`, `flat`, `hardcoded`)
  - `wp_tqb_rate_bands` — the Business asset-band price grid + revenue
    add-on table (mirrors the Rate Reference sheet)
- Seed data included in the activator: all Individual line items, all
  Business Part B extras, and the full asset-band/revenue-addon grid,
  populated with the exact values from the client's actual spreadsheet
- `includes/class-tqb-deactivator.php` — intentionally does nothing
  destructive (no table drops on deactivate, to prevent accidental data loss)
- `includes/class-tqb-db.php` — central DB access helper (get line items,
  get rate bands, insert submission) so later phases don't scatter raw
  `$wpdb` calls across the codebase
- Packaged as `tavola-quote-builder.zip`, ready to install and activate on
  a test/staging WordPress site

**Not done yet:**
- No pricing/rules engine yet — Schedule L conditional logic, asset-band
  lookup, and custom-quote trigger evaluation (Section 3 & 4 of
  PROJECT_SPEC.md) are NOT implemented as code yet, only as seeded DB data.
  Phase 2 builds the actual calculation logic that reads this data.
- No admin UI — line items and rate bands currently only editable via
  direct DB access, not a dashboard screen
- No front-end form, no email handling, no HubSpot integration

**How to verify Phase 1 works:**
1. Install `tavola-quote-builder.zip` on a staging WP site, activate it
2. Check the database for 3 new tables (prefix + `tqb_submissions`,
   `tqb_line_items`, `tqb_rate_bands`)
3. `wp_tqb_line_items` should have 15 individual rows + 7 business rows
4. `wp_tqb_rate_bands` should have 14 asset_band rows (7 bands × 2 entity
   groups) + 3 revenue_addon rows

---

### Session 0 — 2026-07-17 (planning, no code yet)
**Done:**
- Finalized project scope with client (MVP definition agreed)
- Received and analyzed Individual and Business pricing sheets, including real
  formula behavior (not just static values)
- Received Rate Reference sheet (Schedule L thresholds, asset-band table, revenue
  add-on table, dropdown source lists)
- Confirmed with client: audit line excluded from public form, disclaimer wording
  approved, HubSpot integration to be direct API (not Zapier)
- Wrote PROJECT_SPEC.md covering full pricing logic for both Individual and Business
- Identified 2 open questions (childcare Qty handling, retirement distribution
  $25 vs $100) — proceeding with assumptions documented in spec, flagged for
  client confirmation

**Not done yet:**
- No code written
- Scheduling link (Calendly/HubSpot Meetings/etc.) not yet received from client
- HubSpot private app token not yet received from client
- Updated/corrected pricing sheet (client mentioned corrections coming) not yet
  received — current spec is based on latest version shared as of this log entry

---

## File-by-File Build Status

*(Update this table as files get created — this is the fastest way to see build
state at a glance in a new session)*

| File | Status | Notes |
|---|---|---|
| Main plugin file (bootstrap) | ✅ Done | `tavola-quote-builder.php` |
| DB schema / submission table | ✅ Done | `includes/class-tqb-activator.php` |
| DB access helper | ✅ Done | `includes/class-tqb-db.php` |
| Deactivation handler | ✅ Done | `includes/class-tqb-deactivator.php` |
| Pricing/rules engine class | ✅ Done | `includes/class-tqb-pricing-engine.php` — 15/15 tests passing |
| WordPress quote handler (bridge) | ✅ Done | `includes/class-tqb-quote-handler.php` |
| Standalone test suite | ✅ Done | `tests/test-pricing-engine.php` |
| Admin dashboard controller | ✅ Done | `includes/class-tqb-admin.php` |
| Admin — Individual tab | ✅ Done | `admin/views/line-items-tab.php` |
| Admin — Business tab (rate table editor) | ✅ Done | `admin/views/business-tab.php` |
| Admin — General Settings tab | ✅ Done | `admin/views/general-tab.php` |
| Front-end controller (shortcode + AJAX) | ✅ Done | `includes/class-tqb-public.php` |
| Front-end form template | ✅ Done | `public/views/form-template.php` |
| Front-end styles | ✅ Done | `public/css/tqb-public.css` |
| Front-end JS (wizard logic) | ✅ Done | `public/js/tqb-public.js` |
| Email handler (confirmation + team notification) | ✅ Done | `includes/class-tqb-email.php` — needs SMTP confirmed for reliable delivery, see Session 5 notes |
| HubSpot integration | ✅ Done | `includes/class-tqb-hubspot.php` — contact + deal sync via Service Key, not yet tested against a real HubSpot account |
| Scheduling link | ✅ Confirmed | `https://scheduler.zoom.us/matt-schumacher/personal-tax-return-consultation` — seeded as default |
| Submissions list admin screen | Not built | Not in original MVP scope — nice-to-have, data already exists in DB |

---

## Next Steps (in priority order)
1. Build plugin skeleton + DB schema
2. Build pricing/rules engine (Individual first, then Business — Business is more
   complex due to the Schedule L conditional logic)
3. Build admin dashboard for both, wired to the rules engine
4. Build front-end shortcode/form, reading live from admin settings
5. Build proposal + custom-quote-required templates
6. Build email handling
7. HubSpot integration (once token received)
8. End-to-end testing against known values from the client's actual Excel
   calculator (use their real examples as test cases — e.g. Individual example
   totaling $1,075, Business example totaling $1,074)

---

## Known Blockers
- Waiting on: corrected/final pricing sheet from client
- Waiting on: scheduling link for custom-quote path
- Waiting on: HubSpot private app access token
- Waiting on: client confirmation on childcare Qty handling and retirement
  distribution fee amount (see PROJECT_SPEC.md Section 6)

---

## Open Questions / Bugs Discovered During Build
*(Add entries here as they come up while coding — keeps a running list separate
from the pre-build spec questions above)*

- (none yet — build not started)
