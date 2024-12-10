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

use Shopimind\Data\OrdersData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmOrders;
use Thelia\Model\Event\OrderEvent;

class OrderListener
{
    public function __construct(private OrdersData $ordersData)
    {
    }

    /**
     * Synchronizes data after a order is inserted.
     *
     * @param OrderEvent $event the event object triggering the action
     */
    public function postOrderInsert(OrderEvent $event): void
    {
        $order = $event->getModel();

        $data[] = $this->ordersData->formatOrder($order);

        $response = SpmOrders::bulkSave(Utils::getAuth(), $data);

        Utils::handleResponse($response);

        Utils::log('Order', 'Save', json_encode($response), $order->getId());
    }

    /**
     * Synchronizes data after an order is updated.
     *
     * @param OrderEvent $event the event object triggering the action
     */
    public function postOrderUpdate(OrderEvent $event): void
    {
        $order = $event->getModel();

        $data[] = $this->ordersData->formatOrder($order);

        $response = SpmOrders::bulkUpdate(Utils::getAuth(), $data);

        Utils::handleResponse($response);

        Utils::log('Order', 'Update', json_encode($response), $order->getId());
    }

    /**
     * Synchronizes data after a order is deleted.
     *
     * @param OrderEvent $event the event object triggering the action
     */
    public function postOrderDelete(OrderEvent $event): void
    {
        $order = $event->getModel()->getId();

        $response = SpmOrders::delete(Utils::getAuth(), $order);

        Utils::handleResponse($response);

        Utils::log('Order', 'Delete', json_encode($response), $order);
    }
}
