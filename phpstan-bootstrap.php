<?php

/**
 * Comfino Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 Comfino by Comperia.pl S.A.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

define('BP', __DIR__);

if (!class_exists(\Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService::class)) {
    require __DIR__ . '/Test/Stub/AbstractPlaceOrderService.php';
}

if (!class_exists(\Magento\Checkout\Model\Session\Proxy::class)) {
    require __DIR__ . '/Test/Stub/CheckoutSessionProxy.php';
}
