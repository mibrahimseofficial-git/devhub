# CHANGELOG - Fixed Version

## Version 1.2.1 (Critical Bug Fixes)

### 🔧 Bug Fixes

#### Issue #1: "Failed to load pipelines" Error - FIXED
- **File:** `includes/class-tqb-admin.php`
- **Line:** 62
- **Change:** Fixed nonce action mismatch
  - Before: `wp_create_nonce( 'tqb_admin_nonce' )`
  - After: `wp_create_nonce( 'tqb_fetch_pipelines' )`
- **Impact:** Admin can now refresh HubSpot pipelines without error on existing sites
- **Severity:** HIGH - Blocks HubSpot integration configuration

#### Issue #2: Tables Not Creating on New Sites - FIXED
- **File:** `includes/class-tqb-activator.php`
- **Changes:**
  1. Added recovery logic in `activate()` method (after line 32)
     - Re-seeds question sets if table is empty after activation
     - Handles partial failure scenarios
  
  2. Added table safety checks in `upgrade()` method (after line 50)
     - Recreates missing tables during version upgrades
     - Prevents "table doesn't exist" errors on multisite/partial deployments
  
  3. Added `verify_tables_exist()` public static method (before closing brace)
     - Debug tool for verifying database integrity
     - Can be called via WP-CLI or admin pages
- **Impact:** Quote forms now load and work on brand new WordPress installations
- **Severity:** CRITICAL - Blocks all form functionality on new sites

### 📊 What This Fixes

**On Existing Sites:**
- ✅ "Refresh Pipelines" button now works
- ✅ Can configure HubSpot API integration
- ✅ No more "Failed to load pipelines" error

**On New Sites:**
- ✅ Plugin activation creates all 5 database tables
- ✅ Question sets seed correctly (Individual + 4 filing statuses + Business)
- ✅ Quote form appears and functions immediately
- ✅ Form submissions are accepted and saved to database

**General:**
- ✅ Better handling of partial/failed activations
- ✅ Version upgrades include table safety checks
- ✅ Debug method available for troubleshooting

### 🧪 Testing

Before deploying, verify:

```sql
-- Check all tables exist and have seed data
SELECT TABLE_NAME, TABLE_ROWS 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME LIKE 'wp_tqb_%'
ORDER BY TABLE_NAME;
```

Expected results:
- `wp_tqb_line_items`: 26+ rows
- `wp_tqb_question_sets`: 6 rows
- `wp_tqb_question_set_items`: 130+ rows
- `wp_tqb_rate_bands`: 12+ rows
- `wp_tqb_submissions`: 0 rows (initially)

### 📋 Deployment Steps

1. Backup your current WordPress installation
2. Replace the plugin folder with this fixed version
3. Deactivate plugin in WordPress admin
4. Reactivate plugin in WordPress admin
5. Run the SQL verification query above
6. Test form submission and HubSpot pipeline refresh
7. Monitor error logs for 24 hours

### ⚠️ Notes

- **No data loss:** These changes only add initialization logic
- **Backward compatible:** All changes are additions, nothing removed
- **Reversible:** Restore from backup to rollback
- **Zero downtime:** Just a file replacement and plugin reactivation

### 🔍 Files Modified

Only 2 files changed:
- `includes/class-tqb-admin.php`
- `includes/class-tqb-activator.php`

All other plugin files remain unchanged.

---

## Previous Version: 1.2

See CHANGELOG.md for version 1.2 notes.
