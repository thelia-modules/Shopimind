<?php

namespace Shopimind\PassiveSynchronization;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Thelia\Model\OrderStatusQuery;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Base\LangQuery;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmOrdersStatus;
use Shopimind\Data\OrderStatusData;
use Shopimind\Model\ShopimindSyncStatus;

class SyncOrderStatus
{
    /**
     * Process synchronization for order status
     *
     * @param $lastUpdate
     * @param $ids
     * @param $idShopAskSyncs
     * @return array
     */
    public static function processSyncOrderStatus( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs ): array
    {
        $orderStatuesesIds = null;
        if ( !empty( $ids ) ) {
            $orderStatuesesIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
        }

        if ( empty( $lastUpdate ) ) {
            if ( empty( $orderStatuesesIds ) ) {
                $count = OrderStatusQuery::create()->find()->count();
            }else {
                $count = OrderStatusQuery::create()->filterById( $orderStatuesesIds )->find()->count();
            }
        } else {
            if ( empty( $orderStatuesesIds ) ) {
                $count = OrderStatusQuery::create()->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }else {
                $count = OrderStatusQuery::create()->filterById( $orderStatuesesIds )->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }
        }

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $count = $count * $langs->count();

        if ( !empty( $idShopAskSyncs ) ) {
            ShopimindSyncStatus::updateShopimindSyncStatus( $idShopAskSyncs, 'orders_statuses' );
            
            $objectStatus = ShopimindSyncStatus::getObjectStatus( $idShopAskSyncs, 'orders_statuses' );
            $oldCount = !empty( $objectStatus ) ? $objectStatus['total_objects_count'] : 0;
            if( $oldCount > 0 ){
                $count = $oldCount;
            }

            $objectStatus = [
                "status" => "in_progress",
                "total_objects_count" => $count,
            ];
            ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'orders_statuses', $objectStatus );
        }

        if ( $count == 0 ) {
            if ( !empty( $idShopAskSyncs ) ) {
                $objectStatus = [
                    "status" => "completed",
                ];
                ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'orders_statuses', $objectStatus );
            }
            
            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();
        
        if (
            $synchronizationStatus &&
            isset($synchronizationStatus['synchronization_status']['orders_statuses'])
            && $synchronizationStatus['synchronization_status']['orders_statuses'] == 1
            ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus( 'orders_statuses', 1 );

        Utils::launchSynchronisation( 'orders-statuses', $lastUpdate, $orderStatuesesIds, $requestedBy, $idShopAskSyncs );

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes order statuses.
     *
     * @return void
     */
    public function syncOrderStatus( Request $request )
    {
        try {
            $body =  json_decode( $request->getContent(), true );

            $lastUpdate = ( isset( $body['last_update'] ) ) ? $body['last_update'] : null;

            $orderStatuesesIds = null;
            $ids = ( isset( $body['ids'] ) ) ? $body['ids'] : null;
            if ( !empty( $ids ) ) {
                $orderStatuesesIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
            }

            $requestedBy = ( isset( $body['requestedBy'] ) ) ? $body['requestedBy'] : null;

            $idShopAskSyncs = ( isset( $body['idShopAskSyncs'] ) ) ? $body['idShopAskSyncs'] : null;

            $langs = LangQuery::create()->filterByActive( 1 )->find();
            $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();

            $offset = 0;
            $limit = intdiv( 20, $langs->count() );

            $hasMore = true;

            do {
                if ( empty( $lastUpdate ) ) {
                    if ( empty( $orderStatuesesIds ) ) {
                        $ordersStatuses = OrderStatusQuery::create()
                            ->orderByUpdatedAt()
                            ->offset( $offset )
                            ->limit( $limit )
                            ->find();
                    }else {
                        $ordersStatuses = OrderStatusQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById( $orderStatuesesIds )
                            ->offset( $offset )
                            ->limit( $limit )
                            ->find();
                    }
                } else {
                    $lastUpdate = trim( $lastUpdate, '"\'');
                    if ( empty( $orderStatuesesIds ) ) {
                        $ordersStatuses = OrderStatusQuery::create()
                            ->orderByUpdatedAt()
                            ->offset( $offset )
                            ->limit( $limit )
                            ->filterByUpdatedAt( $lastUpdate, '>=' );
                    }else {
                        $ordersStatuses = OrderStatusQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById( $orderStatuesesIds )
                            ->offset( $offset )
                            ->limit( $limit )
                            ->filterByUpdatedAt( $lastUpdate, '>=' );
                    }
                }
        
                if ( $ordersStatuses->count() < $limit ) {
                    $hasMore = false;
                } else {
                    $offset += $limit;    
                }
        
                if ( $ordersStatuses->count() > 0 ) {
                    $data = [];
        
                    foreach ( $ordersStatuses as $ordersStatus ) {
                        $orderStatusDefault = $ordersStatus->getTranslation( $defaultLocal );
        
                        foreach ( $langs as $lang ) {
                            $orderStatusTranslated = $ordersStatus->getTranslation( $lang->getLocale() );
        
                            $data[] = OrderStatusData::formatOrderStatus( $ordersStatus, $orderStatusTranslated, $orderStatusDefault );
                        }
                    }
        
                    $requestHeaders = $requestedBy ? [ 'answered-for' => $requestedBy ] : [];
                    $response = SpmOrdersStatus::bulkSave( Utils::getAuth( $requestHeaders ), $data );
                    
                    if ( !empty( $idShopAskSyncs ) ) {
                        ShopimindSyncStatus::updateObjectStatusesCount( $idShopAskSyncs, 'orders_statuses', $response, count( $data ) );

                        $lastObject = end( $data );
                        $lastObjectUpdate = $lastObject['updated_at'];
                        $objectStatus = [
                            "last_object_update" => $lastObjectUpdate,
                        ];
                        ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'orders_statuses', $objectStatus );  
                    }

                    Utils::handleResponse( $response );
        
                    Utils::log( 'orderStatuses' , 'passive synchronization', json_encode( $response ) );
                }
            } while ( $hasMore );
        
        } catch (\Throwable $th) {
            Utils::log( 'orderStatuses' , 'passive synchronization', $th->getMessage() );
        }  finally {
            if ( !empty( $idShopAskSyncs ) ) {
                $objectStatus = [
                    "status" => "completed",
                ];
                ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'orders_statuses', $objectStatus );
            }

            Utils::log( 'orderStatuses', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus( 'orders_statuses', 0 );
        }

    }
}
