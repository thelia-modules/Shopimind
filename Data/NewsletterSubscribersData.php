<?php

namespace Shopimind\Data;

use Thelia\Model\NewsletterQuery;
use Thelia\Model\AddressQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Newsletter;
use Shopimind\lib\Utils;

class NewsletterSubscribersData
{
    /**
     * Formats the newsletter data to match the ShopiMind format.
     *
     * @param Newsletter $newsletter
     * @return array
     */
    public static function formatNewsletterSubscriber( Newsletter $newsletter ): array
    {
        $data = [
            "email" => $newsletter->getEmail(),
            "is_subscribed" => self::isNewsletterSubscribed( $newsletter->getEmail() ),
            "first_name" => $newsletter->getFirstname() ? $newsletter->getFirstname() : "" ,
            "last_name" => $newsletter->getLastname() ? $newsletter->getLastname() : "" ,
            "postal_code" => self::getZipCode( $newsletter->getEmail() ),
            "lang" => substr( $newsletter->getLocale() , 0, 2 ),
            "updated_at" => $newsletter->getUpdatedAt()->format('Y-m-d\TH:i:s.uP')
        ];

        $data['source_label'] = Utils::getSourceLabel();
        return $data;
    }

    /**
     * Verifies whether a customer is subscribed to the newsletter.
     *
     * @param string $customerEmail The customer's email address.
     * @return bool True if the customer is subscribed, false otherwise.
     */
    public static function isNewsletterSubscribed( string $customerEmail) : bool{
        $newsletter = NewsletterQuery::create()->findOneByEmail( $customerEmail );
        if ( !empty($newsletter) ) {
            return ! $newsletter->getUnsubscribed();
        }else {
            return false;
        }
    }

    /**
     * Retrieves the zipcode for a customer.
     *
     * @param string $email The email.
     */
    public static function getZipCode( string $email ) {
        $customer = CustomerQuery::create()->findOneByEmail( $email );
        if ( !empty( $customer ) ) {
            $address = AddressQuery::create()->findOneByCustomerId( $customer->getId() );
            if ( !empty( $address ) ) {
                return Utils::sanitizePostalCode( $address->getZipCode() );
            }
        }

        // postal_code est facultatif pour ShopiMind (null accepté) mais, s'il est présent, doit respecter
        // /^[\w\s\-]+$/ : la chaîne vide (client sans adresse) faisait rejeter l'abonné, et "0"
        // (prospect sans compte) enregistrait un faux code postal.
        return null;
    }
}
