<?php

namespace Shopimind\PassiveSynchronization;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Shopimind\Model\Base\ShopimindQuery;
use Shopimind\lib\Utils;
use Shopimind\PassiveSynchronization\SyncCustomers;
use Shopimind\PassiveSynchronization\SyncCustomersAddresses;
use Shopimind\PassiveSynchronization\SyncNewsletterSubscribers;
use Shopimind\PassiveSynchronization\SyncOrders;
use Shopimind\PassiveSynchronization\SyncOrderStatus;
use Shopimind\PassiveSynchronization\SyncProductsImages;
use Shopimind\PassiveSynchronization\SyncProducts;
use Shopimind\PassiveSynchronization\SyncProductsVariations;
use Shopimind\PassiveSynchronization\SyncProductsCategories;
use Shopimind\PassiveSynchronization\SyncProductsManufacturers;
use Shopimind\PassiveSynchronization\SyncVouchers;
use Shopimind\Model\ShopimindSyncStatus;

class RequestHandler
{
    /**
     * Controller to handle synchronization requests.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public static function requestController(Request $request){
        if ( !Utils::isConnected() ) {
            return new JsonResponse([
                'success' =>  false,
                'message' => 'Module is not connected.',
            ]);
        }

        $config = ShopimindQuery::create()->findOne();
        $apiKey = $request->headers->get('api-spm-key');
        if ( !( $apiKey === sha1($config->getApiPassword()) ) ) {
            return new JsonResponse([
                'success' =>  false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $body =  json_decode( $request->getContent(), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new JsonResponse([
                'success' =>  false,
                'message' => 'Invalid JSON.',
            ], 400);
        }

        $errors = self::validate( $body );
        if ( !empty( $errors ) ) {
            return $errors;
        }
        
        $type = $body['data']['type'];
        $lastUpdate = ( array_key_exists( 'last_update', $body['data'] ) ) ?  $body['data']['last_update'] : '';
        $ids = ( array_key_exists( 'ids', $body['data'] ) ) ?  $body['data']['ids'] : '';
        $requestedBy = ( array_key_exists( 'requested-by', $body['data'] ) ) ?  $body['data']['requested-by'] : null;

        $idShopAskSyncs = $request->headers->get('id-shop-ask-syncs');
        
        $response = "";
        switch ($type) {
            case 'customers':
                $response = SyncCustomers::processSyncCustomers( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                break;
            case 'customers_addresses':
                $response = SyncCustomersAddresses::processSyncCustomersAddresses( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                break;
            case 'customers_groups':
                if ( Utils::isCustomerFamilyActive() ) {
                    $response = SyncCustomersGroups::processSyncCustomersGroups( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                }else {
                    if ( !empty( $idShopAskSyncs ) ) {
                        $objectStatus = [
                            "status" => "completed",
                        ];
                        ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'customers_groups', $objectStatus );
                    }

                    $response = [
                        'success' => true,
                        'count' => 0,
                    ];
                }
                break;
            case 'newsletter_subscribers':
                $response = SyncNewsletterSubscribers::processSyncNewsletterSubscribers( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                break;
            case 'orders':
                $response = SyncOrders::processSyncOrders( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                break;
            case 'orders_statuses':
                $response = SyncOrderStatus::processSyncOrderStatus( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                break;
            case 'products':
                $response = SyncProducts::processSyncProducts( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                break;
            case 'products_variations':
                $response = SyncProductsVariations::processSyncProductsVariations( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                break;
            case 'products_images':
                $response = SyncProductsImages::processSyncProductsImages( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                break;
            case 'products_categories':
                $response = SyncProductsCategories::processSyncProductsCategories( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                break;
            case 'products_manufacturers':
                $response = SyncProductsManufacturers::processSyncProductsManufacturers( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                break;
            case 'vouchers':
                $response = SyncVouchers::processSyncVouchers( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs );
                break;
        }

        return new JsonResponse($response);
    }

    /**
     * Validate syncrhonize parameters.
     *
     * @param array $params An array containing the parameters.
     */
    public static function validate( $params ) 
    {
        $message = "";

        if (!isset($params['data']) || !isset($params['hmac'])) {
            if (!isset($params['data'])) {
                $message = "data is required. ";
            }
            if (!isset($params['hmac'])) {
                $message = "hmac is required. ";
            }
        } else {
            $requiredParams = [
                'type'
            ];
            foreach ($requiredParams as $param) {
                if ( !array_key_exists( 'type', $params['data'] ) || empty($params['data'][$param]) ) {
                    $message = $param . ' is required. ';
                }
            }
        }

        if ( !empty( $message ) ) {
            return new JsonResponse([
                'success' => false,
                'message' => $message,
            ]);
        }
    }
}
