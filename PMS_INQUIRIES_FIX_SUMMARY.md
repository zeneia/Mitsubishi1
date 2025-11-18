# PMS Inquiries Display Issue - Fix Summary

## Problem
The sales agent PMS inquiries page (`pages/agent_pms_inquiries.php`) was showing "No PMS Inquiries" even though the database contained 14 PMS inquiry records.

## Root Cause
The SQL query in `agent_pms_inquiries.php` had an incorrect JOIN condition:

**INCORRECT (Before Fix):**
```sql
SELECT
    pi.id as inquiry_id,
    pi.pms_id,
    pi.status,
    ...
    acc.FirstName,
    acc.LastName,
    acc.Email,
    acc.PhoneNumber
FROM pms_inquiries pi
LEFT JOIN car_pms_records cpr ON pi.pms_id = cpr.pms_id
LEFT JOIN accounts acc ON cpr.customer_id = acc.Id  -- ❌ WRONG!
ORDER BY pi.created_at DESC
```

The query was joining the `accounts` table using `cpr.customer_id` (from the `car_pms_records` table), but it should have been using `pi.customer_id` (from the `pms_inquiries` table).

### Why This Caused the Issue
- The `pms_inquiries` table has a `customer_id` field that directly references the customer
- The `car_pms_records` table also has a `customer_id` field
- When joining through `cpr.customer_id`, if the PMS record data was incomplete or the join failed, the customer information would be NULL
- This caused the display to fail or show incomplete data

## Solution
Changed the JOIN to use the correct customer_id from the pms_inquiries table:

**CORRECT (After Fix):**
```sql
SELECT
    pi.id as inquiry_id,
    pi.pms_id,
    pi.customer_id,  -- Added this field
    pi.status,
    ...
    acc.FirstName,
    acc.LastName,
    acc.Email,
    ci.mobile_number as PhoneNumber  -- Get phone from customer_information
FROM pms_inquiries pi
LEFT JOIN car_pms_records cpr ON pi.pms_id = cpr.pms_id
LEFT JOIN accounts acc ON pi.customer_id = acc.Id  -- ✅ CORRECT!
LEFT JOIN customer_information ci ON pi.customer_id = ci.account_id  -- Added for phone number
ORDER BY pi.created_at DESC
```

## Additional Improvements
1. **Added NULL value handling** in the display logic to prevent errors when data is incomplete:
   ```php
   $model = $inquiry['model'] ?? 'Unknown Model';
   $pms_info = $inquiry['pms_info'] ?? 'PMS Service';
   $plate_number = $inquiry['plate_number'] ?? 'N/A';
   $customer_name = trim(($inquiry['FirstName'] ?? '') . ' ' . ($inquiry['LastName'] ?? ''));
   if (empty($customer_name)) {
       $customer_name = 'Unknown Customer';
   }
   $email = $inquiry['Email'] ?? 'N/A';
   ```

2. **Added customer_id to SELECT** to ensure we have the correct reference for the JOIN

3. **Fixed PhoneNumber column issue** - The `accounts` table doesn't have a `PhoneNumber` column. Phone numbers are stored in `customer_information.mobile_number`, so we:
   - Added a JOIN with `customer_information` table
   - Changed the SELECT to use `ci.mobile_number as PhoneNumber`

## Files Modified
- `pages/agent_pms_inquiries.php` - Fixed JOIN condition and added NULL handling

## Testing
To verify the fix works:
1. Log in as a sales agent
2. Navigate to the PMS Inquiries page
3. You should now see all 14 PMS inquiry records displayed correctly
4. Each record should show:
   - Vehicle model and PMS service type
   - Plate number
   - Customer name
   - Customer email
   - Status (Open, In Progress, etc.)
   - "View Messages" button
   - "Assign to Me" button (for unassigned inquiries)

## Debug Files Created
- `pages/debug_agent_pms.php` - Debug page to test the exact query and see raw data

## Related Database Tables
- `pms_inquiries` - Main inquiry tracking table
- `car_pms_records` - PMS service records
- `accounts` - User/customer account information

## Prevention
When writing queries that join multiple tables:
1. Always verify which table contains the primary reference field
2. Use table aliases clearly (pi, cpr, acc) to avoid confusion
3. Test queries with actual data to ensure JOINs work correctly
4. Add NULL handling in display logic to gracefully handle incomplete data

