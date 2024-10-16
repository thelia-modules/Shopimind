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

use Shopimind\Data\CustomersData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmCustomers;
use Thelia\Model\Event\CustomerEvent;

class CustomersListener
{
    /**
     * Synchronizes data after a customer is inserted.
     *
     * @param CustomerEvent $event the event object triggering the action
     * @return void
     */
    public static function postCustomerInsert(CustomerEvent $event): void
    {
        $customer = $event->getModel();

        $data[] = CustomersData::formatCustomer($customer);

        $response = SpmCustomers::bulkSave(Utils::getAuth(), $data);

        Utils::handleResponse($response);

        Utils::log('Customer', 'Save', json_encode($response), $customer->getId());
    }

    /**
     * Synchronizes data after a customer is updated.
     *
     * @param CustomerEvent $event the event object triggering the action
     * @return void
     */
    public static function postCustomerUpdate(CustomerEvent $event): void
    {
        $customer = $event->getModel();

        $data[] = CustomersData::formatCustomer($customer);

        $response = SpmCustomers::bulkUpdate(Utils::getAuth(), $data);

        Utils::handleResponse($response);

        Utils::log('Customer', 'Update', json_encode($response), $customer->getId());
    }

    /**
     * Synchronizes data after a customer is deleted.
     *
     * @param CustomerEvent $event the event object triggering the action
     * @return void
     */
    public static function postCustomerDelete(CustomerEvent $event): void
    {
        $customerId = $event->getModel()->getId();

        $response = SpmCustomers::delete(Utils::getAuth(), $customerId);

        Utils::handleResponse($response);

        Utils::log('Customer', 'Delete', json_encode($response), $customerId);
    }
}
