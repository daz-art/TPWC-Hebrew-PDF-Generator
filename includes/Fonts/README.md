# Rubik Font Files

This plugin requires the Rubik font family for Hebrew RTL text rendering in PDFs.

## Required Font Files

Place the following Rubik font files in this directory:

- `Rubik-Regular.ttf` - Rubik Regular (Weight 400)
- `Rubik-Medium.ttf` - Rubik Medium (Weight 500)
- `Rubik-Bold.ttf` - Rubik Bold (Weight 700)

## How to Obtain Rubik Fonts

### Option 1: Google Fonts (Recommended)

1. Visit https://fonts.google.com/specimen/Rubik
2. Download the font family
3. Extract the ZIP file
4. Copy the following files from the `static` folder:
   - `Rubik-Regular.ttf` (or `Rubik[wght].ttf` variable font)
   - `Rubik-Medium.ttf`
   - `Rubik-Bold.ttf`
5. Place them in this directory (`includes/Fonts/`)

### Option 2: Direct Download

```bash
# From the plugin root directory
cd includes/Fonts/

# Download Rubik variable font (contains all weights)
wget "https://github.com/google/fonts/raw/main/ofl/rubik/Rubik%5Bwght%5D.ttf" -O Rubik-Regular.ttf

# Copy for different weights (or use separate static files)
cp Rubik-Regular.ttf Rubik-Medium.ttf
cp Rubik-Regular.ttf Rubik-Bold.ttf
```

### Option 3: Package Manager

```bash
# Using npm
npm install @fontsource/rubik

# Copy TTF files from node_modules/@fontsource/rubik/files/ to includes/Fonts/
```

## License

Rubik is licensed under the SIL Open Font License 1.1
https://github.com/googlefonts/rubik/blob/main/OFL.txt

## Font Verification

The plugin will verify that font files exist on activation. If fonts are missing, PDF generation will fail with a clear error message. There is NO fallback to other fonts to ensure consistent Hebrew RTL rendering.

## Font Subsetting

The plugin uses mPDF's font subsetting feature to embed only the characters actually used in each PDF, reducing file sizes significantly.
