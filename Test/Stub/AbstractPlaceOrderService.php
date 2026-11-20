<?php

/**
 * ComfinoPay Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Hyva\Checkout\Model\Magewire\Payment
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 by ComfinoPay sp. z o.o.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Hyva\Checkout\Model\Magewire\Payment;

use Magento\Quote\Api\CartManagementInterface;

abstract class AbstractPlaceOrderService
{
    protected CartManagementInterface $cartManagement;

    public function __construct(CartManagementInterface $cartManagement)
    {
        $this->cartManagement = $cartManagement;
    }
}
