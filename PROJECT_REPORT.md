# Tavola Quote Builder - Detailed Project Report

## Project Overview

**Plugin Name:** Tavola Quote Builder (tavola-quote-builder)  
**Purpose:** Self-service tax quote questionnaire for tavola.group  
**Type:** WordPress Plugin  
**Shortcode:** `[tavola_quote_builder]`

This plugin provides a multi-step questionnaire that allows prospects (Individual or Business tax clients) to get instant price quotes, or be routed to a custom proposal path when their situation falls outside standard pricing.

---

## File Structure

```
tavola-quote-builder/
├── tavola-quote-builder.php          # Main plugin file
├── admin/
│   ├── class-tqb-admin.php          # Admin dashboard logic
│   └── views/
│       ├── dashboard.php             # Admin settings page
│       ├── tab-individual.php        # Individual pricing config
│       └── tab-business.php          # Business pricing config
├── includes/
│   ├── class-tqb-activator.php      # Plugin activation (DB tables)
│   ├── class-tqb-deactivator.php    # Plugin deactivation
│   ├── class-tqb-admin.php          # Admin functionality
│   ├── class-tqb-db.php             # Database operations
│   ├── class-tqb-email.php          # Email sending
│   ├── class-tqb-hubspot.php        # HubSpot CRM integration
│   ├── class-tqb-pricing-engine.php # Pricing calculation logic
│   ├── class-tqb-public.php         # Frontend AJAX handlers
│   └── class-tqb-quote-handler.php  # Quote submission handling
├── public/
│   ├── css/tqb-public.css           # Frontend styles
│   ├── js/tqb-public.js             # Frontend JavaScript
│   └── views/form-template.php       # Quote form HTML
└── tests/
    └── test-pricing-engine.php      # Unit tests
```

---

## Database Schema

### Table: `wp_tqb_submissions`

Stores all quote submissions (completed, partial, and abandoned).

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key, auto-increment |
| quote_type | VARCHAR(50) | 'individual', 'business', or 'combined' |
| contact_name | VARCHAR(100) | User's name |
| contact_email | VARCHAR(100) | User's email |
| contact_phone | VARCHAR(20) | User's phone |
| answers | LONGTEXT | JSON of all form answers |
| calculated_total | DECIMAL(10,2) | Final quote amount (NULL if custom) |
| is_custom_quote | TINYINT(1) | 1 if custom quote required |
| custom_quote_reason | VARCHAR(100) | Reason for custom quote |
| status | VARCHAR(20) | 'in_progress', 'completed', 'abandoned' |
| last_completed_step | INT | Last step user completed |
| user_ip | VARCHAR(45) | User's IP address (IPv4/IPv6) |
| hubspot_contact_id | VARCHAR(50) | HubSpot contact ID |
| hubspot_deal_id | VARCHAR(50) | HubSpot deal ID |
| hubspot_sync_failed | TINYINT(1) | 1 if last sync failed |
| reminder_email_sent | TINYINT(1) | 1 if reminder email sent |
| followup_email_sent | TINYINT(1) | 1 if follow-up email sent |
| final_email_sent | TINYINT(1) | 1 if final email sent |
| confirmation_email_sent | TINYINT(1) | 1 if confirmation sent to user |
| team_notified | TINYINT(1) | 1 if team notification sent |
| created_at | DATETIME | Record creation time |
| updated_at | DATETIME | Last update time |

### Table: `wp_tqb_line_items`

Stores pricing configuration for Individual and Business line items.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| item_type | VARCHAR(20) | 'individual' or 'business' |
| item_key | VARCHAR(50) | Unique identifier |
| item_label | VARCHAR(200) | Display label |
| fee | DECIMAL(10,2) | Fee amount |
| pricing_pattern | VARCHAR(20) | 'qty_times_fee', 'flat', 'hardcoded' |
| hardcoded_value | DECIMAL(10,2) | Value for hardcoded pattern |
| threshold_qty | DECIMAL(10,2) | Quantity threshold for custom quote |
| threshold_trigger | VARCHAR(10) | 'above' or 'below' threshold |
| is_custom_quote_trigger | TINYINT(1) | Always triggers custom quote |
| notes | TEXT | Help text for users |
| display_order | INT | Sort order |
| is_active | TINYINT(1) | Whether item is shown |

### Table: `wp_tqb_asset_bands`

