<?php

/**
 * ComfinoPay Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\Model\Checkout
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 by ComfinoPay sp. z o.o.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\Model\Checkout;

use Comfino\ComfinoGateway\Api\ApplicationServiceInterface;
use Exception;
use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Order placement for ComfinoPay in Hyvä Checkout, registered in `PlaceOrderServiceProvider` (`etc/frontend/di.xml`).
 *
 * Hyvä calls `getRedirectUrl()` after placing the order. The loan type and term were stored on the quote payment by
 * `Controller\Checkout\SaveLoanParams`, because Magewire collects no per-method `additional_data`.
 *
 * `ApplicationService::save()` reads `CheckoutSession::getLastRealOrder()`, which Luma's controller sets and Hyvä never
 * does, so the session is seeded from the placed order first; otherwise `save()` works on a stale or empty order.
 */
class PlaceOrderService extends AbstractPlaceOrderService
{
    /** Longest error text shown to the shopper, in characters. */
    private const MAX_ERROR_MESSAGE_LENGTH = 300;

    private ApplicationServiceInterface $applicationService;
    private CheckoutSession $checkoutSession;
    private OrderRepositoryInterface $orderRepository;

    public function __construct(
        CartManagementInterface $cartManagement,
        ApplicationServiceInterface $applicationService,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($cartManagement);

        $this->applicationService = $applicationService;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
    }

    public function canPlaceOrder(): bool
    {
        return true;
    }

    public function canRedirect(): bool
    {
        return true;
    }

    /**
     * Returns the ComfinoPay application URL the customer is redirected to.
     *
     * @throws LocalizedException When the application cannot be created or the redirect URL is unusable
     */
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        // `save()` must build the application from this order, not a stale or empty session one.
        $this->seedCheckoutSessionOrder($quote, $orderId);

        // `save()` never throws: it returns `redirectUrl` and, on failure, `error`, and restores the cart or cancels the order itself.
        $result = $this->applicationService->save();

        $redirectUrl = $result[0]['redirectUrl'] ?? null;
        $error = $result[0]['error'] ?? null;

        // An empty error still needs a message, or Hyvä shows a blank error dialog.
        if ($error !== null) {
            throw new LocalizedException(__('%1', self::getShopperMessage($error)));
        }

        if (!is_string($redirectUrl) || !self::isSafeRedirectUrl($redirectUrl)) {
            throw new LocalizedException(__('ComfinoPay payment failed: no redirect URL returned.'));
        }

        return $redirectUrl;
    }

    /**
     * Shopper-facing text for an error from `ApplicationService::save()`.
     *
     * The base module (4.1+) composes it shop-side, but it is still treated as data: markup stripped, length capped,
     * and passed as an argument of `__('%1', …)`, because a dynamic `__()` source string is looked up as a translation
     * key and has its `%1` read as a placeholder. An empty or non-string error gives a fixed fallback.
     */
    private static function getShopperMessage(mixed $error): string
    {
        $message = is_string($error) ? trim(strip_tags($error)) : '';

        if ($message === '') {
            return (string) __('ComfinoPay payment initialization failed.');
        }

        return mb_strlen($message) > self::MAX_ERROR_MESSAGE_LENGTH
            ? mb_substr($message, 0, self::MAX_ERROR_MESSAGE_LENGTH - 1) . '…'
            : $message;
    }

    /**
     * Second check after the base module's: https with a host, plain http only when `COMFINO_DEV_ENV=TRUE`.
     */
    private static function isSafeRedirectUrl(string $redirectUrl): bool
    {
        // phpcs:ignore MEQP1.Security.DiscouragedFunction -- plain URL syntax check, no I/O
        $parts = parse_url($redirectUrl);

        if ($parts === false || ($parts['host'] ?? '') === '' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        // phpcs:ignore MEQP1.Security.DiscouragedFunction -- the same server-set switch the base module reads
        return $scheme === 'https' || ($scheme === 'http' && getenv('COMFINO_DEV_ENV') === 'TRUE');
    }

    /**
     * Points `CheckoutSession::getLastRealOrder()` at the placed order, as Luma's controller does. No-op when the order
     * cannot be loaded; `save()` then reports its own error.
     */
    private function seedCheckoutSessionOrder(Quote $quote, ?int $orderId): void
    {
        if ($orderId === null) {
            return;
        }

        try {
            /** @var OrderInterface $order */
            $order = $this->orderRepository->get($orderId);
        } catch (Exception $e) {
            return;
        }

        $this->checkoutSession->setLastQuoteId($quote->getId());
        $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
        $this->checkoutSession->setLastOrderId($order->getEntityId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus($order->getStatus());
    }
}
