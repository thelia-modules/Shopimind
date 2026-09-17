<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\OrderCouponEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmOrders;
use Shopimind\Data\OrdersData;

class OrderCouponListener
{
    /**
     * Synchronizes order after a order coupon is inserted.
     *
     * @param OrderCouponEvent $event The event object triggering the action.
     */
    public static function postOrderCouponInsert(OrderCouponEvent $event): void
    {
        $orderCoupon = $event->getModel();
        $order = $orderCoupon->getOrder();
        if ( empty( $order ) ) {
            return;
        }

        // Commande complète, comme OrderListener : l'objet partiel envoyé jusqu'ici (order_id, lang,
        // voucher_*, updated_at) était refusé par ShopiMind sur /orders, qui exige customer,
        // products, montants et dates.
        $data = [ OrdersData::formatOrder( $order ) ];

        $response = SpmOrders::bulkSave( Utils::getAuth(), $data );

        Utils::handleResponse( $response );

        Utils::log( 'OrderCoupon', 'update', json_encode( $response ), $orderCoupon->getOrderId() );
    }

}