Stores Business return asset band pricing.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| entity_type | VARCHAR(20) | 'c_s_corp', 'partnership' |
| band_label | VARCHAR(50) | Display label |
| min_assets | DECIMAL(15,2) | Minimum assets |
| max_assets | DECIMAL(15,2) | Maximum assets |
| base_price | DECIMAL(10,2) | Base price for this band |

---

## Core Components

### 1. TQB_Pricing_Engine

**File:** `includes/class-tqb-pricing-engine.php`  
**Purpose:** Pure calculation logic with ZERO WordPress dependencies

This class is completely standalone and can be unit tested independently.

#### Individual Return Calculation

```
Formula: Total = Base Fee + Σ(Line Items)

Base Fee:
- W-2 wage income: $350 (always applies)

Line Item Patterns:
1. Qty × Fee:        Amount = Qty × Fee
2. Flat Fee:         Amount = Fee (Qty ignored)
3. Hardcoded:       Amount = hardcoded_value
4. Custom Quote:    Amount = N/A (skips calculation)
```

#### Business Return Calculation

```
Formula: Total = Base Fee (Part A) + Extras (Part B)

Part A - Base Fee:
├── Step 1: Custom Quote Check
│   └── If assets $5M-$10M OR Over $10M → Custom Quote
│
├── Step 2: Schedule L Check
│   ├── C-Corp/S-Corp: $999 if assets <$250K AND revenue <$250K
│   └── Partnership: $999 if assets <$250K AND revenue <$250K AND assets ≤$1M
│
└── Step 3: Asset Band + Revenue Add-on
    ├── Base Price from asset band table
    └── Plus $200 if revenue > $1M

Part B - Extras:
└── Sum of all applicable extra items
```

---

### 2. TQB_DB

**File:** `includes/class-tqb-db.php`  
**Purpose:** Database operations wrapper

| Method | Description |
|--------|-------------|
| `get_line_items()` | Get line items by type |
| `get_asset_bands()` | Get asset bands by entity type |
| `get_revenue_addons()` | Get revenue band add-ons |
| `get_submission()` | Get single submission by ID |
| `save_submission()` | Create/update submission |

---

### 3. TQB_Public

**File:** `includes/class-tqb-public.php`  
**Purpose:** Frontend AJAX handlers and script enqueuing

#### AJAX Actions

| Action | Handler | Description |
|--------|---------|-------------|
| `tqb_save_partial` | handle_save_partial | Save partial form progress |
| `tqb_submit_quote` | handle_submit | Final form submission |
| `tqb_check_partial_by_ip` | handle_check_partial_by_ip | Check for existing partial |
| `tqb_dismiss_partial` | handle_dismiss_partial | Mark partial as abandoned |

---

### 4. TQB_Quote_Handler

**File:** `includes/class-tqb-quote-handler.php`  
**Purpose:** Quote submission processing and validation

#### Submission Flow

```
1. Validate CSRF nonce
2. Sanitize inputs
3. Validate contact info
4. Check rate limits
5. Check for duplicate email
6. Calculate quote using Pricing Engine
7. Save to database
8. Trigger emails
9. Sync to HubSpot
```

---

### 5. TQB_Email

**File:** `includes/class-tqb-email.php`  
**Purpose:** All email sending functionality

#### Email Types

| Email | Recipient | Trigger |
|-------|-----------|---------|
| Confirmation | User | On successful submission |
| Team Notification | Admin/Team | On successful submission |
| Reminder | User | 24 hours after partial (if enabled) |
| Follow-up | User | 72 hours after partial (offer call) |
| Final | User | 168 hours after partial (marks abandoned) |

---

### 6. TQB_HubSpot

**File:** `includes/class-tqb-hubspot.php`  
**Purpose:** HubSpot CRM integration via Private App Token

#### Sync Process

```
1. Search for existing contact by email
   └── If found: Update contact
   └── If not found: Create new contact

2. Create or update Deal
   └── Link deal to contact
   └── Set deal properties

3. Attach line items to deal
```

---

## Frontend Flow (5-Step Wizard)

### Step 1: Return Type Selection
- User selects Individual and/or Business
- Both can be selected (combined quote)

### Step 2: Contact Information
- User enters name, email, phone
- Partial saved to database on completion

### Step 3: Filing Details
- Dynamic questions based on selected type
- Individual: W-2, rental property, etc.
- Business: Entity type, assets, revenue, extras

### Step 4: Review
- Shows all answers before submission
- User can go back to edit

### Step 5: Result
- Shows calculated quote or "Custom Quote Required"
- Email confirmation sent
- Schedule Call button if configured

---

