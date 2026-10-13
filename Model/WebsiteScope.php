<?php
declare(strict_types=1);

namespace Hellotext\Tracking\Model;

use Magento\Store\Model\StoreManagerInterface;

/** Resolves the current representative store view and all views in its website. */
class WebsiteScope
{
    public static function current(StoreManagerInterface $stores): array
    {
        $store = $stores->getStore();
        $storeId = (int) $store->getId();
        $websiteId = (int) $store->getWebsiteId();
        $storeIds = [];
        foreach ($stores->getStores() as $websiteStore) {
            if ((int) $websiteStore->getWebsiteId() === $websiteId) {
                $id = (int) $websiteStore->getId();
                if ($id > 0) $storeIds[] = $id;
            }
        }
        $storeIds = array_values(array_unique($storeIds));

        if ($storeId <= 0 || $websiteId <= 0 || !in_array($storeId, $storeIds, true)) {
            throw new \InvalidArgumentException('Magento website scope is invalid.');
        }

        return ['store_id' => $storeId, 'website_id' => $websiteId, 'store_ids' => $storeIds];
    }
}
