<?php

namespace Shopimind\Data;

use Thelia\Model\Address;
use Thelia\Model\CountryQuery;

class CustomersAddressesData
{
    /**
     * Formats the customer address data to match the Shopimind format.
     *  
     * @param Address $address
     */
    public static function formatCustomerAddress( Address $address ){
        $country = CountryQuery::create()->findOneById( $address->getCountryId() );

        $data = [
            "address_id" => intval( $address->getId() ),
            "first_name" => $address->getFirstname() ?? '',
            "last_name" => $address->getLastname() ?? '',
            "primary_phone" => $address->getPhone() ?? null,
            "secondary_phone" => $address->getCellphone() ?? null,
            "company" => $address->getCompany() ?? null,
            "address_line_1" => $address->getAddress1() ?? '',
            "address_line_2" => $address->getAddress2() ?? '',
            "postal_code" => $address->getZipcode() ?? '',
            "city" => $address->getCity() ?? '',
            "country" => !empty( $country ) ? $country->getTitle() : '',
            "is_active" => true
        ];

        return $data;
    }
}
