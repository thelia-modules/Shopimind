<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\NewsletterEvent;
use Shopimind\Model\Base\ShopimindQuery;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmNewsletterSubscribers;
use Shopimind\Data\NewsletterSubscribersData;
use Shopimind\Data\CustomersData;
use Shopimind\SdkShopimind\SpmCustomers;
use Thelia\Model\CustomerQuery;


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

        self::syncCustomer( $newsletter->getEmail() );
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
        $response = SpmNewsletterSubscribers::bulkSave( Utils::getAuth(), $data );
        Utils::handleResponse( $response );
        Utils::log( 'NewsletterSubscribers', 'Update', json_encode( $response ), $newsletter->getId() );

        self::syncCustomer( $newsletter->getEmail() );
    }

    /**
     * Renvoie la fiche du client lié à l'e-mail : son is_newsletter_subscribed est lu dans la table
     * newsletter, qu'un abonnement modifie sans déclencher d'événement client. Le second appel
     * renvoyait l'abonné une deuxième fois au lieu du client.
     *
     * @param string $email
     */
    protected static function syncCustomer( string $email ): void
    {
        $customer = CustomerQuery::create()->findOneByEmail( $email );
        if ( empty( $customer ) ) {
            return;
        }

        $data = [ CustomersData::formatCustomer( $customer ) ];
        $response = SpmCustomers::bulkSave( Utils::getAuth(), $data );
        Utils::handleResponse( $response );
        Utils::log( 'Customer', 'Update', json_encode( $response ), $customer->getId() );
    }
}
