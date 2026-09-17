<?php

namespace Shopimind\SpmWebHook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Model\LangQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Newsletter;
use Thelia\Model\NewsletterQuery;
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
        // Appel signé de ShopiMind : corps et signature contrôlés avant tout traitement (réponse JSON 401 sinon).
        $body = Utils::getSpmRequestBody( $request );
        if ( null !== $unauthorized = Utils::authorizeSpmRequest( $request, $body ) ) {
            return $unauthorized;
        }

        try {
            return self::processCustomer( $body );
        } catch ( \Throwable $th ) {
            return Utils::spmErrorResponse( 'Webhook', 'Customer', $th );
        }
    }

    /**
     * @param array $body corps authentifié
     * @return JsonResponse
     */
    protected static function processCustomer( array $body )
    {
        $status = true;
        $message = "Customer created successfully.";
        $customerId = 0;
        $defaultLang = LangQuery::create()->findOneByByDefault(true)->getId();

        $customerData = ( isset( $body['customer'] ) && is_array( $body['customer'] ) ) ? $body['customer'] : [];
        $errors = self::validate( $customerData );

        if ( !empty( $errors ) ) {
            return $errors;
        }

        $email = $customerData['email'];
        $firstName = $customerData['firstName'];
        $lastName = $customerData['lastName'];
        $langParam = ( array_key_exists('lang', $customerData ) ) ? $customerData['lang'] : '';
        $lang = LangQuery::create()->findOneByCode( $langParam );
        $langId = !empty($lang) ? $lang->getId() : $defaultLang;
        $local = !empty($lang ) ? $lang->getLocale() : LangQuery::create()->findOneByByDefault(true)->getLocale();
        $password = $customerData['password'];
        $newsletter = ( array_key_exists('newsletter', $customerData ) ) ? $customerData['newsletter'] : 0;

        $emailExists = CustomerQuery::create()->filterByEmail($email)->exists();

        if ( !$emailExists ) {
            try {
                $customer = new Customer();
                $customer->setTitleId(1);
                $customer->setLangId($langId);
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
                $customerId = (int) $customer->getId();
            } catch (\Throwable $th) {
                Utils::logException( 'Webhook', 'Customer', $th );
                $status = false;
                $message = Utils::toUtf8( $th->getMessage() );
            }

            // Compte créé : un échec de l'inscription à la newsletter est journalisé sans annuler la création.
            if ( $status && $newsletter == 1 ) {
                try {
                    // L'e-mail peut déjà figurer dans la table newsletter (prospect inscrit avant de
                    // créer son compte) : contrainte UNIQUE, la ligne existante est réactivée.
                    $subscription = NewsletterQuery::create()->findOneByEmail( $email );
                    if ( empty( $subscription ) ) {
                        $subscription = new Newsletter();
                        $subscription->setEmail( $email );
                        $subscription->setCreatedAt( new \DateTime() );
                    }
                    $subscription->setFirstname( $firstName );
                    $subscription->setLastname( $lastName );
                    $subscription->setLocale( $local );
                    $subscription->setUnsubscribed( 0 );
                    $subscription->setUpdatedAt( new \DateTime() );

                    $subscription->save();
                } catch (\Throwable $th) {
                    Utils::logException( 'Webhook', 'NewsletterSubscriber', $th, $customerId );
                }
            }
        } else {
            $status = false;
            $message = "Email already exist.";
        }

        $response = [
            'success' => $status,
            'message' => $message,
        ];

        // Identifiant du client créé, qui sert de clé au client chez ShopiMind : false si la création échoue, absent
        // si l'e-mail existe déjà.
        if ( !$emailExists ) {
            if ( !$status ) {
                $response['id_customer'] = false;
            } elseif ( $customerId > 0 ) {
                $response['id_customer'] = $customerId;
            }
        }

        return new JsonResponse( $response );
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
