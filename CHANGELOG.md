# Changelog

## 2026-04-09 - v3.1.0

- Added a global `Checkout Expiry (Minutes)` setting with a default of `5`.
- The payment module now sends Cost+ an `expiration_period` when creating orders so abandoned sessions can expire automatically instead of staying open for the gateway default.
- Documented that OpenCart order status changes from pending to cancelled on expiry still depend on the Cost+ webhook reaching the store.

## 2026-04-09 - v3.0.2

- Constrained the Cost+ admin payment-list logo to a standard gateway-logo size so it no longer renders at the source image's full dimensions.

## 2026-04-09 - v3.0.1

- Rebranded the admin-facing module names from `NoPayn` to `Cost+` while keeping the existing NoPayn API integration and internal extension codes unchanged.
- Added the shared Cost+ payment logo to the OpenCart 3 payment extension list for all Cost+ modules.
- Updated the README and package metadata to reflect the Cost+ branding.

## 2026-04-09 - v3.0.0

- Replaced the combined `NoPayn - Apple Pay / Google Pay` OC3 checkout module with separate `NoPayn - Apple Pay` and `NoPayn - Google Pay` modules.
- Updated the shared payment controller to follow transaction `payment_url` redirects for single-method order flows.
- Refreshed the README with the new module layout, upgrade notes, and the Apple Pay live-mode testing restriction from the current Cost+ docs.
