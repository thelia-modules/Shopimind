<?php

namespace Shopimind\PassiveSynchronization;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Thelia\Model\AddressQuery;
use Symfony\Component\HttpFoundation\Request;
use Shopimind\SdkShopimind\SpmCustomersAddresses;
use Shopimind\Data\CustomersAddressesData;
use Shopimind\lib\Utils;
use Shopimind\Model\ShopimindSyncStatus;

class SyncCustomersAddresses
{
    /**
     * Process synchronization for customers addresses
     *
     * @param $lastUpdate
     * @param $ids
     * @param $idShopAskSyncs
     * @return array
     */
    public static function processSyncCustomersAddresses( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs ): array
    {
        $customerAddressesIds = null;
        if ( !empty( $ids ) ) {
            $customerAddressesIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
        }

        if ( empty( $lastUpdate ) ) {
            if ( empty( $customerAddressesIds ) ) {
                $count = AddressQuery::create()->find()->count();
            }else {
                $count = AddressQuery::create()->filterById( $customerAddressesIds )->find()->count();
            }
        } else {
            if ( empty( $customerAddressesIds ) ) {
                $count = AddressQuery::create()->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }else {
                $count = AddressQuery::create()->filterById( $customerAddressesIds )->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }
        }

        if ( !empty( $idShopAskSyncs ) ) {
            ShopimindSyncStatus::updateShopimindSyncStatus( $idShopAskSyncs, 'customers_addresses' );
            
            $objectStatus = [
                "status" => "in_progress",
                "total_objects_count" => $count,
            ];
            ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'customers_addresses', $objectStatus );
        }

        if ( $count == 0 ) {
            if ( !empty( $idShopAskSyncs ) ) {
                $objectStatus = [
                    "status" => "completed",
                ];
                ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'customers_addresses', $objectStatus );
            }
            
            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();
        
        if (
            $synchronizationStatus &&
            isset($synchronizationStatus['synchronization_status']['customers_addresses'])
            && $synchronizationStatus['synchronization_status']['customers_addresses'] == 1
            ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus( 'customers_addresses', 1 );

        Utils::launchSynchronisation( 'customers-addresses', $lastUpdate, $customerAddressesIds, $requestedBy, $idShopAskSyncs );

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes customers addresses.
     *
     * @return void
     */
    public static function syncCustomersAddresses( Request $request )
    {
        try {
            $body =  json_decode( $request->getContent(), true );

            $lastUpdate = ( isset( $body['last_update'] ) ) ? $body['last_update'] : null;

            $customerAddressesIds = null;
            $ids = ( isset( $body['ids'] ) ) ? $body['ids'] : null;
            if ( !empty( $ids ) ) {
                $customerAddressesIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
            }

            $requestedBy = ( isset( $body['requestedBy'] ) ) ? $body['requestedBy'] : null;

            $idShopAskSyncs = ( isset( $body['idShopAskSyncs'] ) ) ? $body['idShopAskSyncs'] : null;

            $offset = 0;
            $limit = 20;

            $hasMore = true;

            do {
                if ( empty( $lastUpdate ) ) {
                    if ( empty( $customerAddressesIds ) ) {
                        $customersAddresses = AddressQuery::create()->offset( $offset )->limit( $limit )->orderBy('customer_id')->find();
                    }else {
                        $customersAddresses = AddressQuery::create()->filterById( $customerAddressesIds )->offset( $offset )->limit( $limit )->orderBy('customer_id')->find();                        
                    }
                } else {
                    $lastUpdate = trim( $lastUpdate, '"\'');
                    if ( empty( $customerAddressesIds ) ) {
                        $customersAddresses = AddressQuery::create()->offset( $offset )->limit( $limit )->orderBy('customer_id')->filterByUpdatedAt( $lastUpdate, '>=' );
                    }else {
                        $customersAddresses = AddressQuery::create()->filterById( $customerAddressesIds )->offset( $offset )->limit( $limit )->orderBy('customer_id')->filterByUpdatedAt( $lastUpdate, '>=' );                        
                    }
                }
        
                if ( $customersAddresses->count() < $limit ) {
                    $hasMore = false;
                } else {
                    $offset += $limit;    
                }
        
                if ( $customersAddresses->count() > 0 ) {
                    $data = [];
                    foreach ( $customersAddresses as $customerAddress ) {
                        $customerId = $customerAddress->getCustomerId();
                        $data[ $customerId ][] = CustomersAddressesData::formatCustomerAddress( $customerAddress );
                    }
        
                    foreach ( $data as $customerId => $value ) {
                        $requestHeaders = $requestedBy ? [ 'answered-for' => $requestedBy ] : [];
                        $response = SpmCustomersAddresses::bulkSave( Utils::getAuth( $requestHeaders ), $customerId, $value );
                        
                        if ( !empty( $idShopAskSyncs ) ) {
                            Utils::updateObjectStatusesCount( $idShopAskSyncs, 'customers_addresses', $response, count( $value ) );
                        }

                        Utils::handleResponse( $response );
                        
                        Utils::log( 'customersAddresses' ,'passive synchronization' , json_encode( $response ) );
                    }
                }
            } while ( $hasMore );
        
        } catch (\Throwable $th) {
            Utils::log( 'customersAddresses' ,'passive synchronization' , $th->getMessage() );
        }  finally {
            if ( !empty( $idShopAskSyncs ) ) {
                $objectStatus = [
                    "status" => "completed",
                ];
                ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'customers_addresses', $objectStatus );
            }

            Utils::log( 'customersAddresses', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus( 'customers_addresses', 0 );
        }
    }
}
