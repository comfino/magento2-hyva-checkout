<?php

/**
 * Comfino Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\ViewModel
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 Comfino by Comperia.pl S.A.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\ViewModel;

use Comfino\ComfinoGateway\Model\Ui\ConfigProvider;
use Comfino\Frontend\FrontendHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Resolves the URL of the shared, CDN-served comfino-checkout.css (loader spinner visual + show/hide gate).
 *
 * The derivation lives in the php-sdk (FrontendHelper::getCheckoutLoaderStylesheetUrl), so every dependent shop module
 * resolves the stylesheet the same way; this view model only supplies the SDK script URL from the base module's config.
 */
class LoaderStyles implements ArgumentInterface
{
    public function __construct(private readonly ConfigProvider $configProvider)
    {
    }

    /** Absolute CDN URL of comfino-checkout.css, or null if the SDK script URL is unavailable/malformed. */
    public function getLoaderCssUrl(): ?string
    {
        $sdkScriptUrl = $this->configProvider->getConfig()['payment'][ConfigProvider::CODE]['sdkScriptUrl'] ?? null;

        if (!is_string($sdkScriptUrl)) {
            return null;
        }

        return FrontendHelper::getCheckoutLoaderStylesheetUrl($sdkScriptUrl);
    }
}
