# Field Cloner for ExpressionEngine 7

A utility addon that clones fields or entire field groups with prefix/suffix affixes, batched across multiple language codes in a single operation.

**The problem:** Multilingual EE sites need duplicate fields for each language — `title_en`, `title_se`, `title_de`, etc. Creating these by hand is tedious and error-prone, especially with 10+ fields across 3-4 languages.

**The solution:** Pick a field group, enter your language codes, click Clone. Field Cloner creates a new field group per language with all fields duplicated and properly named.

## Features

- Clone from field groups or individual fields
- Suffix (`field_se`) or prefix (`se_field`) mode
- Batch multiple language codes in one run (`en, se, de, ja`)
- Creates a new field group per language code
- Assigns clones to target channels
- Supports: text, textarea, RTE, toggle, select, date, **grid** (with columns), file, URL, email, number, colorpicker, relationship, fluid field
- Skips unsupported types gracefully
- Non-destructive uninstall — cloned fields are yours to keep

## Requirements

- ExpressionEngine 7.5+
- PHP 8.1+

## Installation

1. Copy `system/user/addons/reizoko_field_cloner` to your EE addons directory
2. Install via Add-ons in the control panel

## Usage

1. Go to **Add-ons → Field Cloner**
2. Choose source: a field group or individual fields
3. Set affix position (suffix or prefix)
4. Enter language codes, comma-separated (e.g. `en, se, de`)
5. Optionally select target channels to assign clones to
6. Click **Clone Fields**

Each language code gets its own field group (e.g. "Products SE", "Products DE") with all cloned fields inside.

## License

MIT
