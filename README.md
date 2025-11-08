# TPWC Hebrew PDF Generator

A production-ready WooCommerce plugin for generating Hebrew RTL PDF documents with deterministic caching, strict font control, and comprehensive admin management.

## Features

### Core Functionality
- **Two PDF Types**: Order Summary and Transaction Record (not invoices or payment receipts)
- **Hebrew-only RTL**: Strict right-to-left rendering with Rubik font (Regular/Medium/Bold)
- **Fail-Safe Font Loading**: No fallback fonts - fails loudly if Rubik is unavailable
- **Deterministic Caching**: SHA-256 hash-based caching with automatic invalidation
- **Secure Downloads**: HMAC-SHA256 signed URLs with configurable TTL (default 15 minutes)

### Admin Interface
- **Professional List Table**: Server-side pagination, search, sort, filters (type/date/status)
- **Bulk Operations**: Delete, regenerate, CSV export
- **Cache Statistics**: Real-time hit/miss rates, file sizes, and counts
- **Business Profile Settings**: Hebrew/English names, IDs, address, logo (SVG/PNG), accent color
- **Template Customization**: Margins, header/footer toggles, page numbers, column selection

### PDF Features
- **Modern Design**: Fixed layout with accent headers, zebra rows, generous spacing
- **Logo Support**: SVG preferred, PNG fallback, auto-scaling, pixel-perfect rendering
- **Long Content**: Automatic pagination with repeated table headers
- **LTR Token Isolation**: Emails, SKUs, URLs wrapped in `<bdi dir="ltr">`
- **Hebrew Formatting**: All dates/currency via PHP Intl he_IL locale

### Integration
- **REST API**: Idempotent endpoints with capability checks
- **WP-CLI**: Commands for regenerate, purge-cache, stats, generate-order
- **Action Scheduler**: Async/bulk jobs with job status tracking
- **Email Attachments**: Optional PDF attachment to WooCommerce emails
- **WooCommerce Hooks**: Auto-generate on configurable order statuses

## Requirements

- PHP ≥ 8.1
- WordPress ≥ 6.6
- WooCommerce ≥ 9.0
- PHP Extensions: ext-intl, ext-mbstring, ext-json, GD or Imagick
- Composer (for dependency management)

## Installation

### 1. Clone or Download

```bash
git clone https://github.com/daz-art/TPWC-Hebrew-PDF-Generator.git
cd TPWC-Hebrew-PDF-Generator
```

### 2. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Install Rubik Fonts

**Required font files** (place in `includes/Fonts/`):
- `Rubik-Regular.ttf`
- `Rubik-Medium.ttf`
- `Rubik-Bold.ttf`

**Download from Google Fonts:**
1. Visit https://fonts.google.com/specimen/Rubik
2. Download the font family
3. Extract and copy the TTF files to `includes/Fonts/`

**Or use wget:**
```bash
cd includes/Fonts/
wget "https://github.com/google/fonts/raw/main/ofl/rubik/Rubik%5Bwght%5D.ttf" -O Rubik-Regular.ttf
cp Rubik-Regular.ttf Rubik-Medium.ttf
cp Rubik-Regular.ttf Rubik-Bold.ttf
```

See `includes/Fonts/README.md` for detailed instructions.

### 4. Activate Plugin

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate via WordPress admin
3. The plugin will automatically:
   - Create database tables
   - Set up storage directory with deny rules
   - Initialize default settings

## Configuration

### Admin Settings

Navigate to **WooCommerce → PDF Settings** to configure:

**Business Profile:**
- Hebrew/English business names
- Registration ID and Tax ID
- Full address, phone, email, website
- Logo upload (SVG or PNG)
- Accent color for headers
- Footer/legal text

**Template Options:**
- Page margins (top, right, bottom, left in mm)
- Header/footer visibility toggles
- Page number display
- Order columns (item, SKU, qty, unit price, line total)
- Shipping display toggle

