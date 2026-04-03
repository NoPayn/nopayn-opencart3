# NoPayn Checkout for OpenCart 4

Accept payments via [NoPayn/Cost+](https://costplus.io) in your OpenCart 4 store.

## Supported Payment Methods

- **Credit / Debit Card** — Visa, Mastercard, Amex, Maestro, V Pay, Bancontact, Diners, Discover
- **Apple Pay**
- **Google Pay**
- **Vipps / MobilePay**

## Requirements

- OpenCart 4.0.0.0 or later
- PHP 8.0 or later
- A NoPayn merchant account ([sign up](https://manage.nopayn.io/))

## Release Assets

Each tagged release publishes an installer-ready `.ocmod.zip` asset named like `nopayn-opencart-vX.Y.Z.ocmod.zip`.

Do not use GitHub's auto-generated `Source code (zip)` or `Source code (tar.gz)` downloads for OpenCart installation. Those archives contain the repository layout, not the installer layout that OpenCart expects.

## Installation

### Method A: Upload via Admin Panel

1. Download the `.ocmod.zip` asset attached to the latest [Release](https://github.com/NoPayn/nopayn-opencart/releases)
2. In your OpenCart admin, go to **Extensions → Installer**
3. Upload the `.ocmod.zip` file
4. Go to **Extensions → Extensions → Payment**
5. Find **NoPayn Checkout** and click **Install**, then **Edit**

### Method B: Manual Upload

1. Download or clone this repository
2. Copy the contents of `upload/` into your OpenCart root directory
3. Go to **Extensions → Extensions → Payment**
4. Find **NoPayn Checkout** and click **Install**, then **Edit**

## Build the Installer Package Locally

Run:

```bash
python3 scripts/build_ocmod.py
```

This creates an installer-ready package in `dist/`, for example `dist/nopayn-opencart-vX.Y.Z.ocmod.zip`.

## Release Process

Pushing a `v*` tag triggers the GitHub Actions workflow in `.github/workflows/package-release.yml`, which builds the `.ocmod.zip` package and attaches it to the GitHub release automatically.

## Configuration

1. Enter your **API Key** (found in the [NoPayn merchant portal](https://manage.nopayn.io/) under Settings → API Key)
2. Enable the **payment methods** you have been approved for
3. Set your preferred **order statuses** for completed, pending, and cancelled payments
4. Optionally restrict by **Geo Zone**
5. Set **Status** to enabled
6. Save

## How It Works

This extension uses the NoPayn **Hosted Payment Page (HPP)** flow:

1. Customer selects a NoPayn payment method at checkout
2. On "Confirm Order", the extension creates an order via the NoPayn API
3. Customer is redirected to the NoPayn secure payment page
4. After payment, customer returns to your store with order status updated
5. A webhook ensures the order is updated even if the customer doesn't return

No card data touches your server — fully PCI DSS compliant.

## License

MIT
