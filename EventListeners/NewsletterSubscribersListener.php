<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\NewsletterEvent;
use Shopimind\Model\Base\ShopimindQuery;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmNewsletterSubscribers;
use Shopimind\Data\NewsletterSubscribersData;


class NewsletterSubscribersListener
{
    /**
     * Synchronizes data after a newsletter subscription is inserted.
     *
     * @param NewsletterEvent $event The event object triggering the action.
     */
    public static function postNewsletterInsert(NewsletterEvent $event): void
    {
        $newsletter = $event->getModel();

        $data[] = NewsletterSubscribersData::formatNewsletterSubscriber( $newsletter );
        $response = SpmNewsletterSubscribers::bulkSave( Utils::getAuth(), $data );
        Utils::handleResponse( $response );
        Utils::log( 'NewsletterSubscribers', 'Insert', json_encode( $response ), $newsletter->getId() );

        $customer = NewsletterSubscribersData::customerData( $newsletter->getEmail() );
        if ( !empty( $customer ) ) {
            $response = SpmNewsletterSubscribers::bulkUpdate( Utils::getAuth(), $data );
            Utils::handleResponse( $response );
            Utils::log( 'Customer', 'Update', json_encode( $response ), $newsletter->getId() );
        }
    }

    /**
     * Synchronizes data after a newsletter subscription is updated.
     *
     * @param NewsletterEvent $event The event object triggering the action.
     */
    public static function postNewsletterUpdate(NewsletterEvent $event): void
    {
        $newsletter = $event->getModel();

        $data[] = NewsletterSubscribersData::formatNewsletterSubscriber( $newsletter );
        $response = SpmNewsletterSubscribers::bulkUpdate( Utils::getAuth(), $data );
        Utils::handleResponse( $response );
        Utils::log( 'NewsletterSubscribers', 'Update', json_encode( $response ), $newsletter->getId() );

        $customer = NewsletterSubscribersData::customerData( $newsletter->getEmail() );
        if ( !empty( $customer ) ) {
            $response = SpmNewsletterSubscribers::bulkUpdate( Utils::getAuth(), $data );
            Utils::handleResponse( $response );
            Utils::log( 'Customer', 'Update', json_encode( $response ), $newsletter->getId() );
        }
    }
}
