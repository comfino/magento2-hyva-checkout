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

use Comfino\Api\Serializer\Json as JsonSerializer;
use Comfino\ComfinoGateway\Model\Configuration\SettingsManager;
use Comfino\ComfinoGateway\Model\Ui\ConfigProvider;
use Comfino\ComfinoGatewayHyvaCheckout\Model\Ui\ComfinoPaymentConfig;
use Comfino\Frontend\FrontendHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Builds the `bootstrapPaywall()` config JSON for the page-load bootstrap in Hyvä Checkout.
 *
 * The data comes from the base module's `ConfigProvider` (the source of Luma's `window.checkoutConfig`), so new base fields
 * flow through without a release here. This class only maps `paymentMethodAuth` to `paymentMethodItem.auth`, wraps the SDK
 * URL, adds `checkoutSessionUrl`, `platform`, `loanAmountInputId`, the load-error message and the promotion flags.
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

    private readonly JsonSerializer $serializer;

    public function __construct(
        private readonly ComfinoPaymentConfig $paymentConfig,
        private readonly UrlInterface $url,
        ?JsonSerializer $serializer = null
    ) {
        // Null default: Magento's code generators reject `new` in parameter defaults.
        $this->serializer = $serializer ?? new JsonSerializer();
    }

    /**
     * URL of the CDN checkout glue bundle (`comfino-magento-hyva.min.js`) loaded by `comfino-register.phtml`, on the
     * SDK's origin (allow-listed in the base module's `csp_whitelist.xml`). Null when the SDK URL is missing or malformed.
     */
    public function getCheckoutScriptUrl(): ?string
    {
        $sdkScriptUrl = $this->paymentConfig->get()['sdkScriptUrl'] ?? null;

        if (!is_string($sdkScriptUrl) || $sdkScriptUrl === '') {
            return null;
        }

        return FrontendHelper::getCheckoutScriptUrl($sdkScriptUrl, self::CHECKOUT_PLATFORM);
    }

    /**
     * Serialized config, or null when the paywall must not be shown (method inactive or every product type filtered out).
     */
    public function getBootstrapConfigJson(): ?string
    {
        $comfino = $this->paymentConfig->get();

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
            // Merchant opt-in for the SDK paywall activity tracker; always emitted as a boolean.
            'trackingConsent' => ($comfino['trackingConsent'] ?? false) === true,
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

        // Same source as the Luma checkout and the Hyvä Theme template (stored flag names, else the cached ones).
        $checkoutFlags = $this->getCheckoutFlags();

        if (!empty($checkoutFlags['flags'])) {
            $paywallData['flags'] = $checkoutFlags['flags'];
        }

        if (!empty($checkoutFlags['flagAttributes'])) {
            $paywallData['flagAttributes'] = $checkoutFlags['flagAttributes'];
        }

        if ($allowedProductTypes !== null) {
            $paywallData['productTypes'] = $allowedProductTypes;
        }

        if (!empty($comfino['directRedirect'])) {
            $paywallData['directRedirect'] = true;
        }

        return $this->serializer->serialize([
            'sdk' => ['url' => $comfino['sdkScriptUrl'] ?? ''],
            'errorMessage' => (string) __('Failed to load the ComfinoPay payment widget. Please refresh the page.'),
            'paywall' => $paywallData,
        ]);
    }

    /**
     * Remote flag names and their attributes for the current store, both null when the shop has none.
     *
     * @return array{flags: string[]|null, flagAttributes: array<string, mixed>|null}
     */
    protected function getCheckoutFlags(): array
    {
        return SettingsManager::getCheckoutFlags();
    }
}
