<?php

namespace Shopimind\PassiveSynchronization;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Thelia\Model\CustomerQuery;
use Symfony\Component\HttpFoundation\Request;
use Shopimind\Data\CustomersData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmCustomers;

class SyncCustomers
{
    /**
     * Process synchronization for customers
     *
     * @param $lastUpdate
     * @param $ids
     * @return array
     */
    public static function processSyncCustomers( $lastUpdate, $ids, $requestedBy ): array
    {
        $customerIds = null;
        if ( !empty( $ids ) ) {
            $customerIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
        }

        if ( empty( $lastUpdate ) ) {
            if ( empty( $customerIds ) ) {
                $count = CustomerQuery::create()->find()->count();
            }else {
                $count = CustomerQuery::create()->filterById( $customerIds )->find()->count();
            }
        }else {
            if ( empty( $customerIds ) ) {
                $count = CustomerQuery::create()->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }else {
                $count = CustomerQuery::create()->filterById( $customerIds )->filterByUpdatedAt( $lastUpdate, '>=')->count();
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
            isset($synchronizationStatus['synchronization_status']['customers'])
            && $synchronizationStatus['synchronization_status']['customers'] == 1
            ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus( 'customers', 1 );

        Utils::launchSynchronisation( 'customers', $lastUpdate, $customerIds, $requestedBy );

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes customers.
     *
     * @return void
     */
    public static function syncCustomers( Request $request )
    {
        try {
            $body =  json_decode( $request->getContent(), true );

            $lastUpdate = ( isset( $body['last_update'] ) ) ? $body['last_update'] : null;
            
            $customerIds = null;
            $ids = ( isset( $body['ids'] ) ) ? $body['ids'] : null;
            if ( !empty( $ids ) ) {
                $customerIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
            }

            $requestedBy = ( isset( $body['requestedBy'] ) ) ? $body['requestedBy'] : null;

            $offset = 0;
            $limit = 20;

            $hasMore = true;

            do {
                if ( empty( $lastUpdate ) ) {
                    if ( empty( $customerIds ) ) {
                        $customers = CustomerQuery::create()->offset( $offset )->limit( $limit )->find();
                    }else {
                        $customers = CustomerQuery::create()->filterById( $customerIds )->offset( $offset )->limit( $limit )->find();
                    }
                } else {
                    $lastUpdate = trim( $lastUpdate, '"\'');
                    if ( empty( $customerIds ) ) {
                        $customers = CustomerQuery::create()->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }else {
                        $customers = CustomerQuery::create()->filterById( $customerIds )->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }
                }
        
                if ( $customers->count() < $limit ) {
                    $hasMore = false;
                }else {
                    $offset += $limit;    
                }
        
                if ( $customers->count() > 0 ) {
                    $data = [];
                    foreach ( $customers as $customer ) {
                        $data[] = CustomersData::formatCustomer( $customer );
                    }
            
                    $requestHeaders = $requestedBy ? [ 'answered-for' => $requestedBy ] : [];
                    $response = SpmCustomers::bulkSave( Utils::getAuth( $requestHeaders ), $data );
                    
                    Utils::handleResponse( $response );
                    
                    Utils::log( 'customers', 'passive synchronization', json_encode( $response ) );
                }
            } while ( $hasMore );
            
        } catch (\Throwable $th) {
            Utils::log( 'customers' , 'passive synchronization', $th->getMessage() );
        } finally {
            Utils::log( 'customers', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus( 'customers', 0 );
        }
    }
}
