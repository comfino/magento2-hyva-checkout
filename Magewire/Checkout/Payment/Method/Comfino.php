<?php

/**
 * ComfinoPay Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\Magewire\Checkout\Payment\Method
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 by ComfinoPay sp. z o.o.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\Magewire\Checkout\Payment\Method;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magewirephp\Magewire\Component;
use Throwable;

/**
 * Magewire component behind `comfino.phtml`: keeps the cart total in grosze in step with the quote.
 *
 * Hyvä does not re-render payment templates when totals change, so `#comfino-loan-amount` would go stale after a
 * shipping, coupon or address change. The component listens to the same quote events as Hyvä's PriceSummary and
 * recomputes {@see $loanAmount}; the CDN checkout glue reads the fresh value and reloads the paywall.
 */
class Comfino extends Component
{
    /** Server-authoritative cart grand total in grosze, mirrored into #comfino-loan-amount by the template. */
    public int $loanAmount = 0;

    /**
     * Quote-mutating events Hyvä's PriceSummary refreshes on.
     *
     * @var array<string, string>
     */
    protected $listeners = [
        'shipping_method_selected' => 'refreshLoanAmount',
        'payment_method_selected' => 'refreshLoanAmount',
        'coupon_code_applied' => 'refreshLoanAmount',
        'coupon_code_revoked' => 'refreshLoanAmount',
        'shipping_address_saved' => 'refreshLoanAmount',
        'shipping_address_activated' => 'refreshLoanAmount',
        'billing_address_saved' => 'refreshLoanAmount',
        'billing_address_activated' => 'refreshLoanAmount',
    ];

    public function __construct(private readonly CheckoutSession $checkoutSession)
    {
    }

    /** Seed the total on the initial component render. */
    public function mount(): void
    {
        $this->loanAmount = $this->computeLoanAmount();
    }

    /** Recompute the total on any quote-mutating event; the property change re-renders the template. */
    public function refreshLoanAmount(): void
    {
        $this->loanAmount = $this->computeLoanAmount();
    }

    /**
     * Discards a client-supplied amount: `syncInput` assigns any public property and Magewire 1.x cannot lock one, so
     * the total is re-derived from the quote (the returned value is what gets assigned).
     *
     * @param mixed $value Client-supplied value, ignored
     */
    public function updatingLoanAmount(mixed $value): int
    {
        return $this->computeLoanAmount();
    }

    /** Cart grand total in grosze (1 PLN = 100 grosze); keeps the current value on failure rather than zeroing it. */
    private function computeLoanAmount(): int
    {
        try {
            return (int) round($this->checkoutSession->getQuote()->getGrandTotal() * 100);
        } catch (Throwable) {
            return $this->loanAmount;
        }
    }
}
