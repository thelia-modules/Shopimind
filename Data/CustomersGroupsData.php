<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopimind\Data;

use CustomerFamily\Model\CustomerFamily;
use CustomerFamily\Model\CustomerFamilyI18n;

class CustomersGroupsData
{
    /**
     * Formats the customer customerGroup data to match the Shopimind format.
     */
    public function formatCustomerGroup(CustomerFamily $customerGroup, CustomerFamilyI18n $customerGroupTranslated, CustomerFamilyI18n $customersGroupDefault): array
    {
        $currentDateTime = new \DateTime();

        $createdAt = !empty($customerGroup->getCreatedAt()) ? $customerGroup->getCreatedAt() : $currentDateTime;
        $updatedAt = !empty($customerGroup->getUpdatedAt()) ? $customerGroup->getUpdatedAt() : $currentDateTime;

        $data = [
            'group_id' => (string) $customerGroup->getId(),
            'lang' => substr($customerGroupTranslated->getLocale(), 0, 2),
            'name' => $customerGroupTranslated->getTitle() ?? $customersGroupDefault->getTitle(),
            'created_at' => $createdAt->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $updatedAt->format('Y-m-d\TH:i:s.u\Z'),
        ];

        return $data;
    }
}
