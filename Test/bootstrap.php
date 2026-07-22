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

if (!class_exists(\Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService::class)) {
    require __DIR__ . '/Stub/AbstractPlaceOrderService.php';
}

if (!class_exists(\Magento\Checkout\Model\Session\Proxy::class)) {
    require __DIR__ . '/Stub/CheckoutSessionProxy.php';
}

/* When the real hyva-themes/magento2-hyva-checkout 1.3.x package is installed (resolved from the Hyvä artifact repo in
   dev/CI), AbstractPlaceOrderService::__construct() lazily resolves a DefaultOrderData via ObjectManager when no
   orderData is passed. Our subclass intentionally never passes one (the ?AbstractOrderData ctor arg is 1.3-only and
   must not be used — see CLAUDE.md), so unit tests need a minimal ObjectManager that can instantiate the dependency-
   free DefaultOrderData. Harmless under the 1.1.x stub, which doesn't touch ObjectManager. */
if (interface_exists(\Magento\Framework\ObjectManagerInterface::class)) {
    \Magento\Framework\App\ObjectManager::setInstance(
        new class implements \Magento\Framework\ObjectManagerInterface {
            public function create($type, array $arguments = [])
            {
                return new $type();
            }

            public function get($type)
            {
                return new $type();
            }

            public function configure(array $configuration)
            {
            }
        }
    );
}