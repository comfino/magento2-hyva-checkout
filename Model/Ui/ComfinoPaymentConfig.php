<?php

/**
 * ComfinoPay Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\Model\Ui
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 by ComfinoPay sp. z o.o.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\Model\Ui;

use Comfino\ComfinoGateway\Model\Ui\ConfigProvider;

/**
 * The ComfinoPay part of the base module's checkout config, built once per request.
 *
 * `ConfigProvider::getConfig()` is expensive (product type and creditor lookups, cart build) and three classes of this module
 * need it on every checkout render. Magento shares one instance per request, so the first caller pays and the rest reuse it.
 */
class ComfinoPaymentConfig
{
    private ConfigProvider $configProvider;
    private ?array $config = null;
    private bool $loaded = false;

    public function __construct(ConfigProvider $configProvider)
    {
        $this->configProvider = $configProvider;
    }

    /**
     * @return array<string, mixed>|null Null when the provider returns no ComfinoPay section
     */
    public function get(): ?array
    {
        if (!$this->loaded) {
            $config = $this->configProvider->getConfig()['payment'][ConfigProvider::CODE] ?? null;
            $this->config = is_array($config) ? $config : null;
            $this->loaded = true;
        }

        return $this->config;
    }
}
