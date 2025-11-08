# TPWC Hebrew PDF Generator - Bug & Error Report

## Critical Issues

### 1. **Logger.php:34-41** - Potential Null Return from getLogger()
**Severity:** HIGH
**File:** `includes/Logger.php`
**Line:** 34-41

**Issue:**
```php
private function getLogger(): \WC_Logger
{
    if ($this->logger === null && function_exists('wc_get_logger')) {
        $this->logger = wc_get_logger();
    }
    return $this->logger; // Could return null!
}
```

**Problem:** The method signature promises to return `\WC_Logger` but if `wc_get_logger()` doesn't exist (WooCommerce not loaded), it returns `null`, violating the type contract.

**Impact:** Will cause fatal errors when trying to call methods on null.

**Fix:** Change return type to `?\WC_Logger` or ensure WooCommerce is loaded before instantiating Logger.

---

### 2. **Generator.php:320** - Accessing Public Property on Wrong Object
**Severity:** HIGH
**File:** `includes/PDF/Generator.php`
**Line:** 320

**Issue:**
```php
public function regeneratePdf(int $recordId)
{
    $record = $this->cache->database->get($recordId); // Accessing database through cache!
```

**Problem:** Accessing `$this->cache->database` when `database` is a private property. Should use `$this->fileController->database` or better yet, pass Database to Generator.

**Impact:** Fatal error - accessing private property.

**Fix:** Change to access database properly or pass it as dependency.

---

### 3. **Database.php:336** - Potential SQL Injection in ORDER BY
**Severity:** MEDIUM-HIGH
**File:** `includes/Database/Database.php`
**Line:** 336

**Issue:**
```php
$orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
```

**Problem:** `sanitize_sql_orderby()` returns `false` if validation fails, but this is used directly in SQL without checking.

**Impact:** If validation fails, SQL query becomes `ORDER BY  LIMIT` which is invalid.

**Fix:**
```php
$orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
if ($orderby === false) {
    $orderby = 'created_at DESC'; // fallback
}
```

---

### 4. **CacheManager.php:274** - filesize() Can Return False
**Severity:** MEDIUM
**File:** `includes/Cache/CacheManager.php`
**Line:** 274

**Issue:**
```php
'bytes' => filesize($filePath), // Can return false on error
```

**Problem:** `filesize()` returns `false` on error, but the database expects INT.

**Impact:** Database insertion could fail or store 0 bytes incorrectly.

**Fix:**
```php
'bytes' => filesize($filePath) ?: 0,
```

---

## Security Issues

### 5. **FileController.php:133-140** - No Nonce Verification
**Severity:** MEDIUM
**File:** `includes/PDF/FileController.php`
**Line:** 133-140

**Issue:**
```php
public function handleDownloadRequest(): void
{
    if (!isset($_GET['tpwc_pdf'], $_GET['expires'], $_GET['action'], $_GET['signature'])) {
        return;
    }
    // No nonce check!
```

**Problem:** While HMAC signature is checked, there's no protection against CSRF for the initial request.

**Impact:** Low risk since HMAC is time-limited, but best practice would include nonce.

---

### 6. **ListTable.php:132-145** - XSS Risk in Filter Dropdowns
**Severity:** LOW
**File:** `includes/Admin/ListTable.php`
**Line:** 132-145

**Issue:**
```php
$currentType = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '';
echo '<select name="filter_type">';
echo '<option value="order"' . selected($currentType, 'order', false) . '>...';
```

**Problem:** While `sanitize_text_field()` is used, the value isn't escaped when output in the `selected()` function.

**Impact:** XSS if attacker controls GET parameter.

**Fix:** Use `esc_attr()` on sanitized value:
```php
$currentType = isset($_GET['filter_type']) ? esc_attr(sanitize_text_field($_GET['filter_type'])) : '';
```

---

## Logic/Functional Issues

