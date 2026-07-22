<?php

/**
 * Comfino Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\Plugin\Hyva\Checkout\Model\MethodMetaData
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 Comfino by Comperia.pl S.A.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\Plugin\Hyva\Checkout\Model\MethodMetaData;

use Comfino\ComfinoGateway\Model\Configuration\ConfigManager;
use Comfino\ComfinoGateway\Model\Ui\ConfigProvider;
use Hyva\Checkout\Model\MethodMetaData;

/**
 * Renders the standard-rendering Comfino logo placeholder into the tile's icon slot, with a loader spinner (no text of
 * its own — just the spinner graphic) overlaid on top, so the icon slot is never empty before the web frontend SDK
 * paints the final item content. Hyvä core always renders the "Comfino" title text itself (method-list.phtml), so
 * without this plugin the tile would briefly show the bare title with no logo at all until the SDK injects one — this
 * fills that * gap with the same placeholder-then-swap logo the other shop plugins render server-side.
 *
 * The method-list.phtml outputs MethodMetaData::renderIcon() as raw HTML inside the tile, so we return the placeholder
 * and loader markup there for the Comfino method (and force canRenderIcon() true so the slot is emitted). The placeholder
 * `<img>` is marked `data-comfino-logo` so DefaultPaymentMethodItemRenderer adopts this same node and swaps its `src`
 * to the auth API logo while the tile is still hidden behind the loader — the same "adopt, don't inject a second image"
 * contract as the other shop plugins.
 *
 * MethodMetaData has been deprecated since Hyvä Checkout 1.2.0 in favor of the subject-specific PaymentMethodMetaData,
 * but that class (and AbstractMethodMetaData) does not exist in the 1.1.x line this module supports (composer ^1.0.5),
 * so referencing it would break DI there. MethodMetaData is the only icon-metadata type present across 1.1.x<->1.3.x,
 * and PaymentMethodMetaData extends it, so this plugin still applies via subclass interception on 1.2.0+.
 *
 * The spinner visual lives in the CDN-served comfino-checkout.css; it is shown by default and hidden once the SDK marks
 * the tile .comfino-payment-method-item--ready. On SDK load failure/timeout the CDN registrar (comfino-magento-hyva.min.js)
 * hides the whole tile (flags <body>.comfino-sdk-failed; see comfino-hyva-checkout.css) rather than reveal a dead method.
 * Only rendered when the paywall is showable for the cart, so an inactive or filtered-out method never shows a
 * placeholder that can't resolve.
 */
class RenderComfinoLoader
{
    /** Static, data-free spinner markup styled by comfino-checkout.css. Spans only, to stay valid inline. */
    private const LOADER_HTML =
        '<span class="comfino-payment-method-item__loader" aria-hidden="true">' .
        '<span class="comfino-payment-method-item__loader-spinner"><span></span></span>' .
        '</span>';

    private ?bool $showable = null;

    public function __construct(private readonly ConfigProvider $configProvider)
    {
    }

    /** Force the icon slot to render for Comfino so renderIcon() can emit the placeholder and loader. */
    public function afterCanRenderIcon(MethodMetaData $subject, bool $result): bool
    {
        return $this->isComfinoLoaderApplicable($subject) ? true : $result;
    }

    /** Inject the placeholder logo + loader for the Comfino method; pass everything else through untouched. */
    public function afterRenderIcon(MethodMetaData $subject, string $result): string
    {
        return $this->isComfinoLoaderApplicable($subject) ? $this->buildIconHtml() : $result;
    }

    private function buildIconHtml(): string
    {
        $logoUrl = htmlspecialchars(ConfigManager::getDefaultLogoUrl(), ENT_QUOTES, 'UTF-8');

        return '<img class="comfino-payment-method-item__logo" data-comfino-logo src="' . $logoUrl .
            '" alt="Comfino" />' . self::LOADER_HTML;
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

        $comfino = $this->configProvider->getConfig()['payment'][ConfigProvider::CODE] ?? null;

        $this->showable = is_array($comfino) &&
            ($comfino['isActive'] ?? false) === true &&
            ($comfino['allowedProductTypes'] ?? null) !== [];

        return $this->showable;
    }
}
