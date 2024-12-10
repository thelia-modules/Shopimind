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

use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmOrders;
use Thelia\Model\Event\OrderCouponEvent;

class OrderCouponListener
{
    /**
     * Synchronizes order after an order coupon is inserted.
     *
     * @param OrderCouponEvent $event the event object triggering the action
     */
    public function postOrderCouponInsert(OrderCouponEvent $event): void
    {
        $orderCoupon = $event->getModel();

        $spmOrder = new SpmOrders(Utils::getAuth());

        $spmOrder->order_id = (string) $orderCoupon->getOrderId();
        $spmOrder->lang = $orderCoupon->getOrder()->getLang()->getCode();
        $spmOrder->voucher_used = $orderCoupon->getCode();
        $spmOrder->voucher_value = (string) Utils::formatNumber($orderCoupon->getAmount());
        $spmOrder->updated_at = $orderCoupon->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z');

        $response = $spmOrder->update();

        Utils::handleResponse($response);

        Utils::log('OrderCoupon', 'update', json_encode($response), $orderCoupon->getOrderId());
    }
}