### 7. **CacheManager.php:238-242** - File Existence Check After DB Query
**Severity:** MEDIUM
**File:** `includes/Cache/CacheManager.php`
**Line:** 238-242

**Issue:**
```php
public function get(string $hash): ?object
{
    $record = $this->database->getByHash($hash);

    if ($record && $record->status === 'cached' && file_exists($record->file_path)) {
```

**Problem:** Race condition - file could be deleted between DB check and actual use.

**Impact:** Cache hit returned but file doesn't exist when later accessed.

**Fix:** Add file existence recheck before streaming or mark record as stale if file missing.

---

### 8. **Activator.php:51-55** - No Error Handling for Directory Creation
**Severity:** MEDIUM
**File:** `includes/class-activator.php`
**Line:** 51-55

**Issue:**
```php
$uploadDir = wp_upload_dir();
$storageDir = $uploadDir['basedir'] . '/tpwc-pdfs';

if (!file_exists($storageDir)) {
    wp_mkdir_p($storageDir); // No check if this succeeds
}
```

**Problem:** No verification that directory was created successfully.

**Impact:** Plugin activation succeeds but PDF generation will fail later.

**Fix:**
```php
if (!file_exists($storageDir)) {
    if (!wp_mkdir_p($storageDir)) {
        wp_die('Failed to create storage directory');
    }
}
```

---

### 9. **Settings.php:189** - Missing Null Coalescing for $value
**Severity:** LOW
**File:** `includes/Admin/Settings.php`
**Line:** 189

**Issue:**
```php
printf(
    '<input type="%s" name="%s" id="%s" value="%s" class="regular-text" />',
    esc_attr($type),
    esc_attr($name),
    esc_attr($name),
    esc_attr($value) // $value could be false from get_option
);
```

**Problem:** If `get_option()` returns `false`, it becomes empty string which is fine, but better to be explicit.

**Fix:**
```php
esc_attr($value ?: '')
```

---

### 10. **OrderTemplate.php:191** - Division by Zero Risk
**Severity:** LOW
**File:** `includes/Templates/OrderTemplate.php`
**Line:** (in getColumnValue method)

**Issue:**
```php
$unitPrice = $item->get_subtotal() / max(1, $item->get_quantity());
```

**Problem:** While `max(1, ...)` prevents division by zero, quantity of 0 should be handled differently.

**Impact:** Shows incorrect unit price if quantity is actually 0.

**Fix:**
```php
$qty = $item->get_quantity();
$unitPrice = $qty > 0 ? $item->get_subtotal() / $qty : 0;
```

---

## Missing Error Handling

### 11. **Generator.php:214-226** - mPDF Errors Not Caught Granularly
**Severity:** MEDIUM
**File:** `includes/PDF/Generator.php`
**Line:** 214-226

**Issue:**
```php
$mpdf = new Mpdf($config);
$mpdf->SetDirectionality('rtl');
$mpdf->WriteHTML($this->getDefaultStyles(), 1);
$mpdf->WriteHTML($html, 2);
$mpdf->Output($filePath, 'F'); // No specific error handling
```

**Problem:** Generic `\Exception` catch in calling method doesn't distinguish between font errors, memory errors, file write errors.

**Impact:** Error messages are generic.

**Fix:** Catch specific mPDF exceptions and log detailed messages.

---

### 12. **CacheManager.php:350-352** - Unlink Failures Ignored
**Severity:** LOW
**File:** `includes/Cache/CacheManager.php`
**Line:** 350-352

**Issue:**
```php
if (file_exists($record->file_path)) {
    unlink($record->file_path); // No check if deletion succeeded
}
```

**Problem:** `unlink()` can fail (permissions, locks), but failure is silently ignored.

**Impact:** Orphaned files on disk, database records deleted but files remain.

**Fix:**
```php
if (file_exists($record->file_path)) {
    if (!@unlink($record->file_path)) {
        $this->logger->warning("Failed to delete file: {$record->file_path}");
    }
}
```

