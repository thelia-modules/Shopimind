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
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Newsletter;

class SpmCustomers
{
    /**
     * Create customer.
     */
    public static function createCustomer(Request $request)
    {
        $requestValidation = Utils::validateSpmRequest($request);
        if (!empty($requestValidation)) {
            return $requestValidation;
        }

        $content = $request->getContent();
        parse_str($content, $body);

        $status = true;
        $message = 'Customer created successfully.';
        $defaultLang = LangQuery::create()->findOneByByDefault(true)->getId();

        $customerData = $body['customer'];
        $errors = self::validate($customerData);

        if (!empty($errors)) {
            return $errors;
        }

        $email = $customerData['email'];
        $firstName = $customerData['firstName'];
        $lastName = $customerData['lastName'];
        $langParam = (\array_key_exists('lang', $customerData)) ? $customerData['lang'] : '';
        $lang = LangQuery::create()->findOneByCode($langParam);
        $langId = !empty($lang) ? $lang->getId() : $defaultLang;
        $local = !empty($lang) ? $lang->getLocale() : LangQuery::create()->findOneByByDefault(true)->getLocale();
        $password = $customerData['password'];
        $newsletter = (\array_key_exists('newsletter', $customerData)) ? $customerData['newsletter'] : 0;

        $emailExists = CustomerQuery::create()->filterByEmail($email)->exists();

        if (!$emailExists) {
            try {
                $customer = new Customer();
                $customer->setTitleId(1);
                $customer->setLangId($langId);
                $customer->setRef(self::generateRef());
                $customer->setFirstname($firstName);
                $customer->setLastname($lastName);
                $customer->setEmail($email);
                $customer->setPassword($password);
                $customer->setAlgo('PASSWORD_BCRYPT');
                $customer->setReseller(null);
                $customer->setSponsor(null);
                $customer->setDiscount(null);
                $customer->setRememberMeToken('');
                $customer->setRememberMeSerial('');
                $customer->setEnable(1);
                $customer->setConfirmationToken(null);
                $customer->setCreatedAt(new \DateTime());
                $customer->setUpdatedAt(new \DateTime());
                $customer->setVersion('');
                $customer->setCreatedAt(new \DateTime());
                $customer->setVersionCreatedAt(new \DateTime());
                $customer->setVersionCreatedBy(null);

                $customer->save();

                if ($newsletter == 1) {
                    $newsletter = new Newsletter();
                    $newsletter->setEmail($email);
                    $newsletter->setFirstname($firstName);
                    $newsletter->setLastname($lastName);
                    $newsletter->setLocale($local);
                    $newsletter->setUnsubscribed(0);
                    $newsletter->setCreatedAt(new \DateTime());
                    $newsletter->setUpdatedAt(new \DateTime());

                    $newsletter->save();
                }
            } catch (\Throwable $th) {
                $status = false;
                $message = $th->getMessage();
            }
        } else {
            $status = false;
            $message = 'Email already exist.';
        }

        $response = new JsonResponse([
            'success' => $status,
            'message' => $message,
        ]);

        return $response;
    }

    /**
     * Generates a unique reference for a customer.
     *
     * @return string the generated reference
     */
    public static function generateRef()
    {
        $lastId = CustomerQuery::create()->orderByRef(\Propel\Runtime\ActiveQuery\Criteria::DESC)->findOne();
        $lastIdNumber = $lastId ? (int) substr($lastId->getRef(), 3) : 0;

        $newIdNumber = $lastIdNumber + 1;
        $newRef = 'CUS'.str_pad($newIdNumber, 12, '0', \STR_PAD_LEFT);

        return $newRef;
    }

    /**
     * Validate customer creation parameters.
     *
     * @param array $params an array containing the customer creation parameters
     */
    public static function validate($params)
    {
        $message = '';

        $requiredParams = [
            'email',
            'password',
            'firstName',
            'lastName',
        ];

        foreach ($requiredParams as $param) {
            if (empty($params[$param])) {
                $message = $param.' is required.';
            }
        }

        if (!empty($params['email']) && !filter_var($params['email'], \FILTER_VALIDATE_EMAIL)) {
            $message = 'The email address is not valid.';
        }

        if (!empty($message)) {
            $response = new JsonResponse([
                'success' => false,
                'message' => $message,
            ]);

            return $response;
        }
    }
}