**General Settings:**
- Auto-generate on order statuses (select which statuses trigger PDF generation)
- Signed URL TTL (default 900 seconds / 15 minutes)
- Cleanup TTL (default 90 days for old PDFs)

### Storage Configuration

**Default Storage:**
- Path: `wp-content/uploads/tpwc-pdfs/`
- Access: Denied via .htaccess (Apache) and nginx.conf (Nginx)
- Direct access: Blocked - all downloads via signed URLs

**For Nginx users:**
Add the following to your Nginx configuration:
```nginx
location ~ ^/wp-content/uploads/tpwc-pdfs/ {
    deny all;
    return 403;
}
```

## Usage

### Admin Interface

**View PDFs:** Navigate to **WooCommerce → PDF Docs**

**List Table Features:**
- **Search**: By customer name or reference ID
- **Filters**: Type (Order/Transaction), Status (Cached/Stale), Date range
- **Bulk Actions**: Delete, Regenerate, Export CSV
- **Row Actions**: Preview, Download, Regenerate, Delete

**Generate from Order:**
- Go to WooCommerce → Orders
- Click the "Generate PDF" action for any order

### REST API

All endpoints require `manage_woocommerce` capability.

**Generate Order PDF:**
```http
POST /wp-json/tpwc/v1/generate/order/{order_id}
Content-Type: application/json

{
  "force": false
}
```

Response:
```json
{
  "success": true,
  "record_id": 123,
  "download_url": "https://example.com/?tpwc_pdf=123&expires=...",
  "cached": true
}
```

**Generate Transaction PDF:**
```http
POST /wp-json/tpwc/v1/generate/transaction/{transaction_id}
Content-Type: application/json

{
  "data": {
    "type": "payment",
    "amount": 100.00,
    "currency": "ILS",
    "method": "Credit Card",
    "reference": "TXN123456",
    "note": "Payment received",
    "counterparty_name": "Customer Name",
    "counterparty_email": "customer@example.com",
    "timestamp": 1234567890,
    "auth_code": "ABC123"
  },
  "force": false
}
```

**Regenerate PDF:**
```http
POST /wp-json/tpwc/v1/regenerate/{record_id}
```

**Get Download URL:**
```http
GET /wp-json/tpwc/v1/file/{record_id}?token=SIGNED_TOKEN
```

### WP-CLI Commands

**Regenerate PDFs:**
```bash
# All PDFs
wp tpwc regen

# Orders only
wp tpwc regen --type=order

# Since specific date
wp tpwc regen --since=2024-01-01

# With limit
wp tpwc regen --type=order --limit=100
```

**Purge Old PDFs:**
```bash
# Delete PDFs older than 90 days
wp tpwc purge-cache --older-than=90

# Dry run (show what would be deleted)
wp tpwc purge-cache --older-than=30 --dry-run
```

**View Statistics:**
```bash
wp tpwc stats
```

**Generate Order PDF:**
```bash
wp tpwc generate-order 123
wp tpwc generate-order 123 --force
```

**Invalidate All Cache:**
```bash
wp tpwc invalidate-all
```

## Caching System

### How It Works

The plugin uses deterministic SHA-256 hashing to cache PDFs:

**For Orders:**
```
hash(
  order_id,
  canonical_items (sorted by line_id),
  totals (subtotal, shipping, tax, total),
  customer_fields,
  business_profile_version,
  template_version,
  logo_hash
)
```

**For Transactions:**
```
hash(
  transaction_id,
  type, amount, method, reference, note,
  related_order_id,
  business_profile_version,
  template_version,
  logo_hash
)
```

### Canonicalization Rules

1. **Strings**: UTF-8 NFC normalized, trimmed
2. **Null values**: Converted to empty string
3. **Prices**: Rounded to `wc_get_price_decimals()`
4. **Arrays**: Keys sorted alphabetically
5. **Order items**: Sorted by line_id before hashing

### Cache Invalidation

**Automatic:**
- Changing Business Profile → bumps `business_profile_version`
- Changing Logo → updates `logo_hash`
- "Invalidate All" → bumps `template_version`

