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
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Builds the JSON the Comfino web SDK's bootstrapPaywall() consumes, for page-load bootstrap in Hyvä Checkout.
 *
 * The paywall config data is NOT reassembled here field-by-field — it is taken from the base module's canonical
 * checkout config provider (Comfino\ComfinoGateway\Model\Ui\ConfigProvider, the same source that feeds Luma's
 * window.checkoutConfig.payment.comfino), so any field the base module adds flows through without a Hyvä-module
 * release. This view model only:
 *   - Maps the provider's flat paymentMethodAuth to the SDK's paymentMethodItem.auth shape.
 *   - Wraps the SDK script fields into the sdk object the registrar's loader expects.
 *   - Adds the Hyvä-only server-relay endpoint (checkoutSessionUrl) and the static platform / loanAmountInputId.
 *   - Attaches the localized load-error message.
 *
 * @see ConfigProvider::getConfig
 */
class PaywallBootstrapConfig implements ArgumentInterface
{
    /** Hidden input the SDK reads the server-authoritative cart total from (rendered by comfino.phtml). */
    private const LOAN_AMOUNT_INPUT_ID = 'comfino-loan-amount';

    /** Server relay that persists { loanType, loanTerm } onto the quote payment for Hyvä's Magewire flow. */
    private const CHECKOUT_SESSION_ROUTE = 'comfino_hyva/checkout/saveloanparams';

    /** Platform slug used in the CDN checkout-glue bundle filename: comfino-<platform>.min.js. */
    private const CHECKOUT_PLATFORM = 'magento-hyva';

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly UrlInterface $url,
        private readonly Json $serializer
    ) {
    }

    /**
     * URL of the CDN-served checkout glue bundle (comfino-magento-hyva.min.js) — the Alpine registrar/SDK bootstrapper
     * that comfino-register.phtml loads via <script src>. Derived from the same origin as the SDK script (so it tracks
     * production / sandbox / dev with the SDK), it lives on the Comfino CDN whose host is already allow-listed for
     * script-src by the base module's csp_whitelist.xml. Returns null when the SDK URL is unavailable/malformed, in
     * which case the registrar must not be emitted.
     */
    public function getCheckoutScriptUrl(): ?string
    {
        $sdkScriptUrl = $this->configProvider->getConfig()['payment'][ConfigProvider::CODE]['sdkScriptUrl'] ?? null;

        if (!is_string($sdkScriptUrl) || $sdkScriptUrl === '') {
            return null;
        }

        return FrontendHelper::getCheckoutScriptUrl($sdkScriptUrl, self::CHECKOUT_PLATFORM);
    }

    /**
     * Serialized bootstrapPaywall() config, or null when the paywall must not be shown (no quote, method inactive, or
     * every product type filtered out for this cart — mirrors comfino.phtml's hide gate).
     */
    public function getBootstrapConfigJson(): ?string
    {
        $comfino = $this->configProvider->getConfig()['payment'][ConfigProvider::CODE] ?? null;

        if (!is_array($comfino) || ($comfino['isActive'] ?? false) !== true) {
            return null;
        }

        // allowedProductTypes is null when unrestricted, an empty array when everything is filtered out, or a subset.
        $allowedProductTypes = $comfino['allowedProductTypes'] ?? null;

        if ($allowedProductTypes === []) {
            return null;
        }

        $paywallData = [
            'authToken' => $comfino['authToken'] ?? '',
            'loggingToken' => $comfino['loggingToken'] ?? '',
            'trackId' => $comfino['trackId'] ?? '',
            'loanAmount' => (int) ($comfino['loanAmount'] ?? 0),
            'environment' => $comfino['environment'] ?? '',
            'platform' => 'magento',
            'loanAmountInputId' => self::LOAN_AMOUNT_INPUT_ID,
            'paywallSettings' => $comfino['paywallSettings'] ?? [],
            /* Merchant-configurable payment method label (COMFINO_PAYMENT_TEXT); omitted when empty, so the SDK uses
               its own default. auth is always present (empty string when unavailable). */
            'paymentMethodItem' => array_filter(
                [
                    'auth' => $comfino['paymentMethodAuth'] ?? '',
                    'label' => $comfino['paymentMethodLabel'] ?? null,
                ],
                static fn ($value): bool => $value !== null
            ),
            'checkoutSessionUrl' => $this->url->getUrl(self::CHECKOUT_SESSION_ROUTE),
        ];

        // Copy through optional collections only when present and non-empty, preserving the provider's key order.
        $paywallData += array_filter(
            [
                'productTypeNames' => $comfino['productTypeNames'] ?? null,
                'cart' => $comfino['cart'] ?? null,
                'shopEnvironment' => $comfino['shopEnvironment'] ?? null,
                'creditors' => $comfino['creditors'] ?? null,
                'allowedProductsConfig' => $comfino['allowedProductsConfig'] ?? null,
            ],
            static fn ($value): bool => $value !== null && $value !== []
        );

        if ($allowedProductTypes !== null) {
            $paywallData['productTypes'] = $allowedProductTypes;
        }

        if (!empty($comfino['directRedirect'])) {
            $paywallData['directRedirect'] = true;
        }

        return $this->serializer->serialize([
            'sdk' => [
                'url' => $comfino['sdkScriptUrl'] ?? '',
            ],
            'errorMessage' => (string) __('Failed to load the Comfino payment widget. Please refresh the page.'),
            'paywall' => $paywallData,
        ]);
    }
}
