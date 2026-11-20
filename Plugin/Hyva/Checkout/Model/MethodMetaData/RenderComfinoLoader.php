<?php

/**
 * ComfinoPay Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\Plugin\Hyva\Checkout\Model\MethodMetaData
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 by ComfinoPay sp. z o.o.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\Plugin\Hyva\Checkout\Model\MethodMetaData;

use Comfino\ComfinoGateway\Model\Configuration\ConfigManager;
use Comfino\ComfinoGateway\Model\Ui\ConfigProvider;
use Comfino\ComfinoGatewayHyvaCheckout\Model\Ui\ComfinoPaymentConfig;
use Hyva\Checkout\Model\MethodMetaData;

/**
 * Renders the ComfinoPay logo placeholder with a loader spinner into the tile's icon slot, so it is never empty before
 * the web SDK paints the item. Without it the tile shows the bare title until the SDK injects a logo.
 *
 * The placeholder `<img>` carries `data-comfino-logo`, so the SDK adopts the node and swaps its `src` instead of injecting
 * a second image. The spinner styles are in the CDN `comfino-checkout.css`; on SDK failure the registrar hides the tile
 * (see `comfino-hyva-checkout.css`). Rendered only when the paywall can be shown for the cart.
 *
 * Plugs `MethodMetaData`, not the newer `PaymentMethodMetaData` (deprecated since Hyvä Checkout 1.2.0, absent in 1.1.x,
 * which this module supports); the latter extends it, so the plugin still applies on 1.2.0+.
 */
class RenderComfinoLoader
{
    /** Static, data-free spinner markup styled by comfino-checkout.css. Spans only, to stay valid inline. */
    private const LOADER_HTML =
        '<span class="comfino-payment-method-item__loader" aria-hidden="true">' .
        '<span class="comfino-payment-method-item__loader-spinner"><span></span></span>' .
        '</span>';

    private ?bool $showable = null;

    public function __construct(private readonly ComfinoPaymentConfig $paymentConfig)
    {
    }

    /** Force the icon slot to render for ComfinoPay so renderIcon() can emit the placeholder and loader. */
    public function afterCanRenderIcon(MethodMetaData $subject, bool $result): bool
    {
        return $this->isComfinoLoaderApplicable($subject) ? true : $result;
    }

    /** Inject the placeholder logo + loader for the ComfinoPay method; pass everything else through untouched. */
    public function afterRenderIcon(MethodMetaData $subject, string $result): string
    {
        return $this->isComfinoLoaderApplicable($subject) ? $this->buildIconHtml() : $result;
    }

    private function buildIconHtml(): string
    {
        $logoUrl = htmlspecialchars(ConfigManager::getDefaultLogoUrl(), ENT_QUOTES, 'UTF-8');

        return '<img class="comfino-payment-method-item__logo" data-comfino-logo src="' . $logoUrl . '" alt="ComfinoPay" />' . self::LOADER_HTML;
    }

    private function isComfinoLoaderApplicable(MethodMetaData $subject): bool
    {
        $method = $subject->getMethod();

        if (!method_exists($method, 'getCode') || $method->getCode() !== ConfigProvider::CODE) {
            return false;
        }

        return $this->isPaywallShowable();
    }

    /** Mirrors the gate in PaywallBootstrapConfig: active method with at least one allowed product type. */
    private function isPaywallShowable(): bool
    {
        if ($this->showable !== null) {
            return $this->showable;
        }

        $comfino = $this->paymentConfig->get();

        $this->showable = is_array($comfino) && ($comfino['isActive'] ?? false) === true && ($comfino['allowedProductTypes'] ?? null) !== [];

        return $this->showable;
    }
}
