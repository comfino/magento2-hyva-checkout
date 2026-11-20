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

use Comfino\ComfinoGatewayHyvaCheckout\Magewire\Checkout\Payment\Method\Comfino;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;

final class ComfinoMagewireComponentTest extends TestCase
{
    private float $grandTotal = 0.0;

    public function testMountSeedsTheLoanAmountFromTheQuoteInGrosze(): void
    {
        $component = $this->createComponent(123.45);
        $component->mount();

        $this->assertSame(12345, $component->loanAmount);
    }

    public function testQuoteMutatingEventsRefreshTheLoanAmount(): void
    {
        $component = $this->createComponent(100.0);
        $component->mount();

        $this->assertSame(10000, $component->loanAmount);

        $this->setGrandTotal(80.5);
        $component->refreshLoanAmount();

        $this->assertSame(8050, $component->loanAmount);
    }

    public function testAClientSuppliedLoanAmountIsDiscardedAndReDerivedFromTheQuote(): void
    {
        $component = $this->createComponent(250.0);
        $component->mount();

        // What Magewire's SyncInput does for a crafted { name: loanAmount, value: 1 } update.
        $value = $component->updatingLoanAmount(1);

        $this->assertSame(25000, $value);
    }

    public function testReturnsTheCurrentValueWhenTheQuoteCannotBeRead(): void
    {
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willThrowException(new \RuntimeException('no quote'));

        $component = new Comfino($session);
        $component->loanAmount = 4200;

        $this->assertSame(4200, $component->updatingLoanAmount(1));
    }

    private function createComponent(float $grandTotal): Comfino
    {
        $this->grandTotal = $grandTotal;

        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturnCallback(fn (): Quote => $this->quoteWithTotal($this->grandTotal));

        return new Comfino($session);
    }

    private function setGrandTotal(float $grandTotal): void
    {
        $this->grandTotal = $grandTotal;
    }

    /** Quote::getGrandTotal() is a magic getter, so the mock answers it through __call(). */
    private function quoteWithTotal(float $grandTotal): Quote
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('__call')->with('getGrandTotal')->willReturn($grandTotal);

        return $quote;
    }
}
