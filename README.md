# NoPayn Checkout for ocStore 3 / OpenCart 3

Accept payments via NoPayn in ocStore 3.x and OpenCart 3.x stores.

## Architecture

Version `2.x` uses a multi-extension setup similar to Revolut:

- `NoPayn - Global Settings`
- `NoPayn - Card Payments`
- `NoPayn - Apple Pay / Google Pay`
- `NoPayn - Vipps MobilePay`
- `NoPayn - Swish`

The admin keeps the `NoPayn - ...` naming, while the customer-facing checkout labels are method-first:

- `Card Payments`
- `Apple Pay & Google Pay`
- `Vipps MobilePay`
- `Swish`

If only one wallet is enabled globally, the wallet checkout label automatically becomes `Apple Pay` or `Google Pay`.

## Supported Payment Flows

- Card payments
- Apple Pay
- Google Pay
- Vipps / MobilePay
- Swish

The `Apple Pay / Google Pay` checkout module sends both methods in the NoPayn `transactions` array and prefers the returned `order_url` so the hosted NoPayn page can offer both wallet methods in one flow.

## Requirements

- ocStore 3.0.x or OpenCart 3.0.3.x
- PHP 7.4 or later
- A NoPayn merchant account

## Release Assets

Each tagged release publishes an installer-ready `.ocmod.zip` asset named like `nopayn-opencart3-vX.Y.Z.ocmod.zip`.

Do not use GitHub's auto-generated `Source code (zip)` or `Source code (tar.gz)` downloads for store installation. Those archives contain the repository layout, not the installer layout that the OpenCart 3 extension installer expects.

## Installation

### Method A: Upload via Admin Panel

1. Download the `.ocmod.zip` asset from the latest [Release](https://github.com/NoPayn/nopayn-opencart3/releases).
2. In admin, go to `Extensions -> Installer`.
3. Upload the `.ocmod.zip` file.
4. Go to `Extensions -> Extensions -> Payments`.
5. Install `NoPayn - Global Settings`.
6. Open `NoPayn - Global Settings` and configure:
   - API key
   - completed, pending, and cancelled order statuses
   - available NoPayn methods your merchant account is approved for
   - optional card manual capture
   - optional debug logging
7. Install and configure the checkout modules you want to expose:
   - `NoPayn - Card Payments`
   - `NoPayn - Apple Pay / Google Pay`
   - `NoPayn - Vipps MobilePay`
   - `NoPayn - Swish`
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

The customer chooses one checkout label such as `Card Payments` or `Apple Pay & Google Pay`. After confirming, the extension creates a NoPayn hosted payment order and redirects the customer to the secure NoPayn page.

## Global vs Per-Method Settings

`NoPayn - Global Settings` stores shared configuration once:

- API key
- order status mapping
- debug logging
- method availability in your NoPayn merchant account
- card manual capture

Each checkout module stores its own storefront behavior:

- enabled / disabled
- geo zone
- sort order

## Upgrade Guide from v1.0.0

Version `1.0.0` exposed one storefront method called `NoPayn Checkout`.

Version `2.0.0` changes that to one shared admin settings module plus separate checkout-facing payment extensions.

When upgrading:

1. Upload the new package.
2. In `Extensions -> Extensions -> Payments`, your existing `NoPayn Checkout` entry becomes `NoPayn - Global Settings`.
3. Review and save the global settings.
4. Install and enable the new checkout modules you want customers to see.
5. Stop using the old single-method storefront flow.

## Build the Installer Package Locally

```bash
python3 scripts/build_ocmod.py
```

This creates `dist/nopayn-opencart3-vX.Y.Z.ocmod.zip`.

## Release Process

Pushing a `v*` tag triggers the GitHub Actions workflow in `.github/workflows/package-release.yml`, which builds the OC3-compatible `.ocmod.zip` package and attaches it to the GitHub release automatically.

## License

MIT
