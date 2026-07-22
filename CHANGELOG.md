# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-22

### Added
- Initial release of Hyvä Checkout compatibility module for Comfino Payment Gateway.
- `PlaceOrderService` implementation for Hyvä Checkout payment flow.
- Comfino paywall integration as an Alpine.js component, compatible with Hyvä Checkout's
  CSP-strict environment.
- Paywall initializes at checkout page load — the Comfino payment-method logo appears in the
  payment list as soon as the method is available, before the shopper selects Comfino.
- Automatic cart-total synchronization: paywall reloads with the updated loan amount whenever
  the cart changes during checkout.
- Payment-method label and logo rendered by the Comfino SDK for consistent branding.
- Docker setup for local development and testing.
- Comprehensive documentation and configuration examples.
