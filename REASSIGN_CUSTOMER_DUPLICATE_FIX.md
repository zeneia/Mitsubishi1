# Customer Reassignment Duplicate Issue - Fixed

## Problem Description

When reassigning a customer to another agent using the reassign customer feature, the system was creating **duplicate records** in the `customer_information` table instead of updating the existing customer's `agent_id`.

## Root Cause

The issue was in the `setCustomerAgentByAccountId()` method in `includes/database/customer_operations.php`.

### The Problematic Code (Before Fix)

```php
// Ensure customer row exists
$ins = $this->pdo->prepare("INSERT INTO customer_information (account_id, agent_id, updated_at) 
                             VALUES (:aid, :gid, CURRENT_TIMESTAMP)
                             ON DUPLICATE KEY UPDATE agent_id = VALUES(agent_id), updated_at = CURRENT_TIMESTAMP");
return $ins->execute([':aid' => $accountId, ':gid' => $agentId]);
```

### Why It Failed

The `ON DUPLICATE KEY UPDATE` clause in MySQL **only works when there's a PRIMARY KEY or UNIQUE constraint violation**.

Looking at the `customer_information` table structure:
- **Primary Key**: `cusID` (auto-increment)
- **NO UNIQUE constraint on `account_id`** ❌

Since `account_id` is NOT unique, MySQL would:
1. Try to INSERT a new row with the given `account_id` and `agent_id`
2. Since there's no constraint violation (no unique constraint on `account_id`), it would successfully INSERT
3. This created a **duplicate customer record** with a new `cusID` but the same `account_id`

## The Fix

Changed the logic to explicitly check if a customer record exists before deciding to UPDATE or INSERT:

```php
// Check if customer_information row exists for this account_id
$checkStmt = $this->pdo->prepare("SELECT cusID FROM customer_information WHERE account_id = :aid");
$checkStmt->execute([':aid' => $accountId]);
$existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    // Update existing customer record
    $upd = $this->pdo->prepare("UPDATE customer_information SET agent_id = :gid, updated_at = CURRENT_TIMESTAMP WHERE account_id = :aid");
    return $upd->execute([':gid' => $agentId, ':aid' => $accountId]);
} else {
    // Insert new customer record
    $ins = $this->pdo->prepare("INSERT INTO customer_information (account_id, agent_id, updated_at) VALUES (:aid, :gid, CURRENT_TIMESTAMP)");
    return $ins->execute([':aid' => $accountId, ':gid' => $agentId]);
}
```

## Files Modified

- `includes/database/customer_operations.php` - Fixed `setCustomerAgentByAccountId()` method (lines 281-333)

## How It Works Now

1. **Validation**: Validates that the account exists and is a Customer, and the agent is an active Sales Agent
2. **Check Existence**: Queries the database to check if a `customer_information` record already exists for the given `account_id`
3. **Update or Insert**:
   - If record exists → **UPDATE** the `agent_id` and `updated_at` fields
   - If record doesn't exist → **INSERT** a new record

## Testing Recommendations

1. **Test Reassignment**: 
   - Create a test customer assigned to Agent A
   - Reassign the customer to Agent B
   - Verify that only ONE record exists in `customer_information` for that customer
   - Verify the `agent_id` has been updated to Agent B

2. **Check for Existing Duplicates**:
   ```sql
   SELECT account_id, COUNT(*) as count 
   FROM customer_information 
   GROUP BY account_id 
   HAVING count > 1;
   ```
   This will show any customers that have duplicate records.

3. **Clean Up Duplicates** (if any exist):
   ```sql
   -- Keep only the most recent record for each account_id
   DELETE ci1 FROM customer_information ci1
   INNER JOIN customer_information ci2 
   WHERE ci1.account_id = ci2.account_id 
   AND ci1.cusID < ci2.cusID;
   ```
   ⚠️ **BACKUP YOUR DATABASE BEFORE RUNNING THIS!**

## Related Features

This fix affects the following features:
- **Accounts Page** (`pages/main/accounts.php`) - Reassign customer action
- **Handled Clients Page** (`pages/main/handled-clients.php`) - Client reassignment
- Any other code that calls `CustomerOperations::setCustomerAgentByAccountId()`

## Future Recommendation

Consider adding a **UNIQUE constraint** on the `account_id` column in the `customer_information` table to prevent duplicates at the database level:

```sql
ALTER TABLE customer_information 
ADD UNIQUE KEY unique_account_id (account_id);
```

This would provide an additional layer of protection against duplicate records. However, ensure this won't break any existing functionality before applying it.

