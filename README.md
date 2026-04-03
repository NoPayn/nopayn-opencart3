# NoPayn Checkout for ocStore 3 / OpenCart 3

Accept payments via [NoPayn](https://nopayn.co.uk) in ocStore 3.x and OpenCart 3.x stores.

## Supported Payment Methods

- Credit / Debit Card
- Apple Pay
- Google Pay
- Vipps / MobilePay

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
5. Find `NoPayn Checkout`, click `Install`, then `Edit`.

### Method B: Manual Upload

1. Download or clone this repository.
2. Copy the contents of `upload/` into your store root.
3. Go to `Extensions -> Extensions -> Payments`.
4. Find `NoPayn Checkout`, click `Install`, then `Edit`.

## Build the Installer Package Locally

```bash
python3 scripts/build_ocmod.py
```

This creates `dist/nopayn-opencart3-vX.Y.Z.ocmod.zip`.

## Configuration

1. Enter your NoPayn API key.
2. Enable the payment methods you have been approved for.
3. Set completed, pending, and cancelled order statuses.
4. Optionally restrict the method by geo zone.
5. Enable the extension and save.

At checkout, the customer selects `NoPayn Checkout` as the payment extension and then chooses the enabled NoPayn payment method inside the confirmation panel.

## How It Works

This extension uses the NoPayn hosted payment page flow:

1. The customer selects `NoPayn Checkout`.
2. On confirm, the extension creates a NoPayn order through the API.
3. The customer is redirected to NoPayn's secure hosted payment page.
4. The store receives the customer return callback and a server-to-server webhook.
5. The order history is updated based on the final NoPayn payment status.

## Release Process

Pushing a `v*` tag triggers the GitHub Actions workflow in `.github/workflows/package-release.yml`, which builds the OC3-compatible `.ocmod.zip` package and attaches it to the GitHub release automatically.

## License

MIT
