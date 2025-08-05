<?php

namespace Shopimind\Data;

use Thelia\Model\NewsletterQuery;
use Thelia\Model\Base\Customer;
use Thelia\Model\AddressQuery;
use Thelia\Model\LangQuery;
use CustomerFamily\Model\Base\CustomerCustomerFamilyQuery;
use Shopimind\lib\Utils;

class CustomersData
{
    /**
     * Formats the customer data to match the Shopimind format.
     *
     * @param Customer $customer
     * @return array
     */
    public static function formatCustomer( Customer $customer ): array
    {
        $address = AddressQuery::create()->findOneByCustomerId( $customer->getId() );

        $phone = ( !empty( $address ) && $address->getPhone() ) ? $address->getPhone() : null;

        $defaultLang = LangQuery::create()->findOneByByDefault( 1 );
        $langQuery = LangQuery::create()->findOneById( $customer->getLangId() );
        $lang = ( !empty( $langQuery ) && $langQuery->getCode() ) ? $langQuery->getCode() : $defaultLang->getCode();

        $groupIds = null;
        if ( Utils::isCustomerFamilyActive() ) {
            $groupIds = CustomerCustomerFamilyQuery::create()->filterByCustomerId( $customer->getId() )->select('CustomerFamilyId')->find()->toArray();        
            $groupIds = array_map( 'strval', $groupIds );
        }

        return [
            "customer_id" => strval( $customer->getId() ),
            "email" => $customer->getEmail(),
            "phone_number" => $phone,
            "first_name" => $customer->getFirstname(),
            "last_name" => $customer->getLastname(),
            "birth_date" => null,
            "is_opt_in" => true,
            "is_newsletter_subscribed" => self::isNewsletterSubscribed( $customer->getEmail() ),
            "lang" => $lang,
            "group_ids" => !empty( $groupIds ) ? $groupIds : null,
//            "is_active" => ( bool ) $customer->getEnable(),
            "is_active" => true,
            "created_at" => $customer->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            "updated_at" => $customer->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z')
        ];
    }

    /**
     * Verifies whether a customer is subscribed to the newsletter.
     *
     * @param string $customerEmail The customer's email address.
     * @return bool True if the customer is subscribed, false otherwise.
     */
    public static function isNewsletterSubscribed( string $customerEmail ) : bool{
        $newsletter = NewsletterQuery::create()->findOneByEmail( $customerEmail );
        if ( !empty($newsletter) ) {
            return ! $newsletter->getUnsubscribed();
        }else {
            return false;
        }
    }
}
