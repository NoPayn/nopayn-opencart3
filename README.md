# Cost+ Checkout for ocStore 3 / OpenCart 3

Accept payments via Cost+ in ocStore 3.x and OpenCart 3.x stores.

The module is branded as `Cost+` in the OpenCart admin, while the underlying integration continues to use the existing NoPayn API base URL and internal setting keys for compatibility.

## Architecture

Version `3.x` uses a multi-extension setup similar to Revolut:

- `Cost+ - Global Settings`
- `Cost+ - Card Payments`
- `Cost+ - Apple Pay`
- `Cost+ - Google Pay`
- `Cost+ - Vipps MobilePay`
- `Cost+ - Swish`

The admin keeps the `Cost+ - ...` naming, while the customer-facing checkout labels are method-first:

- `Card Payments`
- `Apple Pay`
- `Google Pay`
- `Vipps MobilePay`
- `Swish`

## Supported Payment Flows

- Card payments
- Apple Pay
- Google Pay
- Vipps / MobilePay
- Swish

Each checkout module creates a Cost+ order for exactly one payment method so the storefront checkout options stay aligned with the Order API redirect flow.

## Requirements

- ocStore 3.0.x or OpenCart 3.0.3.x
- PHP 7.4 or later
- A Cost+ merchant account

## Release Assets

Each tagged release publishes an installer-ready `.ocmod.zip` asset named like `nopayn-opencart3-vX.Y.Z.ocmod.zip`.

Do not use GitHub's auto-generated `Source code (zip)` or `Source code (tar.gz)` downloads for store installation. Those archives contain the repository layout, not the installer layout that the OpenCart 3 extension installer expects.

## Installation

### Method A: Upload via Admin Panel

1. Download the `.ocmod.zip` asset from the latest [Release](https://github.com/NoPayn/nopayn-opencart3/releases).
2. In admin, go to `Extensions -> Installer`.
3. Upload the `.ocmod.zip` file.
4. Go to `Extensions -> Extensions -> Payments`.
5. Install `Cost+ - Global Settings`.
6. Open `Cost+ - Global Settings` and configure:
   - API key
   - completed, pending, and cancelled order statuses
   - available Cost+ methods your merchant account is approved for
   - optional card manual capture
   - optional debug logging
7. Install and configure the checkout modules you want to expose:
   - `Cost+ - Card Payments`
   - `Cost+ - Apple Pay`
   - `Cost+ - Google Pay`
   - `Cost+ - Vipps MobilePay`
   - `Cost+ - Swish`
8. For each checkout module, set:
   - status
   - geo zone
   - sort order

### Method B: Manual Upload

1. Download or clone this repository.
2. Copy the contents of `upload/` into your store root.
3. Go to `Extensions -> Extensions -> Payments`.
4. Follow the same install order as Method A.

## Checkout Behavior

Each checkout module is a separate OpenCart payment extension. This is the most reliable way to get multiple radio options in OpenCart 3 and Simple Checkout.

The customer chooses one checkout label such as `Card Payments`, `Apple Pay`, or `Google Pay`. After confirming, the extension creates a Cost+ hosted payment order for that single method and redirects the customer to the secure payment page.

Apple Pay has an additional platform restriction from the current Cost+ documentation: it cannot be tested in test mode and requires a live project plus a real Apple device with Apple Wallet.

## Global vs Per-Method Settings

`Cost+ - Global Settings` stores shared configuration once:

- API key
- order status mapping
- debug logging
- method availability in your Cost+ merchant account
- card manual capture

Each checkout module stores its own storefront behavior:

- enabled / disabled
- geo zone
- sort order

## Upgrade Guide from v2.0.0

Version `2.0.0` exposed a combined wallet module called `NoPayn - Apple Pay / Google Pay`.

Version `3.0.0` replaced that combined wallet module with separate Apple Pay and Google Pay payment extensions.

Version `3.0.1` keeps the same functionality but rebrands the admin-facing module names from `NoPayn` to `Cost+` and adds the Cost+ logo to the payment extension list.

When upgrading:

1. Upload the new package.
2. In `Extensions -> Extensions -> Payments`, keep using `Cost+ - Global Settings` for the shared API key and status mapping.
3. Uninstall and disable `NoPayn - Apple Pay / Google Pay` if you are upgrading from the old combined wallet version.
4. Install and enable `Cost+ - Apple Pay` and `Cost+ - Google Pay`.
5. Review the global settings and confirm the Apple Pay and Google Pay availability switches are enabled only for methods approved on your merchant account.
6. After uploading `v3.0.1`, the payment extension list should show the Cost+ logo in the second column for each Cost+ payment module.
7. If the old combined wallet entry still appears after upgrading, remove these legacy files from the store because the OpenCart 3 installer does not delete removed files automatically:
   - `admin/controller/extension/payment/nopayn_wallets.php`
   - `admin/language/en-gb/extension/payment/nopayn_wallets.php`
   - `catalog/controller/extension/payment/nopayn_wallets.php`
   - `catalog/language/en-gb/extension/payment/nopayn_wallets.php`
   - `catalog/model/extension/payment/nopayn_wallets.php`

## Build the Installer Package Locally

```bash
python3 scripts/build_ocmod.py
```

This creates `dist/nopayn-opencart3-vX.Y.Z.ocmod.zip`.

## Release Process

Pushing a `v*` tag triggers the GitHub Actions workflow in `.github/workflows/package-release.yml`, which builds the OC3-compatible `.ocmod.zip` package and attaches it to the GitHub release automatically.

## License

MIT
