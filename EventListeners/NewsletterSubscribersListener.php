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

use Shopimind\Data\NewsletterSubscribersData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmNewsletterSubscribers;
use Thelia\Model\Event\NewsletterEvent;

class NewsletterSubscribersListener
{
    /**
     * Synchronizes data after a newsletter subscription is inserted.
     *
     * @param NewsletterEvent $event the event object triggering the action
     * @return void
     */
    public static function postNewsletterInsert(NewsletterEvent $event): void
    {
        $newsletter = $event->getModel();

        $data[] = NewsletterSubscribersData::formatNewsletterSubscriber($newsletter);
        $response = SpmNewsletterSubscribers::bulkSave(Utils::getAuth(), $data);
        Utils::handleResponse($response);
        Utils::log('NewsletterSubscribers', 'Insert', json_encode($response), $newsletter->getId());

        $customer = NewsletterSubscribersData::customerData($newsletter->getEmail());
        if (!empty($customer)) {
            $response = SpmNewsletterSubscribers::bulkUpdate(Utils::getAuth(), $data);
            Utils::handleResponse($response);
            Utils::log('Customer', 'Update', json_encode($response), $newsletter->getId());
        }
    }

    /**
     * Synchronizes data after a newsletter subscription is updated.
     *
     * @param NewsletterEvent $event the event object triggering the action
     * @return void
     */
    public static function postNewsletterUpdate(NewsletterEvent $event): void
    {
        $newsletter = $event->getModel();

        $data[] = NewsletterSubscribersData::formatNewsletterSubscriber($newsletter);
        $response = SpmNewsletterSubscribers::bulkUpdate(Utils::getAuth(), $data);
        Utils::handleResponse($response);
        Utils::log('NewsletterSubscribers', 'Update', json_encode($response), $newsletter->getId());

        $customer = NewsletterSubscribersData::customerData($newsletter->getEmail());
        if (!empty($customer)) {
            $response = SpmNewsletterSubscribers::bulkUpdate(Utils::getAuth(), $data);
            Utils::handleResponse($response);
            Utils::log('Customer', 'Update', json_encode($response), $newsletter->getId());
        }
    }
}
