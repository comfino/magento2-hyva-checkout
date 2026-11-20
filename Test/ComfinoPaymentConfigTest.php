<?php

/**
 * ComfinoPay Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\Test
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 by ComfinoPay sp. z o.o.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\Test;

use Comfino\ComfinoGateway\Model\Ui\ConfigProvider;
use Comfino\ComfinoGatewayHyvaCheckout\Model\Ui\ComfinoPaymentConfig;
use PHPUnit\Framework\TestCase;

final class ComfinoPaymentConfigTest extends TestCase
{
    public function testBuildsTheProviderConfigOnlyOnceAndReturnsTheComfinoSection(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->expects($this->once())->method('getConfig')->willReturn([
            'payment' => [ConfigProvider::CODE => ['isActive' => true], 'other' => ['x' => 1]],
        ]);

        $config = new ComfinoPaymentConfig($configProvider);

        $this->assertSame(['isActive' => true], $config->get());
        $this->assertSame(['isActive' => true], $config->get());
        $this->assertSame(['isActive' => true], $config->get());
    }

    public function testRemembersAMissingSectionWithoutAskingTheProviderAgain(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->expects($this->once())->method('getConfig')->willReturn(['payment' => []]);

        $config = new ComfinoPaymentConfig($configProvider);

        $this->assertNull($config->get());
        $this->assertNull($config->get());
    }

    public function testNonArraySectionIsTreatedAsMissing(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getConfig')->willReturn(['payment' => [ConfigProvider::CODE => 'broken']]);

        $this->assertNull((new ComfinoPaymentConfig($configProvider))->get());
    }
}
