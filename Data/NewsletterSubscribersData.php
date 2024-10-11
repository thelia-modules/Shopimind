<?php

namespace Shopimind\Data;

use Thelia\Model\NewsletterQuery;
use Thelia\Model\AddressQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Newsletter;

class NewsletterSubscribersData
{
    /**
     * Formats the newsletter data to match the Shopimind format.
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
            "updated_at" => $newsletter->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z')
        ];

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
            $customerId = $customer->getId();
            $address = AddressQuery::create()->findOneByCustomerId( $customerId );
            $zipCode = !empty($address) ? $address->getZipCode() : '';
            return $zipCode;
        }
        return "0";
    }

    /**
     * Format data to update customer after newslettersubscribing update
     *
     * @param string $email
     */
    public static function customerData( string $email ): array
    {
        $customer = CustomerQuery::create()->findOneByEmail( $email );
        
        $customerData = [];
        
        if ( !empty( $customer ) ) {
            $customerData = [
                'customer_id' => $customer->getId(),
                'is_newsletter_subscribed' => self::isNewsletterSubscribed( $email ),
            ];
        }

        return $customerData;
    }
}