**Manual:**
- Individual PDF: Use "Regenerate" action
- Bulk: Select multiple PDFs → Bulk Actions → Regenerate

### Cache Hit/Miss

- **Cache Hit**: Returns existing PDF, no regeneration
- **Cache Miss**: Generates new PDF, stores with hash
- View hit/miss rates in Cache Statistics dashboard

## Email Attachments

To attach PDFs to WooCommerce emails:

1. Go to **WooCommerce → PDF Settings**
2. Under "Email Attachments", select which email types should include PDFs
3. Save settings

**Available Email Types:**
- Customer Completed Order
- Customer Invoice
- Customer Processing Order
- Customer On Hold Order
- Customer Refunded Order

PDFs are generated/cached automatically when emails are sent.

## Action Scheduler

For async and bulk operations, the plugin integrates with WooCommerce's Action Scheduler.

**Features:**
- Async PDF generation (no blocking)
- Bulk regeneration with progress tracking
- Automatic retry on failure
- Job status visible in WooCommerce → Status → Scheduled Actions

**Usage in Code:**
```php
$plugin = \TPWC\HebrewPdf\Plugin::instance();

// Schedule async order PDF
$plugin->scheduler->scheduleOrderPdf($orderId, $force);

// Schedule bulk regeneration
$plugin->scheduler->scheduleBulkRegenerate($recordIds);
```

## Security

### Access Control

- **Admin Interface**: Requires `manage_woocommerce` capability
- **REST API**: Requires `manage_woocommerce` capability
- **Signed URLs**: HMAC-SHA256 with configurable expiration
- **Order PDFs**: Customers can access their own order PDFs
- **Transaction PDFs**: Restricted to `manage_woocommerce` users

### File Storage

- **Default**: Non-public path with .htaccess/.nginx deny rules
- **No Directory Listing**: index.php prevents browsing
- **No Direct Access**: All downloads via signed URL controller
- **Automatic Cleanup**: Cron job removes old PDFs based on TTL

### Signed URLs

URLs are signed with HMAC-SHA256:
```
Signature = HMAC-SHA256(key, "recordId:expires:action")
```

- **Key**: Randomly generated on first activation, stored in database
- **Expires**: Unix timestamp (default +15 minutes)
- **Action**: "preview" (inline) or "download" (attachment)

URLs expire after TTL and cannot be reused.

## Database Schema

**Table:** `wp_tpwc_pdfs`

| Column        | Type                   | Description                      |
|---------------|------------------------|----------------------------------|
| id            | BIGINT(20) UNSIGNED    | Primary key                      |
| type          | ENUM                   | 'order' or 'transaction'         |
| ref_id        | BIGINT(20) UNSIGNED    | Order ID or Transaction ID       |
| customer_name | VARCHAR(190)           | Customer name for display        |
| amount        | DECIMAL(20,6) NULL     | Order/transaction amount         |
| created_at    | DATETIME               | PDF creation timestamp           |
| file_path     | VARCHAR(255)           | Absolute file path               |
| file_hash     | CHAR(64)               | SHA-256 hash for cache lookup    |
| bytes         | INT UNSIGNED           | File size in bytes               |
| status        | ENUM                   | 'cached' or 'stale'              |
| meta          | JSON NULL              | Additional metadata              |

**Indexes:**
- `(type, created_at)` - For filtered lists
- `(ref_id)` - For order/transaction lookups
- `(file_hash)` - For cache hits
- `(status)` - For status filtering

## Troubleshooting

### Rubik Fonts Missing

**Symptoms:**
- Error on activation: "Required Rubik font files are missing"
- PDF generation fails with font error

**Solution:**
1. Download Rubik fonts from Google Fonts
2. Place `Rubik-Regular.ttf`, `Rubik-Medium.ttf`, `Rubik-Bold.ttf` in `includes/Fonts/`
3. See `includes/Fonts/README.md` for detailed instructions

### Permission Errors

**Symptoms:**
- Cannot create storage directory
- Cannot write PDF files