---

## Data Integrity Issues

### 13. **Database.php:404** - SUM(bytes) Can Return NULL
**Severity:** LOW
**File:** `includes/Database/Database.php`
**Line:** 404

**Issue:**
```php
$stats['total_bytes'] = (int) $result->total_bytes; // NULL becomes 0
```

**Problem:** If table is empty, `SUM(bytes)` returns `NULL`, casting to int gives 0 which is correct, but should be explicit.

**Fix:**
```php
$stats['total_bytes'] = (int) ($result->total_bytes ?? 0);
```

---

### 14. **Plugin.php:134-136** - Cron Already Scheduled Check Race Condition
**Severity:** LOW
**File:** `includes/Plugin.php`
**Line:** 134-136

**Issue:**
```php
if (!wp_next_scheduled('tpwc_cleanup_old_pdfs')) {
    wp_schedule_event(time(), 'daily', 'tpwc_cleanup_old_pdfs');
}
```

**Problem:** On multisite or during concurrent requests, this could schedule multiple events.

**Impact:** Cleanup could run multiple times daily.

**Fix:** Use `wp_schedule_event()` return value or wrap in transient lock.

---

## Potential Performance Issues

### 15. **Database.php:401-410** - Multiple Separate Queries for Stats
**Severity:** LOW
**File:** `includes/Database/Database.php`
**Line:** 401-410

**Issue:**
```php
$result = $wpdb->get_row("SELECT COUNT(*) as total, SUM(bytes) as total_bytes FROM {$tableName}");
// ...
$stats['cached_files'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tableName} WHERE status = 'cached'");
$stats['stale_files'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tableName} WHERE status = 'stale'");
```

**Problem:** Could be combined into single query with COUNT() CASE statements.

**Impact:** Minor performance hit on large tables.

**Fix:** Use single query with conditional counts.

---

## Missing Validations

### 16. **Settings.php:177** - No Validation for get_option()
**Severity:** LOW
**File:** `includes/Admin/Settings.php`
**Line:** 177

**Issue:**
```php
$value = get_option($name); // Could return unexpected types
```

**Problem:** `get_option()` could return unexpected types if data is corrupted.

**Impact:** Type errors in renderField().

**Fix:** Add type checking based on field type before rendering.

---

### 17. **FileController.php:137-140** - Missing Type Validation
**Severity:** LOW
**File:** `includes/PDF/FileController.php`
**Line:** 137-140

**Issue:**
```php
$recordId = (int) $_GET['tpwc_pdf'];
$expires = (int) $_GET['expires'];
$action = sanitize_text_field($_GET['action']);
```

**Problem:** `$action` should be validated against allowed values ('preview', 'download').

**Impact:** Unexpected action values could cause issues.

**Fix:**
```php
$action = in_array($_GET['action'], ['preview', 'download'], true)
    ? $_GET['action']
    : 'download';
```

---

## Summary by Severity

### Critical (Fix Immediately)
- Logger returning null when type declares non-null
- Generator accessing private property incorrectly

### High Priority
- SQL injection risk in ORDER BY
- Missing error handling in directory creation

### Medium Priority
- File existence race conditions
- mPDF error handling too generic
- No CSRF protection (though HMAC provides time-based protection)

### Low Priority
- Missing null checks
- Unhandled unlink failures
- Multiple DB queries for stats
- Minor XSS risks (already sanitized but not escaped)

## Recommendations

1. **Add comprehensive error handling** throughout, especially for file operations
2. **Fix type declarations** to match actual return values
3. **Add validation layers** for all user inputs
4. **Implement database transactions** where multiple operations must succeed together
5. **Add unit tests** for critical functions (hash generation, cache logic)
6. **Consider using prepared statements** even for sanitized ORDER BY clauses
7. **Add file locks** for concurrent PDF generation
8. **Implement retry logic** for file operations that can fail temporarily
