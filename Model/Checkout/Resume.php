<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteMutexInterface;
use Magento\Store\Model\StoreManagerInterface;

/** Attaches a prepared guest cart to the visiting shopper's native checkout. */
class Resume
{
    public function __construct(
        private MaskedQuoteIdToQuoteIdInterface $maskedIds,
        private CartRepositoryInterface $quotes,
        private CartManagementInterface $carts,
        private QuoteMutexInterface $mutex,
        private CheckoutSession $checkout,
        private CustomerSession $customer,
        private StoreManagerInterface $stores
    ) {}

    public function execute(string $maskedId): void
    {
        if (preg_match('/\A[a-zA-Z0-9]{32}\z/', $maskedId) !== 1) {
            throw new NoSuchEntityException(__('This checkout link is no longer available.'));
        }

        // Magento's masked-cart mutex also commits or rolls back the customer
        // assignment and merge before the browser session is switched.
        $quote = $this->mutex->execute([$maskedId], function () use ($maskedId): Quote {
            $quoteId = $this->maskedIds->execute($maskedId);
            $quote = $this->quotes->getActive($quoteId);
            $storeId = (int) $this->stores->getStore()->getId();

            if ($quote->getCustomerId() || (int) $quote->getStoreId() !== $storeId || !$quote->hasItems()) {
                throw new NoSuchEntityException(__('This checkout link is no longer available.'));
            }

            if ($this->customer->isLoggedIn()) {
                // Native assignment appends the customer's existing basket and
                // removes the guest mask so it cannot expose a customer's cart.
                $this->carts->assignCustomer($quoteId, (int) $this->customer->getCustomerId(), $storeId);
                $quote = $this->quotes->getActive($quoteId);
            }

            $quote->setTotalsCollectedFlag(false);
            $this->quotes->save($quote->collectTotals());

            return $quote;
        });

        $this->checkout->replaceQuote($quote)->resetCheckout();
        $this->checkout->clearHelperData();
        $this->checkout->setLastSuccessQuoteId(null);
        $this->checkout->setCartWasUpdated(true);
    }
}
