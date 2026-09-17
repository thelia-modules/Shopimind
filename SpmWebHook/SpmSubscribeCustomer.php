<?php

namespace Shopimind\SpmWebHook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Newsletter;
use Thelia\Model\NewsletterQuery;
use Shopimind\lib\Utils;

class SpmSubscribeCustomer
{
    /**
     * Subscribe customer to the newsletter
     *
     * Appel ShopiMind : POST application/x-www-form-urlencoded, id_customer=<id>.
     * Acceptés aussi : un e-mail dans id_customer et action=subscribe|unsubscribe
     * (subscribe par défaut).
     *
     * @param Request $request
     *
     */
    public static function subscribeCustomer(Request $request)
    {
        // Corps form-urlencoded, celui dont la signature est contrôlée avant tout traitement (réponse JSON 401
        // sinon) : json_decode() renvoyait null et array_key_exists() levait une TypeError (HTTP 500).
        $body = Utils::getSpmRequestBody( $request );
        if ( null !== $unauthorized = Utils::authorizeSpmRequest( $request, $body ) ) {
            return $unauthorized;
        }

        try {
            return self::processSubscription( $body );
        } catch ( \Throwable $th ) {
            return Utils::spmErrorResponse( 'Webhook', 'NewsletterSubscriber', $th );
        }
    }

    /**
     * @param array $body corps authentifié
     * @return JsonResponse
     */
    protected static function processSubscription( array $body )
    {
        $customerId = isset( $body['id_customer'] ) ? trim( (string) $body['id_customer'] ) : '';
        $action = isset( $body['action'] ) ? (string) $body['action'] : 'subscribe';

        if ( $customerId === '' ) {
            return new JsonResponse([
                'success' => false,
                'message' => "Invalid customer id",
            ]);
        }

        if ( !in_array( $action, [ 'subscribe', 'unsubscribe' ], true ) ) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid action. Must be "subscribe" or "unsubscribe".',
            ]);
        }

        if ( strpos( $customerId, '@' ) !== false ) {
            $email = $customerId;
            $customer = CustomerQuery::create()->findOneByEmail( $email );
        } else {
            $customer = CustomerQuery::create()->findOneById( (int) $customerId );
            if ( empty( $customer ) ) {
                return new JsonResponse([
                    'success' => false,
                    'message' => "The customer does not exist.",
                ]);
            }
            $email = $customer->getEmail();
        }

        $status = true;
        $message = $action === 'subscribe' ? "Customer subscribed successfully." : "Customer unsubscribed successfully.";

        try {
            // Contrainte UNIQUE sur newsletter.email : la ligne existante (client déjà inscrit ou
            // désinscrit) est mise à jour au lieu d'être insérée une seconde fois.
            $newsletter = NewsletterQuery::create()->findOneByEmail( $email );

            if ( $action === 'unsubscribe' ) {
                if ( !empty( $newsletter ) && !$newsletter->getUnsubscribed() ) {
                    $newsletter->setUnsubscribed( 1 );
                    $newsletter->setUpdatedAt( new \DateTime() );
                    $newsletter->save();
                }
            } else {
                if ( empty( $newsletter ) ) {
                    $newsletter = new Newsletter();
                    $newsletter->setEmail( $email );
                    if ( !empty( $customer ) ) {
                        $newsletter->setFirstname( $customer->getFirstname() );
                        $newsletter->setLastname( $customer->getLastname() );
                        $newsletter->setLocale( $customer->getLocale() );
                    }
                    $newsletter->setCreatedAt( new \DateTime() );
                }
                $newsletter->setUnsubscribed( 0 );
                $newsletter->setUpdatedAt( new \DateTime() );
                $newsletter->save();
            }
        } catch (\Throwable $th) {
            $status = false;
            $message = $th->getMessage();
        }

        $response = new JsonResponse([
            'success' => $status,
            'message' => $message,
        ]);

        return $response;
    }
}
