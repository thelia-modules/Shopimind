<?php

namespace Shopimind\Data;

use CustomerFamily\Model\CustomerFamily;
use CustomerFamily\Model\CustomerFamilyI18n;
use Shopimind\lib\Utils;

class CustomersGroupsData
{
    /**
     * Formats the customer customerGroup data to match the ShopiMind format.
     *
     * @param CustomerFamily $customerGroup
     * @param CustomerFamilyI18n $customerGroupTranslated
     * @param CustomerFamilyI18n $customersGroupDefault
     * @return array
     */
    public static function formatCustomerGroup( CustomerFamily $customerGroup, CustomerFamilyI18n $customerGroupTranslated, CustomerFamilyI18n $customersGroupDefault ): array
    {
        $currentDateTime = new \DateTime();

        $createdAt = !empty( $customerGroup->getCreatedAt() ) ? $customerGroup->getCreatedAt() : $currentDateTime;
        $updatedAt = !empty( $customerGroup->getUpdatedAt() ) ? $customerGroup->getUpdatedAt() : $currentDateTime;

        $data = [
            "group_id" => strval( $customerGroup->getId() ),
            'lang' => substr( $customerGroupTranslated->getLocale()  , 0, 2 ),
            "name" => $customerGroupTranslated->getTitle() ?? $customersGroupDefault->getTitle(),
            "created_at" => $createdAt->format('Y-m-d\TH:i:s.uP'),
            "updated_at" => $updatedAt->format('Y-m-d\TH:i:s.uP'), 
        ];

        $data['source_label'] = Utils::getSourceLabel();
        return $data;
    }
}
