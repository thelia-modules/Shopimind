<?php

namespace Shopimind\PassiveSynchronization;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\OrderQuery;
use Symfony\Component\HttpFoundation\Request;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmOrders;
use Shopimind\Data\OrdersData;

class SyncOrders
{
    /**
     * Process synchronization for orders
     *
     * @param $lastUpdate
     * @param $ids
     * @return array
     */
    public static function processSyncOrders( $lastUpdate, $ids, $requestedBy ): array
    {
        $ordersIds = null;
        if ( !empty( $ids ) ) {
            $ordersIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
        }

        if ( empty( $lastUpdate ) ) {
            if ( empty( $ordersIds ) ) {
                $count = OrderQuery::create()->find()->count();
            }else {
                $count = OrderQuery::create()->filterById( $ordersIds )->find()->count();
            }
        } else {
            if ( empty( $ordersIds ) ) {
                $count = OrderQuery::create()->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }else {
                $count = OrderQuery::create()->filterById( $ordersIds )->filterByUpdatedAt( $lastUpdate, '>=')->count();                
            }
        }

        if ( $count == 0 ) {
            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();
        
        if (
            $synchronizationStatus &&
            isset($synchronizationStatus['synchronization_status']['orders'])
            && $synchronizationStatus['synchronization_status']['orders'] == 1
            ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus( 'orders', 1 );

        Utils::launchSynchronisation( 'orders', $lastUpdate, $ordersIds, $requestedBy );

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes orders.
     *
     * @return void
     */
    public static function syncOrders( Request $request )
    {
        try {
            $body =  json_decode( $request->getContent(), true );

            $lastUpdate = ( isset( $body['last_update'] ) ) ? $body['last_update'] : null;

            $ordersIds = null;
            $ids = ( isset( $body['ids'] ) ) ? $body['ids'] : null;
            if ( !empty( $ids ) ) {
                $ordersIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
            }

            $requestedBy = ( isset( $body['requestedBy'] ) ) ? $body['requestedBy'] : null;

            $offset = 0;
            $limit = 20;

            $hasMore = true;

            do {
                if ( empty( $lastUpdate ) ) {
                    if ( empty( $ordersIds ) ) {
                        $orders = OrderQuery::create()->offset( $offset )->limit( $limit )->find();
                    }else {
                        $orders = OrderQuery::create()->filterById( $ordersIds )->offset( $offset )->limit( $limit )->find();
                    }
                } else {
                    $lastUpdate = trim( $lastUpdate, '"\'');
                    if ( empty( $ordersIds ) ) {
                        $orders = OrderQuery::create()->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }else {
                        $orders = OrderQuery::create()->filterById( $ordersIds )->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );                        
                    }
                }
        
                if ( $orders->count() < $limit ) {
                    $hasMore = false;
                } else {
                    $offset += $limit;    
                }
                
                if ( $orders->count() > 0 ) {
                    $data = [];
                    foreach ( $orders as $order ) {
                        $data[] = OrdersData::formatOrder( $order );
                    }

                    $requestHeaders = $requestedBy ? [ 'answered-for' => $requestedBy ] : [];
                    $response = SpmOrders::bulkSave( Utils::getAuth( $requestHeaders ), $data );
                    
                    Utils::handleResponse( $response );
        
                    Utils::log( 'orders' , 'passive synchronization', json_encode( $response ) );
                }
            } while ( $hasMore );
        
        } catch (\Throwable $th) {
            Utils::log( 'orders' , 'passive synchronization', $th->getMessage() );
        } finally {
            Utils::log( 'orders', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus( 'orders', 0 );
        }
    }
}
