<?php

namespace Shopimind\Data;

class CustomersGroupsData
{
    /**
     * Formats the customer customerGroup data to match the Shopimind format.
     *
     * @param \CustomerFamily\Model\CustomerFamily $customerGroup
     * @param \CustomerFamily\Model\CustomerFamilyI18n $customerGroupTranslated
     * @param \CustomerFamily\Model\CustomerFamilyI18n $customersGroupDefault
     * @return array
     */
    public static function formatCustomerGroup( object $customerGroup, object $customerGroupTranslated, object $customersGroupDefault ): array
    {
        $currentDateTime = new \DateTime();

        $createdAt = !empty( $customerGroup->getCreatedAt() ) ? $customerGroup->getCreatedAt() : $currentDateTime;
        $updatedAt = !empty( $customerGroup->getUpdatedAt() ) ? $customerGroup->getUpdatedAt() : $currentDateTime;

        $data = [
            "group_id" => strval( $customerGroup->getId() ),
            'lang' => substr( $customerGroupTranslated->getLocale()  , 0, 2 ),
            "name" => $customerGroupTranslated->getTitle() ?? $customersGroupDefault->getTitle(),
            "created_at" => $createdAt->format('Y-m-d\TH:i:s.u\Z'),
            "updated_at" => $updatedAt->format('Y-m-d\TH:i:s.u\Z'), 
        ];

        return $data;
    }
}
