<?php

namespace Shopimind\Model;

use Shopimind\Model\Base\ShopimindSyncErrors as BaseShopimindSyncErrors;

/**
 * Skeleton subclass for representing a row from the 'shopimind_sync_errors' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ShopimindSyncErrors extends BaseShopimindSyncErrors
{
    /**
     * Record a new sync error
     *
     * @param $idShopAskSyncs
     * @param $objectType
     * @param $errorCode
     * @param $errorMessage
     * @param $errorData
     */
    public static function recordSyncError( $idShopAskSyncs, $objectType, $response, $data )
    {
        // TODO: disable for the moment
        // if ( isset( $response['statusCode'] ) && $response['statusCode'] !== 200 ) {
        //     $syncError = new ShopimindSyncErrors();
        //     $syncError->setIdShopAskSyncs( $idShopAskSyncs );
        //     $syncError->setObjectType( $objectType );
        //     $syncError->setErrorCode( $response['statusCode'] );
        //     $syncError->setErrorMessage( $response );
        //     $syncError->setData( $data );
        //     $syncError->setTimestamp( new \DateTime() );
        //     $syncError->save();
        // }
    }
}
