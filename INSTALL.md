# Installation Guide

## Quick Start

### 1. Download or Clone

```bash
git clone https://github.com/daz-art/TPWC-Hebrew-PDF-Generator.git
cd TPWC-Hebrew-PDF-Generator
```

### 2. Install Composer Dependencies

**IMPORTANT:** This plugin requires mPDF and its dependencies via Composer.

```bash
composer install --no-dev --optimize-autoloader
```

If you don't have Composer installed, download it from: https://getcomposer.org/

### 3. Install Rubik Fonts

The plugin **requires** Rubik fonts for Hebrew text rendering. Without these fonts, PDF generation will fail.

**Option 1: Download from Google Fonts**
1. Visit https://fonts.google.com/specimen/Rubik
2. Click "Download family"
3. Extract the ZIP file
4. Copy these files to `includes/Fonts/`:
   - `Rubik-Regular.ttf`
   - `Rubik-Medium.ttf`
   - `Rubik-Bold.ttf`

**Option 2: Use wget**
```bash
cd includes/Fonts/
wget "https://github.com/google/fonts/raw/main/ofl/rubik/Rubik%5Bwght%5D.ttf" -O Rubik-Regular.ttf
cp Rubik-Regular.ttf Rubik-Medium.ttf
cp Rubik-Regular.ttf Rubik-Bold.ttf
cd ../..
```

### 4. Upload to WordPress

1. Zip the entire plugin folder (including `vendor/` directory created by Composer)
2. Upload via WordPress admin: Plugins → Add New → Upload Plugin
3. **OR** copy the folder to `/wp-content/plugins/` via FTP/SSH

### 5. Activate

1. Go to Plugins in WordPress admin
2. Find "TPWC Hebrew PDF Generator"
3. Click "Activate"

The plugin will automatically:
- Create database tables
- Set up storage directory with deny rules
- Initialize default settings

### 6. Configure

1. Go to **WooCommerce → PDF Settings**
2. Fill in your Business Profile:
   - Business names (Hebrew and English)
   - Registration and Tax IDs
   - Contact information
   - Upload your logo (SVG or PNG)
   - Set accent color
3. Save settings

## Verification

### Check Font Installation

After activation, try generating a test PDF:
1. Go to WooCommerce → Orders
2. Click "Generate PDF" on any order
3. If fonts are missing, you'll see: "Required Rubik font files are missing"

### Check Storage Permissions

The plugin needs write access to `wp-content/uploads/`:
```bash
chmod 755 wp-content/uploads/
chmod 755 wp-content/uploads/tpwc-pdfs/
```

### Check PHP Extensions

Verify required extensions are installed:
```bash
php -m | grep -E 'intl|mbstring|json|gd|imagick'
```

Should show:
- intl
- mbstring
- json
- gd (or imagick)

## Post-Installation

### Configure Auto-Generation

1. Go to **WooCommerce → PDF Settings**
2. Under "General Settings", select which order statuses should trigger PDF generation
3. Default: "Completed" orders only

### Set Up Email Attachments (Optional)

To attach PDFs to customer emails:
1. Go to **WooCommerce → PDF Settings**
2. Scroll to "Email Attachments"
3. Select which email types should include PDFs
4. Save settings

### For Nginx Users

Add this to your Nginx configuration to block direct PDF access:

```nginx
location ~ ^/wp-content/uploads/tpwc-pdfs/ {
    deny all;
    return 403;
}
```

Reload Nginx:
```bash
sudo nginx -t && sudo nginx -s reload
```

## Troubleshooting

### "Composer autoload not found"

You forgot to run `composer install`. Run it from the plugin directory:
```bash
cd wp-content/plugins/tpwc-hebrew-pdf
composer install --no-dev --optimize-autoloader
```

### "Required Rubik font files are missing"

Download Rubik fonts and place them in `includes/Fonts/`. See step 3 above.

### "Failed to generate PDF"

Check the logs:
1. Go to WooCommerce → Status → Logs
2. Select log file starting with "tpwc"
3. Review error messages

Common issues:
- Missing fonts
- Insufficient storage permissions
- PHP memory limit too low (increase to 256M+)

### Can't Upload Plugin ZIP

The ZIP might be too large (due to vendor/ directory). Instead:
1. Upload via FTP/SSH to `/wp-content/plugins/`
2. OR increase WordPress upload limit in php.ini:
   ```
   upload_max_filesize = 50M
   post_max_size = 50M
   ```

## System Requirements

- PHP ≥ 8.1
- WordPress ≥ 6.6
- WooCommerce ≥ 9.0
- Composer (for installation only)
- ext-intl, ext-mbstring, ext-json
- ext-gd or ext-imagick
- 256MB+ PHP memory limit recommended

## Support

If you encounter issues:
1. Check logs: WooCommerce → Status → Logs → tpwc
2. Review this installation guide
3. Check README.md for detailed documentation
4. Open an issue: https://github.com/daz-art/TPWC-Hebrew-PDF-Generator/issues
