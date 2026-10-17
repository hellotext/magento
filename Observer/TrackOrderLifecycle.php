<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Observer;

use Hellotext\Tracking\Model\OrderLifecycleTracker;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class TrackOrderLifecycle implements ObserverInterface
{
    public function __construct(private OrderLifecycleTracker $tracker) {}

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (is_object($order)) {
            $this->tracker->track($order);
        }
    }
}
