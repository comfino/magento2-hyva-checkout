<?php

/**
 * Comfino Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\Tests
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 Comfino by Comperia.pl S.A.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\Tests;

use Comfino\ComfinoGateway\Api\ApplicationServiceInterface;
use Comfino\ComfinoGatewayHyvaCheckout\Model\Checkout\PlaceOrderService;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PlaceOrderServiceTest extends TestCase
{
    private MockObject $cartManagement;
    private MockObject $applicationService;
    private MockObject $checkoutSession;
    private MockObject $orderRepository;
    private PlaceOrderService $placeOrderService;

    protected function setUp(): void
    {
        $this->cartManagement = $this->createMock(CartManagementInterface::class);
        $this->applicationService = $this->createMock(ApplicationServiceInterface::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);

        // getRedirectUrl() seeds the checkout session from the placed order before calling save().
        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000001');
        $this->orderRepository->method('get')->willReturn($order);

        $this->placeOrderService = new PlaceOrderService(
            $this->cartManagement,
            $this->applicationService,
            $this->checkoutSession,
            $this->orderRepository
        );
    }

    public function testCanPlaceOrderReturnsTrue(): void
    {
        self::assertTrue($this->placeOrderService->canPlaceOrder());
    }

    public function testCanRedirectReturnsTrue(): void
    {
        self::assertTrue($this->placeOrderService->canRedirect());
    }

    public function testGetRedirectUrlReturnsValidUrl(): void
    {
        $redirectUrl = 'https://comfino.pl/application/abc123';
        $this->applicationService->expects(self::once())
            ->method('save')
            ->willReturn([['redirectUrl' => $redirectUrl]]);

        $quote = $this->createMock(Quote::class);
        $result = $this->placeOrderService->getRedirectUrl($quote, 123);

        self::assertSame($redirectUrl, $result);
    }

    public function testGetRedirectUrlThrowsExceptionWhenNoRedirectUrl(): void
    {
        $this->applicationService->expects(self::once())
            ->method('save')
            ->willReturn([[]]);

        $quote = $this->createMock(Quote::class);

        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('Comfino payment failed: no redirect URL returned.');

        $this->placeOrderService->getRedirectUrl($quote, 123);
    }

    public function testGetRedirectUrlThrowsExceptionWhenEmptyResult(): void
    {
        $this->applicationService->expects(self::once())
            ->method('save')
            ->willReturn([]);

        $quote = $this->createMock(Quote::class);

        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('Comfino payment failed: no redirect URL returned.');

        $this->placeOrderService->getRedirectUrl($quote);
    }

    public function testGetRedirectUrlThrowsExceptionWhenApiReturnsError(): void
    {
        $this->applicationService->expects(self::once())
            ->method('save')
            ->willReturn([['redirectUrl' => 'https://example.com/checkout/onepage/failure', 'error' => 'Validation failed.']]);

        $quote = $this->createMock(Quote::class);

        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('Validation failed.');

        $this->placeOrderService->getRedirectUrl($quote, 123);
    }

    public function testGetRedirectUrlThrowsUsableMessageWhenApiReturnsEmptyError(): void
    {
        /* save() can route to the failure page with an empty error string; the message must not be blank, otherwise
           Hyvä renders an invisible error dialog and the customer sees nothing happen. */
        $this->applicationService->expects(self::once())
            ->method('save')
            ->willReturn([['redirectUrl' => 'https://example.com/checkout/onepage/failure', 'error' => '']]);

        $quote = $this->createMock(Quote::class);

        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('Comfino payment initialization failed.');

        $this->placeOrderService->getRedirectUrl($quote, 123);
    }
}
