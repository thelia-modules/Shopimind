<?php

namespace Shopimind\PassiveSynchronization;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Shopimind\lib\Utils;
use Shopimind\PassiveSynchronization\SyncCustomers;
use Shopimind\PassiveSynchronization\SyncCustomersAddresses;
use Shopimind\PassiveSynchronization\SyncCustomersGroups;
use Shopimind\PassiveSynchronization\SyncNewsletterSubscribers;
use Shopimind\PassiveSynchronization\SyncOrders;
use Shopimind\PassiveSynchronization\SyncOrderStatus;
use Shopimind\PassiveSynchronization\SyncOrdersCarriers;
use Shopimind\PassiveSynchronization\SyncProductsImages;
use Shopimind\PassiveSynchronization\SyncProducts;
use Shopimind\PassiveSynchronization\SyncProductsVariations;
use Shopimind\PassiveSynchronization\SyncProductsCategories;
use Shopimind\PassiveSynchronization\SyncProductsManufacturers;
use Shopimind\PassiveSynchronization\SyncVouchers;

class RequestHandler extends AbstractController
{
    /**
     * Controller to handle synchronization requests.
     *
     * @param Request $request
     * @param EventDispatcherInterface|null $dispatcher
     * @return JsonResponse
     */
    public function requestController( Request $request, EventDispatcherInterface $dispatcher )
    {
        // Même contrôle que les webhooks (Utils::authorizeSpmRequest) : en-têtes lus par le HeaderBag de Symfony,
        // insensible à la casse (getallheaders() ne trouvait pas les noms reçus en minuscules sous HTTP/2 ou
        // derrière certains proxys), signature vérifiée sur le corps brut, réponse JSON 401 en cas d'échec.
        $body = Utils::getSpmRequestBody( $request );
        if ( null !== $unauthorized = Utils::authorizeSpmRequest( $request, $body ) ) {
            return $unauthorized;
        }

        $errors = self::validate( $body );
        if ( !empty( $errors ) ) {
            return $errors;
        }

        $type = is_scalar( $body['type'] ) ? (string) $body['type'] : '';

        $limit = ( array_key_exists( 'limit', $body ) ) ? (int) $body['limit'] : 20;

        // Clés envoyées par ShopiMind : lastUpdate en camelCase, et id_shop_ask_syncs que les
        // classes Sync* relaient dans l'en-tête X-Shopimind-Sync-Id. Sans cet identifiant,
        // ShopiMind ne rattache pas les objets reçus à l'étape de synchronisation en cours.
        $param = [
            'start' => ( array_key_exists( 'start', $body ) ) ? max( 0, (int) $body['start'] ) : 0,
            'limit' => $limit > 0 ? $limit : 20,
            'ids' => ( array_key_exists( 'ids', $body ) ) ? $body['ids'] : '',
            'lastUpdate' => self::normalizeLastUpdate( $body['lastUpdate'] ?? ( $body['last_update'] ?? '' ) ),
            'just_count' => ( array_key_exists( 'just_count', $body ) ) ? $body['just_count'] : false,
            'id_shop_ask_syncs' => ( array_key_exists( 'id_shop_ask_syncs', $body ) ) ? $body['id_shop_ask_syncs'] : null,
        ];

        $response = [];
        try {
            switch ( $type ) {
                case 'customers':
                    $response = SyncCustomers::processSyncCustomers( $param );
                    break;
                case 'customers_addresses':
                    $response = SyncCustomersAddresses::processSyncCustomersAddresses( $param );
                    break;
                case 'customers_groups':
                    if ( Utils::isCustomerFamilyActive() ) {
                        $response = SyncCustomersGroups::processSyncCustomersGroups( $param );
                    } else {
                        $response = [
                            'success' => true,
                            'total_count' => 0,
                        ];
                    }
                    break;
                case 'newsletter_subscribers':
                    $response = SyncNewsletterSubscribers::processSyncNewsletterSubscribers( $param );
                    break;
                case 'orders':
                    $response = SyncOrders::processSyncOrders( $param );
                    break;
                case 'orders_statuses':
                    $response = SyncOrderStatus::processSyncOrderStatus( $param );
                    break;
                case 'orders_carriers':
                    $response = SyncOrdersCarriers::processSyncOrdersCarriers( $param );
                    break;
                case 'products':
                    $response = SyncProducts::processSyncProducts( $param, $dispatcher );
                    break;
                case 'products_variations':
                    $response = SyncProductsVariations::processSyncProductsVariations( $param, $dispatcher );
                    break;
                case 'products_images':
                    $response = SyncProductsImages::processSyncProductsImages( $param, $dispatcher );
                    break;
                case 'products_categories':
                    $response = SyncProductsCategories::processSyncProductsCategories( $param );
                    break;
                case 'products_manufacturers':
                    $response = SyncProductsManufacturers::processSyncProductsManufacturers( $param );
                    break;
                case 'vouchers':
                    $response = SyncVouchers::processSyncVouchers( $param );
                    break;
                default:
                    return new JsonResponse([
                        'success' => true,
                        'total_count' => 0,
                    ]);
            }
        } catch ( \Throwable $th ) {
            Utils::logException( 'Passive', 'RequestHandler', $th );
            return new JsonResponse([
                'success' => false,
                'message' => 'Error processing ' . $type . ': ' . Utils::toUtf8( $th->getMessage() ),
            ], 500);
        }

        return new JsonResponse( $response );
    }

    /**
     * ShopiMind envoie lastUpdate en ISO 8601 UTC (ex. 2026-09-13T22:00:00.000Z).
     * Thelia enregistre updated_at dans le fuseau PHP de la boutique, au format DATETIME :
     * conversion vers ce fuseau avant le filtre filterByUpdatedAt des classes Sync*.
     * Valeur vide ou illisible : aucun filtre, synchronisation complète.
     *
     * @param mixed $lastUpdate
     * @return string
     */
    public static function normalizeLastUpdate( $lastUpdate ): string
    {
        $lastUpdate = is_scalar( $lastUpdate ) ? trim( (string) $lastUpdate, " \t\n\r\0\x0B\"'" ) : '';
        if ( $lastUpdate === '' || $lastUpdate === 'null' ) {
            return '';
        }

        try {
            $date = new \DateTime( $lastUpdate );
            $date->setTimezone( new \DateTimeZone( date_default_timezone_get() ) );

            return $date->format( 'Y-m-d H:i:s' );
        } catch ( \Exception $e ) {
            Utils::logWarn( 'Passive', 'RequestHandler', 'lastUpdate illisible, synchronisation complète', [ 'lastUpdate' => $lastUpdate ] );

            return '';
        }
    }

    /**
     * Validate synchronize parameters.
     *
     * @param array $params An array containing the parameters.
     * @return JsonResponse|null
     */
    public static function validate( $params )
    {
        $message = "";

        $requiredParams = [ 'type' ];
        foreach ( $requiredParams as $param ) {
            if ( !array_key_exists( $param, $params ) || empty( $params[$param] ) ) {
                $message = $param . ' is required. ';
            }
        }

        if ( !empty( $message ) ) {
            return new JsonResponse([
                'success' => false,
                'message' => $message,
            ]);
        }

        return null;
    }
}
