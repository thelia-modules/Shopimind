<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\CustomerEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmCustomers;
use Shopimind\Data\CustomersData;

class CustomersListener
{
    /**
     * Synchronizes data after a customer is inserted.
     *
     * @param CustomerEvent $event The event object triggering the action.
     */
    public static function postCustomerInsert(CustomerEvent $event): void
    {
        $customer = $event->getModel();

        $data[] = CustomersData::formatCustomer( $customer );
        
        $response = SpmCustomers::bulkSave( Utils::getAuth(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'Customer', 'Save', json_encode( $response ), $customer->getId() );
    }

    /**
     * Synchronizes data after a customer is updated.
     *
     * @param CustomerEvent $event The event object triggering the action.
     */
    public static function postCustomerUpdate(CustomerEvent $event): void
    {
        $customer = $event->getModel();

        $data[] = CustomersData::formatCustomer( $customer );
        
        $response = SpmCustomers::bulkUpdate( Utils::getAuth(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'Customer', 'Update', json_encode( $response ), $customer->getId() );
    }

    /**
     * Synchronizes data after a customer is deleted.
     *
     * @param CustomerEvent $event The event object triggering the action.
     */
    public static function postCustomerDelete(CustomerEvent $event): void
    {
        $customerId = $event->getModel()->getId();

        $response = SpmCustomers::delete( Utils::getAuth(), $customerId );

        Utils::handleResponse( $response );

        Utils::log( 'Customer', 'Delete', json_encode( $response ), $customerId );
    }
}
