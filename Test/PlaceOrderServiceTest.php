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

use Comfino\ComfinoGateway\Api\ApplicationServiceInterface;
use Comfino\ComfinoGatewayHyvaCheckout\Model\Checkout\PlaceOrderService;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::expectExceptionMessage('ComfinoPay payment failed: no redirect URL returned.');

        $this->placeOrderService->getRedirectUrl($quote, 123);
    }

    public function testGetRedirectUrlThrowsExceptionWhenEmptyResult(): void
    {
        $this->applicationService->expects(self::once())
            ->method('save')
            ->willReturn([]);

        $quote = $this->createMock(Quote::class);

        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('ComfinoPay payment failed: no redirect URL returned.');

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
        self::expectExceptionMessage('ComfinoPay payment initialization failed.');

        $this->placeOrderService->getRedirectUrl($quote, 123);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unsafeRedirectUrlProvider(): array
    {
        return [
            'plain http' => ['http://comfino.pl/application/abc123'],
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme' => ['data:text/html,<script>alert(1)</script>'],
            'protocol relative' => ['//evil.example/pay'],
            'relative path' => ['/checkout/onepage/success'],
            'no host' => ['https:///path'],
            'userinfo' => ['https://comfino.pl@evil.example/pay'],
            'not a string' => [['https://comfino.pl/application/abc123']],
            'empty' => [''],
        ];
    }

    #[DataProvider('unsafeRedirectUrlProvider')]
    public function testGetRedirectUrlRejectsAnUnsafeRedirectTarget(mixed $redirectUrl): void
    {
        $this->applicationService->expects(self::once())
            ->method('save')
            ->willReturn([['redirectUrl' => $redirectUrl]]);

        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('ComfinoPay payment failed: no redirect URL returned.');

        $this->placeOrderService->getRedirectUrl($this->createMock(Quote::class), 123);
    }

    public function testGetRedirectUrlAllowsPlainHttpOnlyInADevelopmentEnvironment(): void
    {
        $redirectUrl = 'http://comfino.test/application/abc123';
        $this->applicationService->expects(self::once())
            ->method('save')
            ->willReturn([['redirectUrl' => $redirectUrl]]);

        putenv('COMFINO_DEV_ENV=TRUE');

        try {
            self::assertSame($redirectUrl, $this->placeOrderService->getRedirectUrl($this->createMock(Quote::class), 123));
        } finally {
            putenv('COMFINO_DEV_ENV');
        }
    }

    public function testErrorTextIsStrippedOfMarkupAndPlaceholdersAreNotExpanded(): void
    {
        $this->applicationService->expects(self::once())
            ->method('save')
            ->willReturn([['error' => '<a href="https://evil.example">Call 555-0100</a> now %1 %2']]);

        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('Call 555-0100 now %1 %2');

        $this->placeOrderService->getRedirectUrl($this->createMock(Quote::class), 123);
    }

    public function testLongErrorTextIsShortened(): void
    {
        $this->applicationService->expects(self::once())
            ->method('save')
            ->willReturn([['error' => str_repeat('a', 5000)]]);

        try {
            $this->placeOrderService->getRedirectUrl($this->createMock(Quote::class), 123);
            self::fail('Expected a LocalizedException.');
        } catch (LocalizedException $e) {
            self::assertSame(300, mb_strlen($e->getMessage()));
            self::assertStringEndsWith('…', $e->getMessage());
        }
    }

    public function testNonStringErrorGivesTheFixedFallback(): void
    {
        $this->applicationService->expects(self::once())
            ->method('save')
            ->willReturn([['error' => ['unexpected']]]);

        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('ComfinoPay payment initialization failed.');

        $this->placeOrderService->getRedirectUrl($this->createMock(Quote::class), 123);
    }
}
