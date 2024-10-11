<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\OrderEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmOrders;
use Shopimind\Data\OrdersData;


class OrderListener 
{
    /**
     * Synchronizes data after a order is inserted.
     *
     * @param OrderEvent $event The event object triggering the action.
     */
    public static function postOrderInsert(OrderEvent $event): void
    {
        $order = $event->getModel();

        $data[] = OrdersData::formatOrder( $order );
        
        $response = SpmOrders::bulkSave( Utils::getAuth(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'Order', 'Save', json_encode( $response ), $order->getId() );
    }

    /**
     * Synchronizes data after a order is updated.
     *
     * @param OrderEvent $event The event object triggering the action.
     */
    public static function postOrderUpdate(OrderEvent $event): void
    {
        $order = $event->getModel();
        
        $data[] = OrdersData::formatOrder( $order );

        $response = SpmOrders::bulkUpdate( Utils::getAuth(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'Order', 'Update', json_encode( $response ), $order->getId() );
    }

    /**
     * Synchronizes data after a order is deleted.
     *
     * @param OrderEvent $event The event object triggering the action.
     */
    public static function postOrderDelete(OrderEvent $event): void
    {
        $order = $event->getModel()->getId();

        $response = SpmOrders::delete( Utils::getAuth(), $order );
        
        Utils::handleResponse( $response );

        Utils::log( 'Order', 'Delete', json_encode( $response ), $order );
    }
}
