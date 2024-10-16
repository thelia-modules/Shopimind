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

use Shopimind\Data\OrderStatusData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmOrdersStatus;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\Event\OrderStatusEvent;

class OrderStatusListener
{
    /**
     * Synchronizes data after an order status is inserted.
     *
     * @param OrderStatusEvent $event the event object triggering the action
     * @return void
     */
    public static function postOrderStatusInsert(OrderStatusEvent $event): void
    {
        $orderStatus = $event->getModel();

        $langs = LangQuery::create()->filterByActive(1)->find();

        $data = [];

        foreach ($langs as $lang) {
            $orderStatusTranslated = $orderStatus->getTranslation($lang->getLocale());

            $data[] = OrderStatusData::formatOrderStatus($orderStatus, $orderStatusTranslated);
        }

        $response = SpmOrdersStatus::bulkSave(Utils::getAuth(), $data);

        Utils::handleResponse($response);

        Utils::log('OrderStatus', 'Save', json_encode($response), $orderStatus->getId());
    }

    /**
     * Synchronizes data after an order status is updated.
     *
     * @param OrderStatusEvent $event the event object triggering the action
     * @return void
     */
    public static function postOrderStatusUpdate(OrderStatusEvent $event): void
    {
        $orderStatus = $event->getModel();

        $langs = LangQuery::create()->filterByActive(1)->find();

        $data = [];

        foreach ($langs as $lang) {
            $orderStatusTranslated = $orderStatus->getTranslation($lang->getLocale());

            $data[] = OrderStatusData::formatOrderStatus($orderStatus, $orderStatusTranslated);
        }

        $response = SpmOrdersStatus::bulkUpdate(Utils::getAuth(), $data);

        Utils::handleResponse($response);

        Utils::log('OrderStatus', 'Update', json_encode($response), $orderStatus->getId());
    }

    /**
     * Synchronizes data after an order status is deleted.
     *
     * @param OrderStatusEvent $event the event object triggering the action
     * @return void
     */
    public static function postOrderStatusDelete(OrderStatusEvent $event): void
    {
        $orderStatus = $event->getModel()->getId();

        $response = SpmOrdersStatus::delete(Utils::getAuth(), $orderStatus);

        Utils::handleResponse($response);

        Utils::log('OrderStatus', 'Delete', json_encode($response), $orderStatus);
    }
}
