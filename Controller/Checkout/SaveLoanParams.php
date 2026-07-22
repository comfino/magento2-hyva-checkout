<?php

/**
 * Comfino Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\Controller\Checkout
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 Comfino by Comperia.pl S.A.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\Controller\Checkout;

use Comfino\ComfinoGateway\Observer\DataAssignObserver;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Persists the loan parameters (loanType, loanTerm) selected in the Comfino paywall onto the active quote's
 * payment additional_information, before the order is placed.
 *
 * Hyvä Checkout's Magewire payment flow only saves the payment method *code* on the quote — it has no mechanism
 * to collect per-method additional_data (unlike Luma's Knockout renderer getData()), so the DataAssignObserver
 * never fires for it. The Comfino web SDK (MagentoAdapter) POSTs the selected params to this endpoint
 * (config.checkoutSessionUrl) on every offer selection; we write them onto the session quote so that, at
 * place-order time, ApplicationService::buildOrderDto() finds them via the order payment additional_information.
 */
class SaveLoanParams implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private RequestInterface $request;
    private JsonFactory $resultJsonFactory;
    private CheckoutSession $checkoutSession;
    private CartRepositoryInterface $cartRepository;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        try {
            $payload = $this->readPayload();
            $loanType = isset($payload['loanType']) ? trim((string) $payload['loanType']) : '';
            $loanTerm = isset($payload['loanTerm']) ? trim((string) $payload['loanTerm']) : '';

            if ($loanType === '') {
                return $result->setHttpResponseCode(400)->setData(['success' => false]);
            }

            /* The session quote is the source of truth — never trust a client-supplied identifier for which cart
               to mutate. checkoutSessionId in the payload is informational only. */
            $quote = $this->checkoutSession->getQuote();
            $payment = $quote->getPayment();

            $payment->setAdditionalInformation(DataAssignObserver::PAYMENT_LOAN_TYPE, $loanType);

            if ($loanTerm !== '') {
                $payment->setAdditionalInformation(DataAssignObserver::PAYMENT_LOAN_TERM, $loanTerm);
            }

            $this->cartRepository->save($quote);

            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Comfino: Failed to persist loan params to quote payment.', ['exception' => $e]);

            return $result->setHttpResponseCode(500)->setData(['success' => false]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readPayload(): array
    {
        $content = (string) $this->request->getContent();

        if ($content !== '') {
            $decoded = json_decode($content, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Fallback for form-encoded POSTs.
        return (array) $this->request->getPostValue();
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * The SDK relay POSTs a same-origin JSON body without a form key; mutation is constrained to the caller's own
     * session quote and carries no destructive side effects, so form-key validation is intentionally skipped.
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}