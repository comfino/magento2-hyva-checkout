<?php

/**
 * Comfino Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Magento\Checkout\Model\Session
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 Comfino by Comperia.pl S.A.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Magento\Checkout\Model\Session;

use Magento\Checkout\Model\Session;

/**
 * PHPStan stub for the DI-generated proxy. Declares magic setters that Magento's Session exposes
 * via __call so PHPStan can resolve them without a full Magento installation.
 */
class Proxy extends Session
{
    public function setLastQuoteId(mixed $value): static { return $this; }
    public function setLastSuccessQuoteId(mixed $value): static { return $this; }
    public function setLastOrderId(mixed $value): static { return $this; }
    public function setLastRealOrderId(mixed $value): static { return $this; }
    public function setLastOrderStatus(mixed $value): static { return $this; }
}