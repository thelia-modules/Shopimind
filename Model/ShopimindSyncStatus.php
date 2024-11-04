<?php

namespace Shopimind\Model;

use Shopimind\Model\Base\ShopimindSyncStatus as BaseShopimindSyncStatus;
use Shopimind\Model\ShopimindSyncStatusQuery;

/**
 * Skeleton subclass for representing a row from the 'shopimind_sync_status' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ShopimindSyncStatus extends BaseShopimindSyncStatus
{
    /**
     * List of valid object type
     */
    private const OBJECT_TYPES = [
        'customers',
        'customers_addresses',
        'customers_groups',
        'newsletter_subscribers',
        'orders',
        'orders_statuses',
        'products',
        'products_variations',
        'products_images',
        'products_categories',
        'products_manufacturers',
        'vouchers',
    ];

    /**
     * update or create a shopiminSyncStatus
     *
     * @param $idShopAskSyncs
     * @param $currentDataType
     * @return void
     */
    public static function updateShopimindSyncStatus( $idShopAskSyncs, $currentDataType )
    {
        $syncStatus = ShopimindSyncStatusQuery::create()->findOneById( $idShopAskSyncs );
        
        if ( !empty( $syncStatus ) ) {
            $syncStatus->setCurrentDataType( $currentDataType );
        }else {
            $syncStatus = new ShopimindSyncStatus();
            $syncStatus->setId( $idShopAskSyncs );
            $syncStatus->setCurrentDataType( $currentDataType );
            $syncStatus->setFirstCall( new \DateTime('now') );
            $syncStatus->setGlobalState( 'in_progress' );
            $syncStatus->setStatuses( self::generateObjectStatuses() );
        }

        $syncStatus->save();
    }

    /**
     * Generate initial object statuses
     *
     * @return array
     */
    private static function generateObjectStatuses()
    {
        $objectStatuses = [];

        foreach ( self::OBJECT_TYPES as $objectType ) {
            $objectStatuses[$objectType] = [
                'status' => 'pending',
                'total_objects_count' => 0,
                'sent_successful_count' => 0,
                'sent_failed_count' => 0,
                'last_update' => null
            ];
        }

        return $objectStatuses;
    } 

    /**
     * Update object statuses
     *
     * @param $idShopAskSyncs
     * @param $objectType
     * @return void
     */
    public static function updateObjectStatuses( $idShopAskSyncs, $objectType, $newData )
    {
        if ( !in_array( $objectType, self::OBJECT_TYPES ) ) return; //TODO
        if ( empty( $newData ) || !is_array( $newData ) ) return; //TODO

        $syncStatus = ShopimindSyncStatusQuery::create()->findOneById( $idShopAskSyncs );
        if ( !empty( $syncStatus ) ) {
            $syncStatus->setCurrentDataType( $objectType );
            $newObjectStatuses = $syncStatus->getStatuses();
            
            if ( isset( $newData['status'] ) && !empty( $newData['status'] ) ) {
                $newObjectStatuses[$objectType]['status'] = $newData['status'];
            }

            if ( isset( $newData['total_objects_count'] ) && !empty( $newData['total_objects_count'] ) ) {
                $newObjectStatuses[$objectType]['total_objects_count'] = $newData['total_objects_count'];
            }

            if ( isset( $newData['sent_successful_count'] ) && !empty( $newData['sent_successful_count'] ) ) {
                $newObjectStatuses[$objectType]['sent_successful_count'] = $newData['sent_successful_count'];
            }

            if ( isset( $newData['sent_failed_count'] ) && !empty( $newData['sent_failed_count'] ) ) {
                $newObjectStatuses[$objectType]['sent_failed_count'] = $newData['sent_failed_count'];
            }

            $currentDateTime = new \DateTime();
            $newObjectStatuses[$objectType]['last_update'] = $currentDateTime->format('Y-m-d\TH:i:s.u\Z');
        
            $syncStatus->setStatuses( $newObjectStatuses );

            $syncStatus->save();
        }
    }

    /**
     * Retrieve object status synchronization detail
     *
     * @param $idShopAskSyncs
     * @param $objectType
     * @return array|null
     */
    public static function getObjectStatus( $idShopAskSyncs, $objectType )
    {
        if ( !in_array( $objectType, self::OBJECT_TYPES ) ) return; //TODO

        $syncStatus = ShopimindSyncStatusQuery::create()->findOneById( $idShopAskSyncs );
        if ( !empty( $syncStatus ) ) {
            $newObjectStatuses = $syncStatus->getStatuses();
            return $newObjectStatuses[$objectType];
        }

        return null;
    }
}
