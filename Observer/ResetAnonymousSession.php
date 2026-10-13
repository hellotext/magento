<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Observer;

use Hellotext\Tracking\Model\AnonymousSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ResetAnonymousSession implements ObserverInterface
{
    public function __construct(private AnonymousSession $anonymous) {}

    public function execute(Observer $observer): void
    {
        $this->anonymous->reset();
    }
}
