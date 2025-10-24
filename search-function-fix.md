# Search Function Fix - Hidden Characters & Multi-Word Search Issue

## Problem Description

Search functionality fails when users copy and paste values into search fields, even when the pasted text exactly matches database values. However, manually typing the same text works correctly.

### Symptoms
- ❌ Pasting "Are Testing" → No results found
- ✅ Typing "Are Testing" manually → Results found correctly
- ❌ Pasting values with exact match → No results
- ❌ Multi-word searches fail when words are in different columns

## Root Causes

### 1. Hidden Characters in Pasted Text
When users copy text from web pages, spreadsheets, or other sources, the clipboard often contains invisible characters:
- **Line breaks** (`\n`, `\r`)
- **Tab characters** (`\t`)
- **Multiple consecutive spaces**
- **Zero-width spaces** and other Unicode whitespace
- **Leading/trailing whitespace**

These hidden characters cause the search query to fail because:
```sql
-- What the database has:
firstname = "Are"

-- What gets searched (with hidden characters):
LIKE '%Are  \n%'  -- Extra spaces and line break
```

### 2. Multi-Word Search Limitation
Original implementation searched for the entire phrase in a single column:
```sql
-- Searching for "Are Testing"
WHERE (firstname LIKE '%Are Testing%' OR lastname LIKE '%Are Testing%')
```

This fails when:
- `firstname = "Are"` and `lastname = "Testing"` (words in different columns)
- The search expects both words in ONE column

## Solution

### Backend Fix (PHP)

**File:** `includes/handlers/pms_handler.php`

#### Step 1: Clean the Search Input
```php
// Trim and clean the search input to handle pasted text with hidden characters
$searchTerm = trim($filters['customer_search']);
// Remove any line breaks, tabs, and extra whitespace
$searchTerm = preg_replace('/\s+/', ' ', $searchTerm);
```

**What this does:**
- `trim()` - Removes leading/trailing whitespace
- `preg_replace('/\s+/', ' ', $searchTerm)` - Replaces all whitespace characters (spaces, tabs, newlines) with a single space

#### Step 2: Split Multi-Word Searches
```php
// Split search term into words for multi-word search
$searchWords = explode(' ', $searchTerm);
$searchConditions = [];

foreach ($searchWords as $index => $word) {
    if (!empty(trim($word))) {
        $paramKey = 'search_' . $index;
        $searchConditions[] = "(c.firstname LIKE :{$paramKey} OR c.lastname LIKE :{$paramKey} OR p.plate_number LIKE :{$paramKey} OR p.model LIKE :{$paramKey} OR CONCAT(c.firstname, ' ', c.lastname) LIKE :{$paramKey})";
        $params[$paramKey] = '%' . trim($word) . '%';
    }
}

if (!empty($searchConditions)) {
    $whereConditions[] = '(' . implode(' AND ', $searchConditions) . ')';
}
```

**What this does:**
- Splits "Are Testing" into `["Are", "Testing"]`
- Creates separate search conditions for each word
- Each word is searched across ALL relevant columns
- Uses `AND` logic: ALL words must be found (but can be in different columns)
- Uses parameterized queries to prevent SQL injection

**Example SQL Generated:**
```sql
WHERE (
    (firstname LIKE '%Are%' OR lastname LIKE '%Are%' OR plate_number LIKE '%Are%' OR model LIKE '%Are%' OR CONCAT(firstname, ' ', lastname) LIKE '%Are%')
    AND
    (firstname LIKE '%Testing%' OR lastname LIKE '%Testing%' OR plate_number LIKE '%Testing%' OR model LIKE '%Testing%' OR CONCAT(firstname, ' ', lastname) LIKE '%Testing%')
)
```

### Frontend Fix (JavaScript)

**File:** `pages/main/pms-tracking.php` (or any page with search)

#### Step 1: Clean Input in applyFilters()
```javascript
function applyFilters() {
    // Trim and clean search input to handle pasted text with hidden characters
    const searchValue = document.getElementById('client-search').value;
    const cleanedSearch = searchValue.trim().replace(/\s+/g, ' ');
    
    const filters = {
        customer_search: cleanedSearch,
        // ... other filters
    };
    
    // ... rest of function
}
```

#### Step 2: Add Paste Event Listener
```javascript
// Clean pasted text to remove hidden characters
searchInput.addEventListener('paste', function(e) {
    setTimeout(() => {
        // Trim and remove extra whitespace from pasted content
        this.value = this.value.trim().replace(/\s+/g, ' ');
        debouncedSearch();
    }, 0);
});
```

**What this does:**
- Intercepts paste events
- Cleans the pasted text immediately (user sees cleaned value)
- Triggers search with cleaned value
- `setTimeout(..., 0)` ensures paste completes before cleaning

## Implementation Checklist

Use this checklist when fixing search functionality on other pages:

### Backend (PHP Handler)
- [ ] Locate the search filter logic (usually in a handler file)
- [ ] Add `trim()` to remove leading/trailing whitespace
- [ ] Add `preg_replace('/\s+/', ' ', $searchTerm)` to normalize whitespace
- [ ] Split search term into words: `explode(' ', $searchTerm)`
- [ ] Create search conditions for each word across all searchable columns
- [ ] Use `AND` logic to combine word conditions (all words must match)
- [ ] Use parameterized queries (`:paramName`) to prevent SQL injection
- [ ] Include `CONCAT()` of name fields for full name matching

### Frontend (JavaScript)
- [ ] Locate the search input element
- [ ] Add paste event listener to clean pasted text
- [ ] Clean search value in filter/search function before sending to API
- [ ] Use `.trim()` to remove whitespace
- [ ] Use `.replace(/\s+/g, ' ')` to normalize whitespace
- [ ] Trigger search after cleaning (if using real-time search)

