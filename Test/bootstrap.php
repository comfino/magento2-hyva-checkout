<?php

/**
 * ComfinoPay Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 by ComfinoPay sp. z o.o.
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

/* With the real Hyvä Checkout 1.3.x package, `AbstractPlaceOrderService::__construct()` resolves a `DefaultOrderData`
   through the ObjectManager when none is passed (our subclass never passes one, as the argument is 1.3-only). A minimal
   ObjectManager covers that; it is harmless under the 1.1.x stub. */
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
