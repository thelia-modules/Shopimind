<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\AddressEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmCustomersAddresses;
use Shopimind\Data\CustomersAddressesData;

class CustomersAddressesListener
{
    /**
     * Synchronizes data after a customer address is inserted.
     *
     * @param AddressEvent $event The event object triggering the action.
     */
    public static function postAddressInsert( AddressEvent $event ): void
    {
        $address = $event->getModel();

        $data[] = CustomersAddressesData::formatCustomerAddress( $address );
        
        $response = SpmCustomersAddresses::bulkSave( Utils::getAuth(), $address->getCustomerId(), $data );

        Utils::handleResponse( $response );

        Utils::log( 'CustomerAddress', 'Save', json_encode( $response ), $address->getId() );
    }

    /**
     * Synchronizes data after a customer address is updated.
     *
     * @param AddressEvent $event The event object triggering the action.
     */
    public static function postAddressUpdate(AddressEvent $event): void
    {
        $address = $event->getModel();

        $data[] = CustomersAddressesData::formatCustomerAddress( $address );

        $response = SpmCustomersAddresses::bulkSave( Utils::getAuth(), $address->getCustomerId(), $data );

        Utils::handleResponse( $response );

        Utils::log( 'CustomerAddress', 'Update', json_encode( $response ), $address->getId() );
    }

    /**
     * Synchronizes data after a customer address is deleted.
     *
     * @param AddressEvent $event The event object triggering the action.
     */
    public static function postAddressDelete(AddressEvent $event): void
    {
        $address = $event->getModel();

        $response = SpmCustomersAddresses::delete( Utils::getAuth(), $address->getCustomerId(), $address->getId() );
        
        Utils::handleResponse( $response );

        Utils::log( 'CustomerAddress', 'Delete', json_encode( $response ), $address->getId() );
    }
}
