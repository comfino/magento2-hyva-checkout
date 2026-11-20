# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-11-20

### Added
- **Paywall track ID relay** (`Controller\Checkout\SaveLoanParams`). The loan-selection request now also stores the
  paywall track ID sent by the web SDK on the quote payment, so the ComfinoPay order is created with the same track ID
  as the paywall events and its promotion flags and discount match what the shopper saw. A track ID that is missing or
  malformed (not 1-128 characters of letters, digits, `_`, `.`, `:` or `-`) is ignored and the loan selection is still
  saved. Base module releases that do not read the stored value ignore it.
- **Promotion hint data in the paywall bootstrap config** (`ViewModel\PaywallBootstrapConfig`). The config now carries
  `flags` and `flagAttributes` (the shop's remote feature flags and their parameters, as stored by the base module or
  cached from the ComfinoPay API), so the checkout paywall can show the promotion hint like the Luma checkout and the
  Hyvä Theme template do. Both keys are left out when the shop has no flags.
- `trackingConsent` (boolean) in the paywall bootstrap config, taken from the base module's paywall activity tracking
  consent setting (requires the `comfino/magento2` release that adds it; absent there, it is sent as `false`).

### Changed
- **Requires `comfino/magento2` ^4.1** (was ^4.0). The flags, `trackingConsent` and `trackId` come from that release.
- **Released versions only:** `composer.json` declares `minimum-stability: stable` (was `beta` with `prefer-stable`), as the base module does, so installs no longer
  accept beta releases of the base module and the SDK libraries.
- The checkout config of the base module is built once per request and shared by the paywall bootstrap, the loader stylesheet
  and the tile loader, instead of up to four times per checkout render.

### Security
- **Loan selection relay hardened** (`Controller\Checkout\SaveLoanParams`). Severity: medium. Affects 1.0.0. The endpoint
  now accepts only an `application/json` request (a form-encoded one gets HTTP 415, so a cross-origin page can no longer
  send it as a simple request), stores a `loanType` only when it is one of the types the merchant offers in the paywall
  (HTTP 400 otherwise, also when the offers cannot be loaded), and drops a `loanTerm` that is not an integer from 1 to
  120.
- **The paywall amount can no longer be set from the browser** (Magewire component). Severity: low. The component
  discards a client-supplied `loanAmount` and recomputes it from the quote.
- **Checkout error text and redirect target checked** (`Model\Checkout\PlaceOrderService`). Severity: low. The error text
  is stripped of markup, shortened to 300 characters and no longer used as a translation source string; the payment
  redirect must be an `https://` address with a host (plain `http://` only when `COMFINO_DEV_ENV=TRUE`).

### Upgrade notes
- Update the base module to 4.1.0 first (`composer update comfino/magento2 comfino/magento2-hyva-checkout`), then run
  `bin/magento setup:di:compile` and `bin/magento cache:flush`.

## [1.0.0] - 2026-07-22

### Added
- Initial release of Hyvä Checkout compatibility module for ComfinoPay Payment Gateway.
- `PlaceOrderService` implementation for Hyvä Checkout payment flow.
- ComfinoPay paywall integration as an Alpine.js component, compatible with Hyvä Checkout's
  CSP-strict environment.
- Paywall initializes at checkout page load — the ComfinoPay payment-method logo appears in the
  payment list as soon as the method is available, before the shopper selects ComfinoPay.
- Automatic cart-total synchronization: paywall reloads with the updated loan amount whenever
  the cart changes during checkout.
- Payment-method label and logo rendered by the ComfinoPay SDK for consistent branding.
- Docker setup for local development and testing.
- Comprehensive documentation and configuration examples.
