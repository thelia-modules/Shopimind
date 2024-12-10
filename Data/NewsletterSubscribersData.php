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

namespace Shopimind\Data;

use Thelia\Model\AddressQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Newsletter;
use Thelia\Model\NewsletterQuery;

class NewsletterSubscribersData
{
    /**
     * Formats the newsletter data to match the Shopimind format.
     */
    public function formatNewsletterSubscriber(Newsletter $newsletter): array
    {
        return [
            'email' => $newsletter->getEmail(),
            'is_subscribed' => self::isNewsletterSubscribed($newsletter->getEmail()),
            'first_name' => $newsletter->getFirstname() ?? '',
            'last_name' => $newsletter->getLastname() ?? '',
            'postal_code' => self::getZipCode($newsletter->getEmail()),
            'lang' => substr($newsletter->getLocale(), 0, 2),
            'updated_at' => $newsletter->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    /**
     * Verifies whether a customer is subscribed to the newsletter.
     */
    public function isNewsletterSubscribed(string $customerEmail): bool
    {
        $newsletter = NewsletterQuery::create()->findOneByEmail($customerEmail);
        if (!empty($newsletter)) {
            return !$newsletter->getUnsubscribed();
        } else {
            return false;
        }
    }

    /**
     * Retrieves the zipcode for a customer.
     */
    public static function getZipCode(string $email): string
    {
        $customer = CustomerQuery::create()->findOneByEmail($email);
        if (!empty($customer)) {
            $customerId = $customer->getId();
            $address = AddressQuery::create()->findOneByCustomerId($customerId);

            return !empty($address) ? $address->getZipCode() : '';
        }

        return '0';
    }

    /**
     * Format data to update customer after newslettersubscribing update.
     */
    public static function customerData(string $email): array
    {
        $customer = CustomerQuery::create()->findOneByEmail($email);

        $customerData = [];

        if (!empty($customer)) {
            $customerData = [
                'customer_id' => $customer->getId(),
                'is_newsletter_subscribed' => self::isNewsletterSubscribed($email),
            ];
        }

        return $customerData;
    }
}
