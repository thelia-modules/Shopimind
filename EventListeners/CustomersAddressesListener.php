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

namespace Shopimind\EventListeners;

require_once \dirname(__DIR__).'/vendor-module/autoload.php';

use Shopimind\Data\CustomersAddressesData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmCustomersAddresses;
use Thelia\Model\Event\AddressEvent;

class CustomersAddressesListener
{
    /**
     * Synchronizes data after a customer address is inserted.
     *
     * @param AddressEvent $event
     * @return void
     */
    public static function postAddressInsert(AddressEvent $event): void
    {
        $address = $event->getModel();

        $data[] = CustomersAddressesData::formatCustomerAddress($address);

        $response = SpmCustomersAddresses::bulkSave(Utils::getAuth(), $address->getCustomerId(), $data);

        Utils::handleResponse($response);

        Utils::log('CustomerAddress', 'Save', json_encode($response), $address->getId());
    }

    /**
     * Synchronizes data after a customer address is updated.
     *
     * @param AddressEvent $event
     * @return void
     */
    public static function postAddressUpdate(AddressEvent $event): void
    {
        $address = $event->getModel();

        $data[] = CustomersAddressesData::formatCustomerAddress($address);

        $response = SpmCustomersAddresses::bulkUpdate(Utils::getAuth(), $address->getCustomerId(), $data);

        Utils::handleResponse($response);

        Utils::log('CustomerAddress', 'Update', json_encode($response), $address->getId());
    }

    /**
     * Synchronizes data after a customer address is deleted.
     *
     * @param AddressEvent $event
     * @return void
     */
    public static function postAddressDelete(AddressEvent $event): void
    {
        $address = $event->getModel();

        $response = SpmCustomersAddresses::delete(Utils::getAuth(), $address->getCustomerId(), $address->getId());

        Utils::handleResponse($response);

        Utils::log('CustomerAddress', 'Delete', json_encode($response), $address->getId());
    }
}
