<?php

namespace Shopimind\Data;

use Thelia\Model\Address;
use Thelia\Model\CountryQuery;
use Shopimind\lib\Utils;

class CustomersAddressesData
{
    /**
     * Formats the customer address data to match the ShopiMind format.
     *  
     * @param Address $address
     */
    public static function formatCustomerAddress( Address $address ){
        $country = CountryQuery::create()->findOneById( $address->getCountryId() );
        $countryIso = !empty( $country ) ? strtoupper( $country->getIsoalpha2() ) : null;

        $data = [
            "address_id" => intval( $address->getId() ),
            "first_name" => $address->getFirstname() ?? '',
            "last_name" => $address->getLastname() ?? '',
            "primary_phone" => $address->getPhone() ?? null,
            "secondary_phone" => $address->getCellphone() ?? null,
            "company" => $address->getCompany() ?? null,
            "address_line_1" => $address->getAddress1() ?? '',
            "address_line_2" => $address->getAddress2() ?? '',
            // Seuls lettres, chiffres, espaces et tirets sont acceptés par ShopiMind (/^[\w\s\-]+$/).
            "postal_code" => Utils::sanitizePostalCode( $address->getZipcode() ),
            "city" => $address->getCity() ?? '',
            "country" => $countryIso,
            "is_active" => true,
            "created_at" => $address->getCreatedAt()->format('Y-m-d\TH:i:s.uP'),
            'updated_at' => $address->getUpdatedAt()->format('Y-m-d\TH:i:s.uP'),
        ];

        $data['source_label'] = Utils::getSourceLabel();
        return $data;
    }
}
