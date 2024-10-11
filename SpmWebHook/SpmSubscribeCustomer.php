<?php

namespace Shopimind\SpmWebHook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Newsletter;
use Shopimind\lib\Utils;

class SpmSubscribeCustomer
{
    /**
     * Subscribe customer to the newsletter
     *
     * @param Request $request
     * 
     */
    public static function subscribeCustomer(Request $request)
    {
        $requestValidation = Utils::validateSpmRequest( $request );
        if ( !empty( $requestValidation ) ) return $requestValidation;

        $body =  json_decode( $request->getContent(), true );

        $status = true;
        $message = "Customer subscribed successfully.";

        $customerId = ( array_key_exists('id_customer', $body ) ) ? $body['id_customer'] : '';
        if ( !empty( $customerId ) ) {
            $customer = CustomerQuery::create()->findOneById( $customerId );
            if ( !empty( $customer ) ) {
                try {
                    $newsletter = new Newsletter();
                    $newsletter->setEmail( $customer->getEmail() );
                    $newsletter->setFirstname( $customer->getFirstname() );
                    $newsletter->setLastname( $customer->getLastname() );
                    $newsletter->setLocale( $customer->getLocale() );
                    $newsletter->setUnsubscribed( 0 );
                    $newsletter->setCreatedAt( new \DateTime() );
                    $newsletter->setUpdatedAt( new \DateTime() );

                    $newsletter->save();
                } catch (\Throwable $th) {
                    $status = false;
                    $message = $th->getMessage();
                }
            }else {
                $status = false;
                $message = "The customer does not exist.";    
            }
        }else {
            $status = false;
            $message = "Invalid customer id";
        }

        $response = new JsonResponse([
            'success' => $status,
            'message' => $message,
        ]);

        return $response;
    }
}