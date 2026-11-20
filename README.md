<a href="https://developers.comfino.pl">
  <img src="assets/comfinopay_logo.svg" alt="ComfinoPay" width="220">
</a>

# ComfinoPay Payment Gateway — Hyvä Checkout Module

[![Latest Version](https://img.shields.io/badge/release-1.1.0-blue.svg)](https://github.com/comfino/magento2-hyva-checkout/releases)
[![PHP Version](https://img.shields.io/packagist/dependency-v/comfino/magento2-hyva-checkout/php.svg)](https://packagist.org/packages/comfino/magento2-hyva-checkout)
[![Build Status](https://github.com/comfino/magento2-hyva-checkout/actions/workflows/tests.yml/badge.svg)](https://github.com/comfino/magento2-hyva-checkout/actions/workflows/tests.yml)
[![Software License](https://img.shields.io/badge/license-OSL--3.0-orange.svg)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/comfino/magento2-hyva-checkout.svg)](https://packagist.org/packages/comfino/magento2-hyva-checkout)
[![API Documentation](https://img.shields.io/badge/API-documentation-5a9e33)](https://developers.comfino.pl)

Hyvä Checkout compatibility module for the [ComfinoPay Payment Gateway for Magento 2](https://github.com/comfino/magento2) — adds `PlaceOrderService` and an Alpine.js paywall integration so [ComfinoPay](https://comfino.pl) works correctly in the [Hyvä Checkout](https://hyva.io/hyva-checkout.html) flow.

## Requirements

- **Magento**: 2.4.4 or higher
- **PHP**: 8.1 or higher
- **Hyvä Checkout**: 1.0.5 or higher (`^1.0.5`)
- **ComfinoPay Payment Gateway**: `comfino/magento2` ^4.1

## Installation

### Via Composer (recommended)

> **Note:** `hyva-themes/magento2-hyva-checkout` is distributed via the private Hyvä repository.
> Add `{"type":"composer","url":"https://hyva.io/packages/"}` to your project's `repositories`
> before running `composer require`.

Installing both the base gateway and the Hyvä Checkout compatibility module together:

```bash
composer require comfino/magento2 comfino/magento2-hyva-checkout
bin/magento module:enable Comfino_ComfinoGateway Comfino_SdkForMagento2 Comfino_ComfinoGatewayHyvaCheckout
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

If the base ComfinoPay module is already installed, just add this one:

```bash
composer require comfino/magento2-hyva-checkout
bin/magento module:enable Comfino_ComfinoGatewayHyvaCheckout
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

## Development

### Local setup

```bash
# Install dependencies
./bin/composer install

# Run tests
./bin/phpunit

# Run with coverage
./bin/phpunit --coverage-html coverage

# Check code style (MEQP2 + PSR-12)
./bin/composer cs

# Auto-fix style violations
./bin/composer cs-fix

# Run PHPStan static analysis (level 5)
./bin/phpstan analyse
```

Tests run without a Magento installation — `Test/bootstrap.php` provides Hyvä Checkout stubs.

## Support

- **E-mail:** kontakt@comfino.pl
- **API Documentation:** [developers.comfino.pl](https://developers.comfino.pl)
- **Issues:** [GitHub Issues](https://github.com/comfino/magento2-hyva-checkout/issues)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

## License

[Open Software License (OSL 3.0)](LICENSE)
