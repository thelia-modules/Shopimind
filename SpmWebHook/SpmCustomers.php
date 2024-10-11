<?php

namespace Shopimind\SpmWebHook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Model\LangQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Newsletter;
use Shopimind\lib\Utils;

class SpmCustomers
{
    /**
     * Create customer
     *
     * @param Request $request
     * 
     */
    public static function createCustomer(Request $request)
    {
        $requestValidation = Utils::validateSpmRequest( $request );
        if ( !empty( $requestValidation ) ) return $requestValidation;

        $body =  json_decode( $request->getContent(), true );

        $status = true;
        $message = "Customer created successfully.";
        $defaultLang = LangQuery::create()->findOneByByDefault(true)->getId();
        
        $errors = self::validate( $body );
        if ( !empty( $errors ) ) {
            return $errors;
        }

        $email = $body['email'];
        $firstName = $body['firstName'];
        $lastName = $body['lastName'];
        $langParam = ( array_key_exists('lang', $body ) ) ? $body['lang'] : '';
        $lang = LangQuery::create()->findOneByCode( $langParam );
        $langId = !empty($lang) ? $lang->getId() : $defaultLang;
        $local = !empty($lang ) ? $lang->getLocale() : LangQuery::create()->findOneByByDefault(true)->getLocale();
        $password = $body['password'];
        $newsletter = ( array_key_exists('newsletter', $body ) ) ? $body['newsletter'] : 0;

        $emailExists = CustomerQuery::create()->filterByEmail($email)->exists();

        if ( !$emailExists ) {
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
                $customer->setReseller(NULL);
                $customer->setSponsor(NULL);
                $customer->setDiscount(NULL);
                $customer->setRememberMeToken('');
                $customer->setRememberMeSerial('');
                $customer->setEnable(1);
                $customer->setConfirmationToken(NULL);
                $customer->setCreatedAt(new \DateTime());
                $customer->setUpdatedAt(new \DateTime());
                $customer->setVersion('');
                $customer->setCreatedAt(new \DateTime());
                $customer->setVersionCreatedAt(new \DateTime());
                $customer->setVersionCreatedBy(NULL);

                $customer->save();

                if ( $newsletter == 1 ) {
                    $newsletter = new Newsletter();
                    $newsletter->setEmail( $email );
                    $newsletter->setFirstname( $firstName );
                    $newsletter->setLastname( $lastName );
                    $newsletter->setLocale( $local );
                    $newsletter->setUnsubscribed( 0 );
                    $newsletter->setCreatedAt( new \DateTime() );
                    $newsletter->setUpdatedAt( new \DateTime() );

                    $newsletter->save();
                }
            } catch (\Throwable $th) {
                $status = false;
                $message = $th->getMessage();
            }
        } else {
            $status = false;
            $message = "Email already exist.";
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
     * @return string The generated reference.
     */
    public static function generateRef()
    {
        $lastId = CustomerQuery::create()->orderByRef(\Propel\Runtime\ActiveQuery\Criteria::DESC)->findOne();
        $lastIdNumber = $lastId ? (int)substr($lastId->getRef(), 3) : 0;

        $newIdNumber = $lastIdNumber + 1;
        $newRef = 'CUS' . str_pad($newIdNumber, 12, '0', STR_PAD_LEFT);

        return $newRef;
    }

    /**
     * Validate customer creation parameters.
     *
     * @param array $params An array containing the customer creation parameters.
     */
    public static function validate( $params ) 
    {
        $message = "";

        $requiredParams = [
            'email',
            'password',
            'firstName',
            'lastName'
        ];

        foreach ( $requiredParams as $param ) {
            if ( empty( $params[$param] ) ) {
                $message = $param . ' is required.';
            }
        }

        if ( !empty($params['email'] ) && !filter_var( $params['email'], FILTER_VALIDATE_EMAIL ) ) {
            $message = 'The email address is not valid.';
        }

        if ( !empty( $message ) ) {
            $response = new JsonResponse([
                'success' => false,
                'message' => $message,
            ]);
    
            return $response;
        }
    }

}
