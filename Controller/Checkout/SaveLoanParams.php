<?php

/**
 * ComfinoPay Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\Controller\Checkout
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 by ComfinoPay sp. z o.o.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\Controller\Checkout;

use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Serializer\Json as JsonSerializer;
use Comfino\ComfinoGateway\Model\Configuration\SettingsManager;
use Comfino\ComfinoGateway\Observer\DataAssignObserver;
use Comfino\Enum\LoanTypeInterface;
use Comfino\Enum\ProductListType;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Stores the loan type and term chosen in the ComfinoPay paywall, and the paywall track ID, on the session quote's payment.
 *
 * Hyvä Checkout's Magewire flow saves only the payment method code, so `DataAssignObserver` never fires for it. The web SDK POSTs
 * the selection here (`config.checkoutSessionUrl`) and `ApplicationService::buildOrderDto()` reads it back from the order payment.
 */
class SaveLoanParams implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /** Payment `additional_information` key of the track ID. Defined here so base module releases that do not know it ignore it. */
    public const PAYMENT_TRACK_ID = 'trackId';

    /** Track ID format accepted by the ComfinoPay API and the shop plugins. */
    private const TRACK_ID_PATTERN = '/^[A-Za-z0-9_.:-]{1,128}$/';

    /** Loan type format, the same one comfino/magento2 (LoanSelection::sanitizeLoanType()) accepts. */
    private const LOAN_TYPE_PATTERN = '/^[A-Z_]{1,40}$/';

    /** Loan term bounds in months, the same hard cap comfino/magento2 (LoanSelection::sanitizeLoanTerm()) applies. */
    private const MIN_LOAN_TERM = 1;
    private const MAX_LOAN_TERM = 120;

    private RequestInterface $request;
    private JsonFactory $resultJsonFactory;
    private CheckoutSession $checkoutSession;
    private CartRepositoryInterface $cartRepository;
    private LoggerInterface $logger;
    private JsonSerializer $serializer;

    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger,
        ?JsonSerializer $serializer = null
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
        // Null default: Magento's interceptor generator rejects `new` in parameter defaults.
        $this->serializer = $serializer ?? new JsonSerializer();
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        try {
            // JSON only: a form-encoded POST is a simple request any origin can send; application/json needs a preflight.
            if (($payload = $this->readPayload()) === null) {
                return $result->setHttpResponseCode(415)->setData(['success' => false]);
            }

            $loanType = $this->sanitizeLoanType($payload['loanType'] ?? null);
            $loanTerm = $this->sanitizeLoanTerm($payload['loanTerm'] ?? null);
            $trackId = isset($payload['trackId']) && is_string($payload['trackId']) ? $payload['trackId'] : '';

            // An empty or failed lookup allows nothing. The base module's `LoanSelection::resolve()` checks again at order time.
            if ($loanType === null || !in_array($loanType, $this->getAllowedLoanTypes(), true)) {
                return $result->setHttpResponseCode(400)->setData(['success' => false]);
            }

            // Always the session quote; a client-supplied cart identifier is never used.
            $quote = $this->checkoutSession->getQuote();
            $payment = $quote->getPayment();

            $payment->setAdditionalInformation(DataAssignObserver::PAYMENT_LOAN_TYPE, $loanType);

            if ($loanTerm !== null) {
                $payment->setAdditionalInformation(DataAssignObserver::PAYMENT_LOAN_TERM, (string) $loanTerm);
            }

            // The order is created with the paywall's track ID, so promotions match what the shopper saw. Malformed IDs are dropped.
            if (preg_match(self::TRACK_ID_PATTERN, $trackId) === 1) {
                $payment->setAdditionalInformation(self::PAYMENT_TRACK_ID, $trackId);
            }

            $this->cartRepository->save($quote);

            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('ComfinoPay: Failed to persist loan params to quote payment.', ['exception' => $e]);

            return $result->setHttpResponseCode(500)->setData(['success' => false]);
        }
    }

    /**
     * Loan types offered in the paywall, or [] when the lookup fails.
     *
     * @return string[]
     */
    protected function getAllowedLoanTypes(): array
    {
        return array_map(
            static fn (LoanTypeInterface $loanType): string => $loanType->getValue(),
            SettingsManager::getProductTypesEnums(ProductListType::PAYWALL->value)
        );
    }

    /** Upper-case letters and underscores only, else null. */
    private function sanitizeLoanType(mixed $loanType): ?string
    {
        if (!is_string($loanType) || preg_match(self::LOAN_TYPE_PATTERN, $loanType = trim($loanType)) !== 1) {
            return null;
        }

        return $loanType;
    }

    /** An integer within the hard term cap (the SDK sends a number, or its digits as a string), else null. */
    private function sanitizeLoanTerm(mixed $loanTerm): ?int
    {
        if (is_string($loanTerm) && ctype_digit($loanTerm = trim($loanTerm))) {
            $loanTerm = (int) $loanTerm;
        }

        if (!is_int($loanTerm) || $loanTerm < self::MIN_LOAN_TERM || $loanTerm > self::MAX_LOAN_TERM) {
            return null;
        }

        return $loanTerm;
    }

    /**
     * The decoded JSON body, or null unless the request is an `application/json` object.
     *
     * @return array<string, mixed>|null
     */
    private function readPayload(): ?array
    {
        $contentType = $this->request instanceof HttpRequest ? (string) $this->request->getHeader('Content-Type') : '';

        if (stripos(trim($contentType), 'application/json') !== 0) {
            return null;
        }

        try {
            $decoded = $this->serializer->unserialize((string) $this->request->getContent());
        } catch (ResponseValidationError) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * The SDK relay sends no form key, so the check is skipped. The endpoint changes the order's financing terms, so it
     * is limited to the session quote, `application/json` bodies (a cross-origin page cannot send one without a preflight)
     * and offered loan types with a bounded term. The order path validates again.
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
