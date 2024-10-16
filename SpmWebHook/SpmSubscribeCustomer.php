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

namespace Shopimind\SpmWebHook;

use Shopimind\lib\Utils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Newsletter;

class SpmSubscribeCustomer
{
    /**
     * Subscribe customer to the newsletter.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Throwable
     */
    public static function subscribeCustomer(Request $request): JsonResponse
    {
        $requestValidation = Utils::validateSpmRequest($request);
        if (!empty($requestValidation)) {
            return $requestValidation;
        }

        $body = json_decode($request->getContent(), true);

        $status = true;
        $message = 'Customer subscribed successfully.';

        $customerId = (\array_key_exists('id_customer', $body)) ? $body['id_customer'] : '';
        if (!empty($customerId)) {
            $customer = CustomerQuery::create()->findOneById($customerId);
            if (!empty($customer)) {
                try {
                    $newsletter = new Newsletter();
                    $newsletter->setEmail($customer->getEmail());
                    $newsletter->setFirstname($customer->getFirstname());
                    $newsletter->setLastname($customer->getLastname());
                    $newsletter->setLocale($customer->getLocale());
                    $newsletter->setUnsubscribed(0);
                    $newsletter->setCreatedAt(new \DateTime());
                    $newsletter->setUpdatedAt(new \DateTime());

                    $newsletter->save();
                } catch (\Throwable $th) {
                    $status = false;
                    $message = $th->getMessage();
                }
            } else {
                $status = false;
                $message = 'The customer does not exist.';
            }
        } else {
            $status = false;
            $message = 'Invalid customer id';
        }

        return new JsonResponse([
            'success' => $status,
            'message' => $message,
        ]);
    }
}
