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
use Thelia\Model\Base\Customer;
use Thelia\Model\LangQuery;
use Thelia\Model\NewsletterQuery;

class CustomersData
{
    /**
     * Formats the customer data to match the Shopimind format.
     *
     * @param Customer $customer
     * @return array
     */
    public static function formatCustomer(Customer $customer): array
    {
        $address = AddressQuery::create()->findOneByCustomerId($customer->getId());

        $phone = (!empty($address) && $address->getPhone()) ? $address->getPhone() : null;

        $defaultLang = LangQuery::create()->findOneByByDefault(1);
        $langQuery = LangQuery::create()->findOneById($customer->getLangId());
        $lang = (!empty($langQuery) && $langQuery->getCode()) ? $langQuery->getCode() : $defaultLang->getCode();

        return [
            'customer_id' => (string) $customer->getId(),
            'email' => $customer->getEmail(),
            'phone_number' => $phone,
            'first_name' => $customer->getFirstname(),
            'last_name' => $customer->getLastname(),
            'birth_date' => null,
            'is_opt_in' => true,
            'is_newsletter_subscribed' => self::isNewsletterSubscribed($customer->getEmail()),
            'lang' => $lang,
            'group_ids' => null,
            'is_active' => (bool) $customer->getEnable(),
            'created_at' => $customer->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $customer->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    /**
     * Verifies whether a customer is subscribed to the newsletter.
     *
     * @param string $customerEmail
     * @return bool
     */
    public static function isNewsletterSubscribed(string $customerEmail): bool
    {
        $newsletter = NewsletterQuery::create()->findOneByEmail($customerEmail);
        if (!empty($newsletter)) {
            return !$newsletter->getUnsubscribed();
        } else {
            return false;
        }
    }
}
