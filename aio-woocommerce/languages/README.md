# Translation Files for AIO WooCommerce

This directory contains translation files for the AIO WooCommerce plugin.

## Current Translations

- **Persian (fa_IR)**: `aio-woocommerce-fa_IR.po` and `aio-woocommerce-fa_IR.mo`

## Compiling Translation Files

WordPress requires `.mo` files (compiled binary format) in addition to `.po` files (source text format).

### Method 1: Using WP-CLI (Recommended)

If you have WP-CLI installed, you can use:

```bash
wp i18n make-mo languages/
```

### Method 2: Using Poedit

1. Download and install [Poedit](https://poedit.net/)
2. Open `aio-woocommerce-fa_IR.po` in Poedit
3. Click "Save" - Poedit will automatically generate the `.mo` file

### Method 3: Using msgfmt (Linux/Mac)

```bash
msgfmt -o aio-woocommerce-fa_IR.mo aio-woocommerce-fa_IR.po
```

### Method 4: Online Tools

You can use online PO to MO converters like:
- https://po2mo.net/
- https://www.easytranslation.io/po-to-mo-converter

## Adding New Translations

1. Extract translatable strings using WP-CLI:
   ```bash
   wp i18n make-pot . languages/aio-woocommerce.pot
   ```

2. Update existing `.po` files or create new ones for other languages

3. Compile to `.mo` format using one of the methods above

## Language File Naming

Translation files should follow WordPress naming convention:
- `aio-woocommerce-{locale}.po` (source file)
- `aio-woocommerce-{locale}.mo` (compiled file)

Where `{locale}` is the language code (e.g., `fa_IR` for Persian, `en_US` for English).

## Notes

- The plugin automatically loads translations based on WordPress site language
- If a translation doesn't exist, English strings will be displayed
- Always keep `.po` files (source) and `.mo` files (compiled) in sync

