# Changelog

All notable changes to the Tavola Quote Builder plugin.

## [0.4.1] - 2026-07-29

### Added
- **View Details Modal**: New "View" button in the Submissions table that opens a detailed modal showing:
  - Contact information (name, email, phone, IP address)
  - Quote details (type, status, result, HubSpot sync status)
  - Business information (entity type, asset band, revenue band)
  - Email statuses (confirmation, reminder, follow-up, final)
  - All answers with quantities and threshold notes
  - Timestamps for created, updated, and email sends
- Modal styled with Tavola brand colors (navy/gold)
- Click outside or press Escape to close modal

## [0.4.0] - 2026-07-24

### Added
- **HubSpot Retry Queue**: Failed HubSpot syncs are now tracked and automatically retried via hourly cron job
- **Admin Notifications**: Daily email notifications to admin when HubSpot sync failures exist
- **Loading States**: Visual feedback (spinner) on buttons during save/submit operations
- **Accessibility (ARIA)**: 
  - Screen reader live region announces step changes
  - `aria-current="step"` on active step indicators
  - Focus management: automatically focus first interactive element on step change
  - `role="status"` with `aria-live="polite"` for dynamic announcements
- **Input Length Limits**: Server-side limits on name (100 chars), phone (20 chars), quote_types (500 chars)
- **Column Existence Caching**: `column_exists()` helper caches column checks per request to reduce DB queries

### Fixed
- **IP Conflict Check**: Now checks ALL submissions from IP (not just in_progress) to prevent duplicate submissions
- **CSRF Protection**: Added nonce verification to `handle_save_partial` and `handle_check_partial_by_ip` AJAX handlers
- **Variable Reference**: Fixed `$column_exists` → `$has_status_column` in save_partial_submission
- **Error Logging**: Added detailed error logging for HubSpot sync failures

### Changed
- `hubspot_sync_failed` column added to submissions table for tracking
- Cron job `tqb_retry_hubspot_syncs` runs hourly to retry failed syncs
- Cron job `tqb_notify_hubspot_failures` runs daily to notify admin of failures

## [0.3.0] - Previous Release

### Added
- Individual vs Business multi-select
- Real-time pricing preview
- Abandoned quote follow-up emails
- HubSpot CRM integration
- Rate limiting on form submission

### Known Issues
See PROJECT_SPEC.md for open questions and technical debt.
