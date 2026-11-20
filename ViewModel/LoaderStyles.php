<?php

/**
 * ComfinoPay Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\ViewModel
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 by ComfinoPay sp. z o.o.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\ViewModel;

use Comfino\ComfinoGatewayHyvaCheckout\Model\Ui\ComfinoPaymentConfig;
use Comfino\Frontend\FrontendHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * URL of the CDN-served `comfino-checkout.css` (loader spinner and show/hide gate), derived from the SDK script URL by
 * `FrontendHelper::getCheckoutLoaderStylesheetUrl()` in the php-sdk.
 */
class LoaderStyles implements ArgumentInterface
{
    public function __construct(private readonly ComfinoPaymentConfig $paymentConfig)
    {
    }

    /** Absolute CDN URL of comfino-checkout.css, or null if the SDK script URL is unavailable/malformed. */
    public function getLoaderCssUrl(): ?string
    {
        $sdkScriptUrl = $this->paymentConfig->get()['sdkScriptUrl'] ?? null;

        if (!is_string($sdkScriptUrl)) {
            return null;
        }

        return FrontendHelper::getCheckoutLoaderStylesheetUrl($sdkScriptUrl);
    }
}
