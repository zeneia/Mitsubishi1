# Test Drive Gatepass PDF Update Instructions

## Overview
The test drive gatepass PDF has been updated to include all requested information fields. However, to fully enable the "Approved By" feature, you need to run a database migration.

## Changes Made

### 1. Updated Files

#### `pages/test_drive_pdf.php`
- Enhanced database query to fetch approver information
- Added logic to extract license number from notes field
- Updated PDF layout to display all requested fields:
  - Customer name
  - License number
  - Vehicle (model, variant, year)
  - Schedule date
  - Time
  - Location
  - Instructor
  - Approved By

#### `pages/header_ex.php`
- Fixed JavaScript error by adding null checks in `responsiveHeader()` function
- This prevents errors on pages with different header structures

#### `pages/test/test_drive_management.php`
- Updated approval process to store `approved_by` field with current user's ID

#### `includes/backend/test_drive_backend.php`
- Updated `approveRequest()` function to store `approved_by` field

### 2. Database Migration Required

**IMPORTANT:** You must run the following SQL script to add the `approved_by` column to the `test_drive_requests` table:

```
includes/database/add_approved_by_to_test_drive.sql
```

**How to run:**
1. Open phpMyAdmin or your MySQL client
2. Select your database
3. Go to SQL tab
4. Copy and paste the contents of `includes/database/add_approved_by_to_test_drive.sql`
5. Click "Go" or "Execute"

This script will:
- Add `approved_by` column (INT) to store the ID of the user who approved the request
- Add a foreign key constraint to link to the `accounts` table
- Add an index for better query performance
- Safely check if the column already exists before adding it

### 3. Testing the Changes

#### To test the gatepass PDF:

1. **Run the database migration first** (see step 2 above)

2. **Debug existing data:**
   - Open `debug_test_drive_data.php?request_id=57` in your browser
   - This will show you the table structure and data for request ID 57
   - Check if the license number is in the notes field
   - Check if the approved_by field exists

3. **Test with a new approval:**
   - Go to the test drive management page
   - Approve a pending test drive request
   - The system will now store who approved it
   - View the gatepass PDF to see all fields populated

4. **For existing approved requests:**
   - The "Approved By" field will fall back to showing the assigned sales agent's name
   - For new approvals, it will show the actual person who clicked "Approve"

## Known Issues & Solutions

### Issue 1: License Number Not Showing
**Cause:** The license number is stored in the `notes` field as text, not in a separate column.

**Solution:** The code now extracts it using regex pattern matching. If the license number is not showing:
- Check if the notes field contains "License Number: [value]"
- Use the debug script to verify: `debug_test_drive_data.php?request_id=XX`

### Issue 2: Approved By Not Showing
**Cause:** The `approved_by` column doesn't exist in the database yet.

**Solution:** Run the migration script `includes/database/add_approved_by_to_test_drive.sql`

### Issue 3: JavaScript Error on test_drive_success.php
**Cause:** The page has a different header structure than expected by `header_ex.php`

**Solution:** Already fixed by adding null checks in the `responsiveHeader()` function.

## Fallback Behavior

The system is designed with fallback values:
- **License Number:** Shows "N/A" if not found in notes
- **Instructor:** Shows "To be assigned" if not set
- **Approved By:** Shows the assigned sales agent's name if `approved_by` is null, or "Pending Approval" if neither is available
- **Location:** Shows "Showroom" if not set

## Files Created

1. `includes/database/add_approved_by_to_test_drive.sql` - Database migration script
2. `debug_test_drive_data.php` - Debug script to inspect test drive request data
3. `TEST_DRIVE_GATEPASS_UPDATE_INSTRUCTIONS.md` - This file

## Next Steps

1. ✅ Run the database migration
2. ✅ Test with the debug script
3. ✅ Approve a new test drive request to test the full flow
4. ✅ Print/download the gatepass PDF to verify all fields are showing correctly

