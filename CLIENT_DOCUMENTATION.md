# Tavola Quote Builder - Client Documentation

**Version:** 1.0  
**Date:** July 2024  
**For:** Tavola Group

---

## Table of Contents

1. [Overview](#1-overview)
2. [Features](#2-features)
3. [How It Works](#3-how-it-works)
4. [User Guide](#4-user-guide)
5. [Admin Dashboard](#5-admin-dashboard)
6. [Email Notifications](#6-email-notifications)
7. [HubSpot Integration](#7-hubspot-integration)
8. [Pricing Rules](#8-pricing-rules)
9. [Settings & Configuration](#9-settings--configuration)
10. [FAQ](#10-faq)
11. [Support](#11-support)

---

## 1. Overview

### What Is This?

The **Tavola Quote Builder** is a WordPress plugin that allows your website visitors to get instant tax preparation quotes without needing to contact you directly.

### How Does It Help Your Business?

- **24/7 Availability:** Prospects can get quotes anytime, even outside business hours
- **Instant Results:** No waiting for email responses or phone calls
- **Lead Capture:** Automatically captures leads even from incomplete submissions
- **CRM Integration:** New submissions flow directly into HubSpot
- **Follow-up Automation:** Automatically reminds prospects who don't complete the form

---

## 2. Features

### For Your Website Visitors

| Feature | Description |
|---------|-------------|
| Multi-step Questionnaire | Easy 5-step process to get a quote |
| Real-time Preview | See running total as they answer questions |
| Individual & Business | Supports both tax return types |
| Combined Quotes | Can quote both in one submission |
| Review Before Submit | Users review all answers before final submission |
| Email Confirmation | Immediate confirmation email after submission |

### For Your Team

| Feature | Description |
|---------|-------------|
| Admin Dashboard | Manage pricing, questions, and settings |
| Email Notifications | Team receives email with every submission |
| HubSpot Integration | Auto-creates contacts and deals |
| Abandoned Lead Follow-up | Automated email sequence for incomplete forms |
| Submission Reports | View all submissions in admin |
| Rate Limiting | Prevents abuse/spam submissions |

---

## 3. How It Works

### The 5-Step Process

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  STEP 1: SELECT RETURN TYPE                                      │
│  ┌──────────────────────┐  ┌──────────────────────┐           │
│  │ □ Individual         │  │ □ Business           │           │
│  │   Personal tax       │  │   C-Corp, S-Corp,    │           │
│  │   return             │  │   Partnership        │           │
│  └──────────────────────┘  └──────────────────────┘           │
│                                                                 │
│  STEP 2: CONTACT INFORMATION                                     │
│  ┌──────────────────────────────────────────────┐               │
│  │ Name:    [________________________]         │               │
│  │ Email:   [________________________]         │               │
│  │ Phone:   [________________________]         │               │
│  └──────────────────────────────────────────────┘               │
│                                                                 │
│  STEP 3: FILING DETAILS                                         │
│  ┌──────────────────────────────────────────────┐               │
│  │ Answer questions about your tax situation   │               │
│  │ (W-2 wages, rental property, states, etc.)   │               │
│  └──────────────────────────────────────────────┘               │
│                                                                 │
│  STEP 4: REVIEW                                                 │
│  ┌──────────────────────────────────────────────┐               │
│  │ Review all answers and estimated total       │               │
│  └──────────────────────────────────────────────┘               │
│                                                                 │
│  STEP 5: RESULT                                                 │
│  ┌──────────────────────────────────────────────┐               │
│  │           Your Estimated Quote               │               │
│  │              $750.00                        │               │
│  │                                               │               │
│  │  [Get Another Quote]  [Schedule a Call]      │               │
│  └──────────────────────────────────────────────┘               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### What Happens When Someone Submits?

1. **Quote Calculated** - System calculates price based on answers
2. **Confirmation Email** - User receives confirmation email
3. **Team Notification** - Your team receives email with full details
4. **HubSpot Created** - Contact and Deal created in your CRM
5. **Partial Saved** - If form started but not finished, saved for follow-up

---

## 4. User Guide

### For Your Website Visitors

#### Getting a Quote

1. Visit the quote page on your website
2. Select the type of return (Individual, Business, or both)
3. Enter contact information
4. Answer questions about your tax situation
5. Review your answers and estimated total
6. Submit to receive your quote

#### After Submitting

- **Confirmation Email:** Within seconds, they'll receive an email confirming their submission
- **Team Follow-up:** Someone from your team will reach out within 1-2 business days
- **Schedule a Call:** They can book a call directly from the result page (if configured)

#### If They Don't Finish

If someone starts but doesn't complete the form:

| Time | What Happens |
|------|--------------|
| 24 hours | Reminder email: "Complete your tax quote" |
| 72 hours | Follow-up email: "Need help? Schedule a call" |
| 1 week | Final reminder email |

*Note: They can click "Start Over" to begin fresh - this stops follow-up emails.*

---

## 5. Admin Dashboard

### Accessing the Dashboard

1. Log into WordPress admin
2. Look for **"Quote Builder"** in the left menu
3. Click to access settings and submissions

### Dashboard Sections

#### Submissions Tab
- View all quote submissions
- Filter by status (Completed, In Progress, Abandoned)
- Search by email or name
- Export data to CSV

#### Individual Pricing Tab
- Manage Individual return line items
- Set fees and pricing patterns
- Add help text for questions
- Reorder items

#### Business Pricing Tab
- Manage Business return pricing
- Configure entity types (C-Corp, S-Corp, Partnership)
- Set asset band pricing
- Configure extra service fees

#### Settings Tab
- Disclaimer text
- Scheduling link (Calendly)
- Team notification email
- Abandoned email settings
- HubSpot API configuration

---

## 6. Email Notifications

### Emails Sent to Customers

| Email | When | Purpose |
|-------|------|---------|
| Confirmation | After submission | Acknowledge receipt |
| Reminder | 24h after partial | Encourage completion |
| Follow-up | 72h after partial | Offer call |
| Final | 1 week after partial | Last chance |

### Emails Sent to Your Team

| Email | When | Contents |
|-------|------|----------|
| New Submission | After form completion | Name, email, phone, quote type, estimated total |

### Email Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Enable Follow-up Emails | Yes | Send automated follow-ups |
| Reminder Timing | 24 hours | When to send first reminder |
| Follow-up Timing | 72 hours | When to send follow-up |
| Final Reminder | 1 week | When to send final email |
| Team Email | (Admin email) | Where to send notifications |

---

## 7. HubSpot Integration

### What Gets Synced?

When a submission is received, the following is automatically created in HubSpot:

#### Contact
| Field | Maps To |
|-------|---------|
| Email | contact_email |
| First Name | contact_name (first word) |
| Last Name | contact_name (remaining words) |
| Phone | contact_phone |

#### Deal
| Field | Maps To |
|-------|---------|
| Deal Name | "[Type] Quote - [Name]" |
| Amount | calculated_total |
| Deal Type | Quote Type (Individual/Business) |
| Stage | Appointment Scheduled |

### Requirements

- HubSpot Private App Token (provided by your developer)
- API access enabled in HubSpot

---

## 8. Pricing Rules

### Individual Returns

**Base Fee:** $350 (W-2 wages - always applies)

**Additional Services:**

| Service | Fee | Notes |
|---------|-----|-------|
| Multiple states | $150 each | Per additional state |
| 1099-INT/DIV | $25 flat | Interest/dividends |
| 1099-B (Brokerage) | $25 each | Per sale |
| Rental property | $200 each | Per property |
| Self-employed | $200 flat | Sole proprietor/LLC |
| Farm income | $275 flat | |
| K-1 received | $50 each | Per K-1 |
| HSA | $25 flat | |
| Home sale (1099-S) | $150 flat | |
| Retirement (1099-R) | $100 flat | |
| Crypto | Custom | Over $100K volume |
| Foreign accounts | Custom | FBAR required |

**Note:** "Custom" means the situation requires a personalized quote.

### Business Returns

**Base Fee (Part A):**
- Schedule L NOT required: **$999 flat**
- Based on assets/revenue: See table below

**Asset Band Pricing (Part A):**

| Asset Level | C-Corp/S-Corp | Partnership |
|-------------|---------------|-------------|
| Under $250K | $1,250 | $1,250 |
| $250K-$500K | $1,250 | $1,250 |
| $500K-$1M | $1,500 | $1,250 |
| $1M-$2M | $1,500 | $1,500 |
| $2M-$5M | $1,750 | $1,700 |
| $5M+ | Custom | Custom |

**Revenue Add-on:** +$200 if annual revenue over $1M

**Extra Services (Part B):**

| Service | Fee |
|---------|-----|
| Multiple partners (extra K-1s) | $25 each |
| Multiple states | $250 each |
| Fixed asset schedule | $250 |
| Foreign partner | $350 |
| Books don't match tax | $250 |

---

## 9. Settings & Configuration

### Setting Up the Plugin

#### 1. Add to Website
Place this shortcode on any page:
```
[tavola_quote_builder]
```

#### 2. Configure Settings

| Setting | Where to Find | Notes |
|---------|---------------|-------|
| Disclaimer | Settings Tab | Shown below quote |
| Scheduling Link | Settings Tab | Calendly/scheduling URL |
| Team Email | Settings Tab | Where notifications go |
| HubSpot Token | Settings Tab | For CRM sync |

#### 3. Customize Pricing

- **Individual Tab:** Modify line items and fees
- **Business Tab:** Adjust asset bands and extras
- Changes take effect immediately

---

## 10. FAQ

### General

**Q: Can someone get a quote for both Individual and Business?**
A: Yes! They can select both types and get a combined quote.

**Q: What if someone's situation is complicated?**
A: If their situation triggers "Custom Quote," they'll be shown a message directing them to schedule a call.

**Q: Can users save their progress and come back later?**
A: Progress is saved automatically when they enter contact info. They can click "Start Over" to begin fresh.

### Technical

**Q: Does this work with my existing theme?**
A: Yes, it uses your site's existing styles and is designed to blend with most themes.

**Q: Is there spam protection?**
A: Yes, there's rate limiting (max 5 submissions per IP per 24 hours) and CSRF protection.

**Q: What happens if HubSpot is down?**
A: Submissions are still saved locally. HubSpot sync will be retried automatically.

### Follow-up Emails

**Q: How do I disable follow-up emails?**
A: Go to Settings → uncheck "Enable Abandoned Email Follow-up"

**Q: What if someone doesn't want emails?**
A: If they click "Start Over," their partial is marked as abandoned and won't receive emails.

**Q: Can I edit the follow-up email content?**
A: Currently, email content is set by the developer. Contact your developer to modify.

---

## 11. Support

### For Technical Issues

Contact your WordPress developer or IT support team.

### For Business Questions

Contact your project manager at Tavola Group.

---

## Appendix: Submission Statuses

| Status | Meaning |
|--------|---------|
| **In Progress** | User started but hasn't completed |
| **Completed** | User submitted the form |
| **Abandoned** | User quit OR clicked Start Over |

---

*Document prepared for Tavola Group*  
*Plugin: Tavola Quote Builder v1.0*
