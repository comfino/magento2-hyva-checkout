<?php

/**
 * ComfinoPay Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\Tests
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 by ComfinoPay sp. z o.o.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\Tests;

use Comfino\Api\Serializer\Json as JsonSerializer;
use Comfino\ComfinoGateway\Model\Ui\ConfigProvider;
use Comfino\ComfinoGatewayHyvaCheckout\Model\Ui\ComfinoPaymentConfig;
use Comfino\ComfinoGatewayHyvaCheckout\ViewModel\PaywallBootstrapConfig;
use JsonException;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

final class PaywallBootstrapConfigTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testTrackingConsentIsFalseWhenMissingFromProviderConfig(): void
    {
        $paywall = $this->getPaywallData([]);

        $this->assertArrayHasKey('trackingConsent', $paywall);
        $this->assertFalse($paywall['trackingConsent']);
    }

    /**
     * @throws JsonException
     */
    public function testTrackingConsentIsFalseWhenDisabled(): void
    {
        $this->assertFalse($this->getPaywallData(['trackingConsent' => false])['trackingConsent']);
    }

    /**
     * @throws JsonException
     */
    public function testTrackingConsentIsTrueWhenEnabled(): void
    {
        $this->assertTrue($this->getPaywallData(['trackingConsent' => true])['trackingConsent']);
    }

    /**
     * @throws JsonException
     */
    public function testFlagsAreOmittedWhenShopHasNone(): void
    {
        $paywall = $this->getPaywallData([]);

        $this->assertArrayNotHasKey('flags', $paywall);
        $this->assertArrayNotHasKey('flagAttributes', $paywall);
    }

    /**
     * @throws JsonException
     */
    public function testFlagsAndAttributesAreForwarded(): void
    {
        $attributes = ['PROMO' => ['text' => ['pl' => 'Raty 0%'], 'amount' => 2.5]];

        $paywall = $this->getPaywallData(
            [],
            ['flags' => ['PROMO', 'PAYMENT_ITEM_PROMOTION'], 'flagAttributes' => $attributes]
        );

        $this->assertSame(['PROMO', 'PAYMENT_ITEM_PROMOTION'], $paywall['flags']);
        $this->assertSame($attributes, $paywall['flagAttributes']);
    }

    public function testHostileFlagValuesStayInsideTheJsonString(): void
    {
        $text = '</script><!--"\'&';

        $json = $this->getBootstrapJson(
            [],
            ['flags' => ['PROMO'], 'flagAttributes' => ['PROMO' => ['text' => $text]]]
        );

        $this->assertSame(
            $text,
            json_decode($json, true, 512, JSON_THROW_ON_ERROR)['paywall']['flagAttributes']['PROMO']['text']
        );
    }

    /**
     * @param array<string, mixed> $providerFields
     * @param array{flags: string[]|null, flagAttributes: array<string, mixed>|null} $checkoutFlags
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function getPaywallData(array $providerFields, array $checkoutFlags = ['flags' => null, 'flagAttributes' => null]): array
    {
        return json_decode(
            $this->getBootstrapJson($providerFields, $checkoutFlags),
            true,
            512,
            JSON_THROW_ON_ERROR
        )['paywall'];
    }

    /**
     * @param array<string, mixed> $providerFields
     * @param array{flags: string[]|null, flagAttributes: array<string, mixed>|null} $checkoutFlags
     */
    private function getBootstrapJson(array $providerFields, array $checkoutFlags = ['flags' => null, 'flagAttributes' => null]): string
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getConfig')->willReturn([
            'payment' => [
                ConfigProvider::CODE => array_merge(
                    [
                        'isActive' => true,
                        'authToken' => 'token',
                        'loanAmount' => 10000,
                        'environment' => 'sandbox',
                        'sdkScriptUrl' => 'https://widget.craty.pl/comfino-sdk.min.js',
                        'allowedProductTypes' => null,
                    ],
                    $providerFields
                ),
            ],
        ]);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturn('https://shop.example/comfino_hyva/checkout/saveloanparams');

        $viewModel = new class (new ComfinoPaymentConfig($configProvider), $url, new JsonSerializer(), $checkoutFlags) extends PaywallBootstrapConfig {
            /** @param array{flags: string[]|null, flagAttributes: array<string, mixed>|null} $checkoutFlags */
            public function __construct(
                ComfinoPaymentConfig $paymentConfig,
                UrlInterface $url,
                JsonSerializer $serializer,
                private readonly array $checkoutFlags
            ) {
                parent::__construct($paymentConfig, $url, $serializer);
            }

            protected function getCheckoutFlags(): array
            {
                return $this->checkoutFlags;
            }
        };

        $json = $viewModel->getBootstrapConfigJson();

        $this->assertNotNull($json);

        return $json;
    }
}
