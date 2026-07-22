<?php

/**
 * Comfino Payment Gateway for Magento 2 with Hyvä Checkout
 *
 * @package Comfino\ComfinoGatewayHyvaCheckout\Magewire\Checkout\Payment\Method
 * @author Artur Kozubski <a.kozubski@artkosoft.pl>
 * @copyright Copyright (c) 2026 Comfino by Comperia.pl S.A.
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link https://github.com/comfino/magento2-hyva-checkout
 */

declare(strict_types=1);

namespace Comfino\ComfinoGatewayHyvaCheckout\Magewire\Checkout\Payment\Method;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magewirephp\Magewire\Component;
use Throwable;

/**
 * Magewire component backing the Comfino payment-method template (comfino.phtml).
 *
 * Keeps the server-authoritative cart total (in grosze) in sync with the quote so the paywall iframe can be reloaded
 * when it changes. Hyvä Checkout does not re-render payment-method templates on totals changes and emits no browser
 * event carrying the new total, so a plain block leaves the #comfino-loan-amount hidden input stale after a
 * shipping/coupon/address change (only the order summary re-renders).
 *
 * By subscribing to the same quote-mutating Magewire events Hyvä's own PriceSummary listens to, this component
 * recomputes {@see $loanAmount} and re-renders the template, refreshing the hidden input server-side. The CDN-served
 * checkout glue (comfino-magento-hyva.min.js) then reads the refreshed value and reloads the paywall. Keeping the
 * total server-computed avoids parsing localized currency strings out of the summary DOM on the client.
 */
class Comfino extends Component
{
    /** Server-authoritative cart grand total in grosze, mirrored into #comfino-loan-amount by the template. */
    public int $loanAmount = 0;

    /**
     * Quote-mutating events Hyvä dispatches on the Magewire bus — the same set Hyvä's PriceSummary refreshes on.
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