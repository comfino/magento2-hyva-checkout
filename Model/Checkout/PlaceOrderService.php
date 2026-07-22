<?php

/**
 * Comfino Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\Model\Checkout
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 Comfino by Comperia.pl S.A.
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
 * Handles Comfino order placement in Hyvä Checkout.
 *
 * Registered in PlaceOrderServiceProvider (etc/frontend/di.xml).
 *
 * Hyvä Checkout calls getRedirectUrl() after the
 * Magento order has been placed via CartManagementInterface (handled by the base class). At that point the payment
 * additional_information already contains loanType and loanTerm.
 *
 * Unlike Luma — whose Knockout renderer submits these as payment additional_data, triggering DataAssignObserver —
 * Hyvä Checkout's Magewire payment flow only persists the payment method code and never collects per-method
 * additional_data. The values are instead written onto the session quote's payment additional_information by the
 * Controller\Checkout\SaveLoanParams relay endpoint, which the Comfino web SDK POSTs to (config.checkoutSessionUrl)
 * on every offer selection. They are copied onto the order payment at placeOrder() time.
 *
 * ApplicationService::save() reads the placed order via CheckoutSession::getLastRealOrder(), creates the Comfino
 * financing application, and returns its redirect URL.
 *
 * IMPORTANT — checkout session seeding: ApplicationService::save() (shared with the main module) reads
 * CheckoutSession::getLastRealOrder(), i.e., the order identified by the session key `last_real_order_id`. In Luma
 * that key is set by the order-placement controller before the Comfino REST call runs. Hyvä Checkout places the
 * order via CartManagementInterface::placeOrder() and never sets it, so without seeding it here, save() would read a
 * stale order or an empty one — producing a wrong/empty redirect URL or a blank error. getRedirectUrl() therefore seeds
 * the session from the just-placed $orderId before calling save().
 */
class PlaceOrderService extends AbstractPlaceOrderService
{
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
     * Called by Hyvä Checkout after the Magento order has been placed.
     * Returns the Comfino application URL to redirect the customer to.
     *
     * @throws LocalizedException When the Comfino API call fails or returns no redirect URL
     */
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        /* Point CheckoutSession::getLastRealOrder() at the order Hyvä just placed, so ApplicationService::save()
           builds the Comfino application from THIS order rather than a stale/empty session order. */
        $this->seedCheckoutSessionOrder($quote, $orderId);

        /* ApplicationService::save() owns its own failure handling — it never throws, always returning an array with a
           redirectUrl and (on failure) an error. Cart restoration / order cancellation happen inside save(). */
        $result = $this->applicationService->save();

        $redirectUrl = $result[0]['redirectUrl'] ?? null;
        $error = $result[0]['error'] ?? null;

        /* A non-null error means save() reported a failure. Surface a usable message even when the API returned
           an empty error string (otherwise Hyvä shows a blank, invisible error dialog). */
        if ($error !== null) {
            $message = is_string($error) && $error !== '' ? $error : 'Comfino payment initialization failed.';

            throw new LocalizedException(__($message));
        }

        if (empty($redirectUrl)) {
            throw new LocalizedException(__('Comfino payment failed: no redirect URL returned.'));
        }

        return $redirectUrl;
    }

    /**
     * Seed the checkout session's "last real order" keys from the just-placed order so the main module's
     * ApplicationService::save() (which reads CheckoutSession::getLastRealOrder()), operates on the correct order.
     * Mirrors what Luma's order-placement controller sets implicitly. No-op when the order cannot be resolved — save()
     * then falls back to its existing session state and reports its own error.
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
