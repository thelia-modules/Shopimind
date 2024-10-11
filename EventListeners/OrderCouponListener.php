<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\OrderCouponEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmOrders;

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

        $spmOrder = new SpmOrders( Utils::getAuth() );

        $spmOrder->order_id = strval( $orderCoupon->getOrderId() );
        $spmOrder->lang = $orderCoupon->getOrder()->getLang()->getCode();
        $spmOrder->voucher_used = $orderCoupon->getCode();
        $spmOrder->voucher_value = strval( Utils::formatNumber( $orderCoupon->getAmount() ) );
        $spmOrder->updated_at = $orderCoupon->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z');

        $response = $spmOrder->update();

        Utils::handleResponse( $response );

        Utils::log( 'OrderCoupon', 'update', json_encode( $response ), $orderCoupon->getOrderId() );
    }

}