**Solution:**
1. Ensure `wp-content/uploads/` is writable
2. Check file permissions: `chmod 755 wp-content/uploads/tpwc-pdfs/`
3. Check ownership: `chown www-data:www-data wp-content/uploads/tpwc-pdfs/`

### Cache Not Working

**Symptoms:**
- PDFs regenerate on every access
- Cache hit rate is 0%

**Solution:**
1. Check if order data has changed (causes new hash)
2. Verify Business Profile and Template versions haven't incremented
3. Review logs: WooCommerce → Status → Logs → tpwc

### Download Link Expired

**Symptoms:**
- "Download link has expired" error

**Solution:**
1. Increase signed URL TTL in settings
2. Generate a fresh download link
3. Note: Links expire after configured TTL (default 15 minutes)

### Hebrew Text Not Rendering

**Symptoms:**
- Hebrew characters appear as boxes or question marks
- RTL layout is incorrect

**Solution:**
1. Verify Rubik fonts are installed correctly
2. Check that Rubik fonts include Hebrew glyphs
3. Ensure UTF-8 encoding throughout

## Development

### Directory Structure

```
tpwc-hebrew-pdf/
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── includes/
│   ├── ActionScheduler/
│   │   └── Scheduler.php
│   ├── Admin/
│   │   ├── AdminMenu.php
│   │   ├── ListTable.php
│   │   └── Settings.php
│   ├── Cache/
│   │   └── CacheManager.php
│   ├── CLI/
│   │   └── Commands.php
│   ├── Database/
│   │   └── Database.php
│   ├── Email/
│   │   └── Attachments.php
│   ├── Fonts/
│   │   ├── README.md
│   │   └── [Rubik font files]
│   ├── PDF/
│   │   ├── FileController.php
│   │   └── Generator.php
│   ├── REST/
│   │   └── API.php
│   ├── Templates/
│   │   ├── OrderTemplate.php
│   │   └── TransactionTemplate.php
│   ├── Logger.php
│   ├── Plugin.php
│   ├── class-activator.php
│   └── class-deactivator.php
├── languages/
├── vendor/
├── .gitignore
├── composer.json
├── README.md
└── tpwc-hebrew-pdf.php
```

### PSR-12 Coding Standards

This plugin follows PSR-12 coding standards:
- Strict types enabled
- Type hints on all parameters and return values
- Proper namespacing (PSR-4 autoloading)
- DocBlocks on all methods

### Logging

All errors logged via WC_Logger to channel `tpwc`:

**View logs:**
WooCommerce → Status → Logs → Select "tpwc"

**Log levels:**
- `error`: Generation failures, file errors
- `warning`: Missing fonts, permission issues
- `info`: Successful operations, cache invalidations
- `debug`: Cache hits/misses, detailed operations (only in WP_DEBUG mode)

## License

GPL v3 or later. See LICENSE file for details.

Rubik font is licensed under SIL Open Font License 1.1.

## Credits

- **mPDF**: PDF generation library (https://github.com/mpdf/mpdf)
- **Rubik Font**: Google Fonts (https://fonts.google.com/specimen/Rubik)
- **WooCommerce**: E-commerce platform

## Support

For issues, questions, or contributions:
- GitHub: https://github.com/daz-art/TPWC-Hebrew-PDF-Generator
- Issues: https://github.com/daz-art/TPWC-Hebrew-PDF-Generator/issues

## Acceptance Criteria

✅ Admin list is paginated, searchable, and shows recent PDFs
✅ Logo appears in header on both PDFs and updates after logo change
✅ Order Summary contains products, totals, and both business and customer details
✅ Transaction renders for non-payment types with fixed layout
✅ Hebrew RTL text renders correctly for long strings
✅ Mixed LTR tokens (emails, SKUs, URLs) are isolated
✅ VAT on/off totals are accurate
✅ Orders with 50+ lines paginate with repeated headers
✅ Identical inputs hit the cache deterministically
✅ Signed URLs expire as configured
✅ Generation fails loudly if Rubik is missing
✅ REST and CLI behave idempotently with proper exit codes
