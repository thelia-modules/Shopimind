<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\OrderStatusEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmOrdersStatus;
use Shopimind\Data\OrderStatusData;
use Thelia\Model\Base\LangQuery;


class OrderStatusListener
{
    /**
     * Synchronizes data after a order status is inserted.
     *
     * @param OrderStatusEvent $event The event object triggering the action.
     */
    public static function postOrderStatusInsert(OrderStatusEvent $event): void
    {
        $orderStatus = $event->getModel();

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $orderStatusDefault = $orderStatus->getTranslation( $defaultLocal );

        $data = [];

        foreach ( $langs as $lang ) {
            $orderStatusTranslated = $orderStatus->getTranslation( $lang->getLocale() );

            $data[] = OrderStatusData::formatOrderStatus( $orderStatus, $orderStatusTranslated, $orderStatusDefault );
        }

        $response = SpmOrdersStatus::bulkSave( Utils::getAuth(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'OrderStatus', 'Save', json_encode( $response ), $orderStatus->getId() );
    }

    /**
     * Synchronizes data after a order status is updated.
     *
     * @param OrderStatusEvent $event The event object triggering the action.
     */
    public static function postOrderStatusUpdate(OrderStatusEvent $event): void
    {
        $orderStatus = $event->getModel();

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $orderStatusDefault = $orderStatus->getTranslation( $defaultLocal );

        $data = [];

        foreach ( $langs as $lang ) {
            $orderStatusTranslated = $orderStatus->getTranslation( $lang->getLocale() );

            $data[] = OrderStatusData::formatOrderStatus( $orderStatus, $orderStatusTranslated, $orderStatusDefault );
        }
        
        $response = SpmOrdersStatus::bulkUpdate( Utils::getAuth(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'OrderStatus', 'Update', json_encode( $response ), $orderStatus->getId() );
    }

    /**
     * Synchronizes data after a order status is deleted.
     *
     * @param OrderStatusEvent $event The event object triggering the action.
     */
    public static function postOrderStatusDelete(OrderStatusEvent $event): void
    {
        $orderStatus = $event->getModel()->getId();

        $response = SpmOrdersStatus::delete( Utils::getAuth(), $orderStatus );
        
        Utils::handleResponse( $response );

        Utils::log( 'OrderStatus', 'Delete', json_encode( $response ), $orderStatus );
    }
}
