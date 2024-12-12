<?php

namespace Shopimind\PassiveSynchronization;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Shopimind\lib\Utils;
use Shopimind\Model\Base\ShopimindQuery;
use Shopimind\Model\ShopimindSyncStatusQuery;

class SyncStatus
{
    /**
     * Retrieves the current synchronization statuses.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public static function getSyncStatus( Request $request )
    {
        $validateRequest = self::validateRequest( $request );
        if ( !empty( $validateRequest ) ) return $validateRequest;
        
        $idShopAskSyncs = $request->headers->get('id-shop-ask-syncs');
        $syncStatus = ShopimindSyncStatusQuery::create()->findOneById( $idShopAskSyncs );
        if ( !empty( $syncStatus ) ) {
            $firstCall = $syncStatus->getFirstCall() ? $syncStatus->getFirstCall()->format('Y-m-d\TH:i:s.u\Z') : null;

            $response = [
                "id" => $syncStatus->getId(),
                "current_data_type" => $syncStatus->getCurrentDataType(),
                "global_state" => $syncStatus->getGlobalState(),
                "firt_call" => $firstCall,
                "statuses" => $syncStatus->getStatuses(),
            ];

            return new JsonResponse( $response );
        }else {
            return new JsonResponse([
                'success' =>  false,
                'message' => $idShopAskSyncs . ' is not found',
            ], 401);
        }
    }

    /**
     * Update the global_state of sync-status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public static function updateSyncStatus( Request $request )
    {
        $validateRequest = self::validateRequest( $request );
        if ( !empty( $validateRequest ) ) return $validateRequest;

        $body =  json_decode( $request->getContent(), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new JsonResponse([
                'success' =>  false,
                'message' => 'Invalid JSON.',
            ], 400);
        }

        if ( !isset( $body['status'] ) || empty( $body['status'] ) ) {
            return new JsonResponse([
                'success' =>  false,
                'message' => 'status is required.',
            ]);
        }

        $idShopAskSyncs = $request->headers->get('id-shop-ask-syncs');
        $syncStatus = ShopimindSyncStatusQuery::create()->findOneById( $idShopAskSyncs );
        if ( !empty( $syncStatus ) ) {
            try {
                $syncStatus->setGlobalState( $body['status'] );
                $syncStatus->save();

                return new JsonResponse( ['success' => true] );
            } catch (\Throwable $th) {
                return new JsonResponse([
                    'success' =>  false,
                    'message' => $th->getMessage(),
                ]);    
            }
        }else {
            return new JsonResponse([
                'success' =>  false,
                'message' => $idShopAskSyncs . ' is not found',
            ]);
        }
    }

    /**
     * set object sync status to failed
     *
     * @param Request $request
     * @return JsonResponse
     */
    public static function setObjectSyncStatusToFailed( Request $request )
    {
        $validateRequest = self::validateRequest( $request );
        if ( !empty( $validateRequest ) ) return $validateRequest;

        $body =  json_decode( $request->getContent(), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new JsonResponse([
                'success' =>  false,
                'message' => 'Invalid JSON.',
            ], 400);
        }

        if ( !isset( $body['object'] ) || empty( $body['object'] ) ) {
            return new JsonResponse([
                'success' =>  false,
                'message' => 'object is required.',
            ]);
        }
        $object = $body['object'];

        $idShopAskSyncs = $request->headers->get('id-shop-ask-syncs');
        $syncStatus = ShopimindSyncStatusQuery::create()->findOneById( $idShopAskSyncs );
        if ( !empty( $syncStatus ) ) {
            try {
                $statuses = $syncStatus->getStatuses();
                if ( !empty( $statuses ) && isset($statuses[$object]) ) {
                    $statuses[ $object ]['status'] = 'failed';
                    $statuses[ $object ]['relaunch_count'] = intval( $statuses[ $object ]['relaunch_count'] ) + 1;
                    $syncStatus->setStatuses( $statuses );
                    $syncStatus->save();

                    Utils::updateSynchronizationStatus( $object, 0 );

                    return new JsonResponse( ['success' => true] );
                }else {
                    return new JsonResponse( ['success' => false] );                    
                }
            } catch (\Throwable $th) {
                return new JsonResponse([
                    'success' =>  false,
                    'message' => $th->getMessage(),
                ]);    
            }
        }else {
            return new JsonResponse([
                'success' =>  false,
                'message' => $idShopAskSyncs . ' is not found',
            ]);
        }

        return new JsonResponse([
            'success' =>  true,
        ]);
    }

    /**
     * Validate request.
     *
     * @param Request $request
     */
    public static function validateRequest( Request $request ) 
    {
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

        $idShopAskSyncs = $request->headers->get('id-shop-ask-syncs');
        if( empty( $idShopAskSyncs ) ){
            return new JsonResponse([
                'success' =>  false,
                'message' => 'id-shop-ask-syncs is required',
            ], 401);
        }

        return null;
    }
}