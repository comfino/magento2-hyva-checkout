<?php

/**
 * ComfinoPay Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\Test
 * @copyright Copyright (c) 2026 by ComfinoPay sp. z o.o.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\Test;

use Comfino\ComfinoGatewayHyvaCheckout\Controller\Checkout\SaveLoanParams;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NotFoundException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SaveLoanParamsTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $stored = [];

    /**
     * @throws NotFoundException
     */
    public function testStoresTheLoanParamsAndTheTrackIdOnTheQuotePayment(): void
    {
        $this->execute(['loanType' => 'CONVENIENT_INSTALLMENTS', 'loanTerm' => 18, 'trackId' => '01a1d4b7-46b3-725e-86cc-0047ff6f3881']);

        $this->assertSame(
            ['loanType' => 'CONVENIENT_INSTALLMENTS', 'loanTerm' => '18', 'trackId' => '01a1d4b7-46b3-725e-86cc-0047ff6f3881'],
            $this->stored
        );
    }

    /**
     * @throws NotFoundException
     */
    public function testSkipsAMissingOrMalformedTrackId(): void
    {
        $this->execute(['loanType' => 'CONVENIENT_INSTALLMENTS', 'loanTerm' => 18, 'trackId' => "bad\r\nvalue"]);

        $this->assertArrayNotHasKey(SaveLoanParams::PAYMENT_TRACK_ID, $this->stored);

        $this->stored = [];

        $this->execute(['loanType' => 'CONVENIENT_INSTALLMENTS', 'loanTerm' => 18]);

        $this->assertArrayNotHasKey(SaveLoanParams::PAYMENT_TRACK_ID, $this->stored);
    }

    /**
     * @throws NotFoundException
     */
    public function testRejectsALoanTypeTheMerchantDoesNotOffer(): void
    {
        $this->assertSame(400, $this->execute(['loanType' => 'PAY_LATER', 'loanTerm' => 3], saves: 0));
        $this->assertSame([], $this->stored);
    }

    /**
     * @throws NotFoundException
     */
    public function testRejectsAMalformedLoanType(): void
    {
        foreach (['convenient_installments', 'CONVENIENT-INSTALLMENTS', '', ['x'], 7, "PAY_\r\nLATER"] as $loanType) {
            $this->assertSame(
                400,
                $this->execute(['loanType' => $loanType, 'loanTerm' => 3], ['CONVENIENT_INSTALLMENTS', 'PAY_LATER'], saves: 0)
            );
        }

        $this->assertSame([], $this->stored);
    }

    /**
     * @throws NotFoundException
     */
    public function testRejectsEverythingWhenTheOfferedTypesCannotBeResolved(): void
    {
        $this->assertSame(400, $this->execute(['loanType' => 'CONVENIENT_INSTALLMENTS', 'loanTerm' => 12], [], saves: 0));
        $this->assertSame([], $this->stored);
    }

    /**
     * @throws NotFoundException
     */
    public function testDropsAnOutOfRangeOrNonNumericLoanTerm(): void
    {
        foreach ([999999, 0, -3, '12abc', '1.5', 12.5, [12], null] as $loanTerm) {
            $this->stored = [];

            $this->execute(['loanType' => 'CONVENIENT_INSTALLMENTS', 'loanTerm' => $loanTerm]);

            $this->assertSame(['loanType' => 'CONVENIENT_INSTALLMENTS'], $this->stored);
        }
    }

    /**
     * @throws NotFoundException
     */
    public function testAcceptsTheTermBoundsAndADigitString(): void
    {
        foreach ([1 => '1', 120 => '120', '36' => '36'] as $loanTerm => $expected) {
            $this->stored = [];

            $this->execute(['loanType' => 'CONVENIENT_INSTALLMENTS', 'loanTerm' => $loanTerm]);

            $this->assertSame($expected, $this->stored['loanTerm']);
        }
    }

    /**
     * @throws NotFoundException
     */
    public function testRefusesARequestThatIsNotJson(): void
    {
        $payload = ['loanType' => 'CONVENIENT_INSTALLMENTS', 'loanTerm' => 12];

        foreach (['application/x-www-form-urlencoded', 'text/plain', 'multipart/form-data; boundary=x', ''] as $contentType) {
            $this->assertSame(415, $this->execute($payload, contentType: $contentType, saves: 0));
        }

        $this->assertSame(415, $this->execute($payload, rawBody: 'loanType=CONVENIENT_INSTALLMENTS', saves: 0));
        $this->assertSame(415, $this->execute($payload, rawBody: '"just a string"', saves: 0));
        $this->assertSame([], $this->stored);
    }

    /**
     * @throws NotFoundException
     */
    public function testAcceptsAJsonContentTypeWithACharset(): void
    {
        $this->execute(['loanType' => 'CONVENIENT_INSTALLMENTS', 'loanTerm' => 12], contentType: 'application/json; charset=UTF-8');

        $this->assertSame('CONVENIENT_INSTALLMENTS', $this->stored['loanType']);
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[] $allowedTypes Loan types the merchant offers
     * @param int $saves Expected number of quote saves
     *
     * @return int HTTP response code (200 unless the controller set another)
     *
     * @throws NotFoundException
     */
    private function execute(
        array $payload,
        array $allowedTypes = ['CONVENIENT_INSTALLMENTS'],
        string $contentType = 'application/json',
        ?string $rawBody = null,
        int $saves = 1
    ): int {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getContent')->willReturn($rawBody ?? json_encode($payload));
        $request->method('getHeader')->willReturn($contentType);

        $responseCode = 200;

        $result = $this->createMock(Json::class);
        $result->method('setData')->willReturnSelf();
        $result->method('setHttpResponseCode')->willReturnCallback(
            function (int $code) use ($result, &$responseCode): Json {
                $responseCode = $code;

                return $result;
            }
        );

        $resultJsonFactory = $this->createMock(JsonFactory::class);
        $resultJsonFactory->method('create')->willReturn($result);

        $payment = $this->createMock(Payment::class);
        $payment->method('setAdditionalInformation')->willReturnCallback(
            function (string $key, mixed $value) use ($payment): Payment {
                $this->stored[$key] = $value;

                return $payment;
            }
        );

        $quote = $this->createMock(Quote::class);
        $quote->method('getPayment')->willReturn($payment);

        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->expects($this->exactly($saves))->method('save')->with($quote);

        (new class (
            $request,
            $resultJsonFactory,
            $checkoutSession,
            $cartRepository,
            $this->createMock(LoggerInterface::class),
            $allowedTypes
        ) extends SaveLoanParams {
            /** @param string[] $allowedTypes */
            public function __construct(
                HttpRequest $request,
                JsonFactory $resultJsonFactory,
                CheckoutSession $checkoutSession,
                CartRepositoryInterface $cartRepository,
                LoggerInterface $logger,
                private readonly array $allowedTypes
            ) {
                parent::__construct($request, $resultJsonFactory, $checkoutSession, $cartRepository, $logger);
            }

            protected function getAllowedLoanTypes(): array
            {
                return $this->allowedTypes;
            }
        })->execute();

        return $responseCode;
    }
}