## Pages That May Need This Fix

Search for these patterns in your codebase to find pages that might have the same issue:

### Search Pattern 1: Backend
```bash
# Find files with LIKE queries that might need fixing
grep -r "LIKE.*search" includes/handlers/
```

Look for:
- `LIKE :search` or `LIKE '%{$search}%'`
- Search functionality without `trim()` or whitespace normalization
- Single-word search logic that doesn't handle multi-word queries

### Search Pattern 2: Frontend
```bash
# Find search input fields
grep -r "getElementById.*search" pages/
```

Look for:
- Search input fields without paste event handlers
- Filter functions that don't clean input before sending to API
- Real-time search without input sanitization

### Common Pages to Check
- Customer Management pages
- Transaction/Order pages
- Inventory/Product pages
- User Management pages
- Any page with a search bar or filter functionality

## Testing the Fix

### Test Case 1: Pasted Text with Hidden Characters
1. Copy text from a webpage or spreadsheet (e.g., "Are Testing")
2. Paste into search field
3. **Expected:** Results found correctly
4. **Verify:** Input field shows cleaned text (no extra spaces)

### Test Case 2: Multi-Word Search
1. Search for "Are Testing" (firstname + lastname in different columns)
2. **Expected:** Finds records where firstname="Are" AND lastname="Testing"
3. Search for "MIRAGE 2024" (model + year)
4. **Expected:** Finds records matching both words

### Test Case 3: Extra Whitespace
1. Type or paste "  MIRAGE  " (with extra spaces)
2. **Expected:** Automatically trims to "MIRAGE"
3. **Expected:** Results found correctly

### Test Case 4: Line Breaks
1. Copy multi-line text (e.g., from Excel with line breaks)
2. Paste into search field
3. **Expected:** Line breaks converted to spaces
4. **Expected:** Search works correctly

### Test Case 5: Single Word Search
1. Search for single word like "MIRAGE"
2. **Expected:** Still works as before (backward compatible)

## Code Examples for Different Scenarios

### Scenario 1: Simple Search (Single Column)
```php
// Before
if (!empty($filters['search'])) {
    $whereConditions[] = "column_name LIKE :search";
    $params['search'] = '%' . $filters['search'] . '%';
}

// After
if (!empty($filters['search'])) {
    $searchTerm = trim($filters['search']);
    $searchTerm = preg_replace('/\s+/', ' ', $searchTerm);
    
    $whereConditions[] = "column_name LIKE :search";
    $params['search'] = '%' . $searchTerm . '%';
}
```

### Scenario 2: Multi-Column Search (Single Word)
```php
// Before
if (!empty($filters['search'])) {
    $whereConditions[] = "(col1 LIKE :search OR col2 LIKE :search)";
    $params['search'] = '%' . $filters['search'] . '%';
}

// After
if (!empty($filters['search'])) {
    $searchTerm = trim($filters['search']);
    $searchTerm = preg_replace('/\s+/', ' ', $searchTerm);
    
    $whereConditions[] = "(col1 LIKE :search OR col2 LIKE :search)";
    $params['search'] = '%' . $searchTerm . '%';
}
```

### Scenario 3: Multi-Column, Multi-Word Search (Recommended)
```php
// After (Full Implementation)
if (!empty($filters['search'])) {
    $searchTerm = trim($filters['search']);
    $searchTerm = preg_replace('/\s+/', ' ', $searchTerm);
    
    $searchWords = explode(' ', $searchTerm);
    $searchConditions = [];
    
    foreach ($searchWords as $index => $word) {
        if (!empty(trim($word))) {
            $paramKey = 'search_' . $index;
            // Add all searchable columns here
            $searchConditions[] = "(col1 LIKE :{$paramKey} OR col2 LIKE :{$paramKey} OR col3 LIKE :{$paramKey})";
            $params[$paramKey] = '%' . trim($word) . '%';
        }
    }
    
    if (!empty($searchConditions)) {
        $whereConditions[] = '(' . implode(' AND ', $searchConditions) . ')';
    }
}
```

## Performance Considerations

### Indexing
For better performance with LIKE queries:
```sql
-- Add indexes on frequently searched columns
CREATE INDEX idx_firstname ON customer_information(firstname);
CREATE INDEX idx_lastname ON customer_information(lastname);
CREATE INDEX idx_plate_number ON car_pms_records(plate_number);
```

### Full-Text Search (Advanced)
For very large datasets, consider MySQL full-text search:
```sql
-- Create full-text index
ALTER TABLE customer_information ADD FULLTEXT(firstname, lastname);

-- Use MATCH AGAINST instead of LIKE
WHERE MATCH(firstname, lastname) AGAINST('Are Testing' IN BOOLEAN MODE)
```

## Security Notes

✅ **Always use parameterized queries** - The fix uses `:paramName` placeholders to prevent SQL injection

✅ **Validate input length** - Consider adding max length validation:
```php
if (strlen($searchTerm) > 100) {
    $searchTerm = substr($searchTerm, 0, 100);
}
```

✅ **Sanitize special characters** - The current fix handles most cases, but for extra security:
```php
// Escape special LIKE characters if needed
$searchTerm = str_replace(['%', '_'], ['\\%', '\\_'], $searchTerm);
```

## Related Issues

This fix also resolves:
- Search not working with copied email addresses
- Search failing with phone numbers containing spaces
- Search not finding partial matches across name fields
- Inconsistent search behavior between typing and pasting

## References

- **Original Issue:** PMS Tracking page search function
- **Fixed Files:**
  - `includes/handlers/pms_handler.php` (lines 88-110)
  - `pages/main/pms-tracking.php` (lines 637-663, 1009-1024)
- **Date Fixed:** 2025-10-24