## Submission Status Flow

```
┌─────────────────┐
│ Form Loaded     │
│ (No Status)     │
└────────┬────────┘
         │
         │ User enters contact info
         ▼
┌─────────────────┐
│  in_progress     │◄────────────────┐
│  (Partial Saved)│                 │
└────────┬────────┘                 │
         │                          │
    ┌────┴────┬─────────────┐        │
    │         │             │        │
    ▼         ▼             ▼        │
┌───────┐ ┌────────┐ ┌──────────┐    │
│Submit │ │Start  │ │Cron Check│    │
│       │ │Over   │ │(24h/72h)│────┘
└───┬───┘ └───┬────┘ └────┬─────┘
    │         │            │
    ▼         ▼            ▼
┌─────────────────┐ ┌─────────────────┐
│   completed     │ │   abandoned     │
│  (Final Submit) │ │ (User Quit)    │
└─────────────────┘ └─────────────────┘
```

---

## Rate Limiting

### Configuration

```php
MAX_SUBMISSIONS_PER_IP = 5        // Max submissions per IP
RATE_LIMIT_WINDOW_HOURS = 24      // Time window in hours
COOLDOWN_MINUTES = 30            // Cooldown after limit hit
```

### Rate Limit Logic

```
1. Check submissions in last 24 hours for this IP
2. If count >= 5 → Block with rate limit error
3. If within cooldown → Show remaining time
4. Otherwise → Allow submission
```

---

## IP Address Handling

### Detection Order

1. HTTP_CF_CONNECTING_IP (Cloudflare)
2. HTTP_X_FORWARDED_FOR (Proxies)
3. HTTP_X_FORWARDED
4. HTTP_X_CLUSTER_CLIENT_IP
5. HTTP_FORWARDED_FOR
6. HTTP_FORWARDED
7. REMOTE_ADDR (Direct connection)

### Notes
- Falls back to `0.0.0.0` if no valid IP found
- X-Forwarded-For can contain multiple IPs (takes first one)
- Validates both IPv4 and IPv6 addresses
- Localhost: `127.0.0.1` (IPv4) or `::1` (IPv6)

---

## Cron Jobs

### Abandoned Quote Follow-up

**Hook:** `tqb_send_abandoned_emails`  
**Schedule:** Hourly

```
Every hour:
├── Send reminder email (24h) if in_progress > 24h
├── Send follow-up email (72h) if reminder sent
├── Send final email (168h) and mark abandoned
```

---

## Security Measures

1. **CSRF Protection:** All AJAX actions use WordPress nonces
2. **Input Sanitization:** All user inputs are sanitized
3. **SQL Injection Prevention:** Using $wpdb->prepare()
4. **XSS Prevention:** Using esc_html(), esc_url()
5. **Rate Limiting:** Per-IP submission limits
6. **Contact Verification:** Contact info must match for partial updates

---

## Recent Fixes

### Issues Fixed (Rate Limiting Feature)

1. **Start Over Warning:** Now properly abandons ALL partial records for IP
2. **Abandoned Records:** No longer accidentally updated/modified
3. **Duplicate Schedule Button:** Removed duplicate on custom quotes
4. **Resume Banner:** Removed (not working properly)

### Status Flow (Confirmed)

| Action | Status | Follow-up Emails |
|--------|--------|------------------|
| User enters contact, leaves | in_progress | Yes |
| User clicks Start Over | abandoned | No |
| User completes submission | completed | No |

---

## WordPress Options

| Option | Default | Description |
|--------|---------|-------------|
| tqb_disclaimer_text | (varies) | Disclaimer shown on result |
| tqb_scheduling_link | '' | Calendly/scheduling URL |
| tqb_team_notification_email | admin_email | Team email |
| tqb_hubspot_api_key | '' | HubSpot Private App Token |
| tqb_enable_abandoned_emails | 1 | Enable follow-up emails |
| tqb_reminder_email_hours | 24 | Hours before reminder |
| tqb_followup_email_hours | 72 | Hours before follow-up |
| tqb_final_email_hours | 168 | Hours before final |

---

## Future Enhancements

1. **Resume Feature:** Rebuild with proper data restoration
2. **Crypto Volume Threshold:** Two-part question (Yes/No + volume)
3. **Help Text Rewrite:** Customer-friendly explanations
4. **Multiple Businesses:** Handle multiple business types
5. **Export Functionality:** CSV export in admin

---

*Report generated: 2026-07-24*  
*Plugin: tavola-quote-builder*
