# Zenai Search for PrestaShop

This module adds an AI search mode to your storefront search.

Tested with PrestaShop 9.0.

## Features

- API token configuration in module settings
- Search mode selector in search bar:
  - `AI Mode`
  - `Search` (classic PrestaShop)
- Product export CSV (`product_id`, `title`, `description`, `category`, `price`)

## Setup

1. Download files from this repository and move them to a folder named `zenaisearch`.
2. Create a zip archive with that folder.
1. Install and activate module `zenaisearch` in Back Office.
2. Open module configuration.
3. Add your API token from https://zenaisoftware.com/dashboard/api.
4. Save settings.

## Usage

1. Go to storefront.
2. In search bar, select `AI Mode`.
3. Search for a term.
4. Results are ordered by Zenai recommendations.

Switch to `Search` to use standard PrestaShop search behavior.

## Optional Export

In module configuration click `Export CSV`, then import the file in:
https://zenaisoftware.com/dashboard/csv-upload
