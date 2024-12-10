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

use Thelia\Model\Address;
use Thelia\Model\CountryQuery;

class CustomersAddressesData
{
    /**
     * Formats the customer address data to match the Shopimind format.
     */
    public function formatCustomerAddress(Address $address): array
    {
        $country = CountryQuery::create()->findOneById($address->getCountryId());

        $data = [
            'address_id' => $address->getId(),
            'first_name' => $address->getFirstname() ?? '',
            'last_name' => $address->getLastname() ?? '',
            'primary_phone' => $address->getPhone() ?? null,
            'secondary_phone' => $address->getCellphone() ?? null,
            'company' => $address->getCompany() ?? null,
            'address_line_1' => $address->getAddress1() ?? '',
            'address_line_2' => $address->getAddress2() ?? '',
            'postal_code' => $address->getZipcode() ?? '',
            'city' => $address->getCity() ?? '',
            'country' => !empty($country) ? $country->getTitle() : '',
            'is_active' => true,
            'created_at' => $address->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $address->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
        ];

        return $data;
    }
}
