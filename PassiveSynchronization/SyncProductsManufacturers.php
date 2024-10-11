<?php

namespace Shopimind\PassiveSynchronization;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Thelia\Model\BrandQuery;
use Symfony\Component\HttpFoundation\Request;
use Shopimind\lib\Utils;
use Shopimind\Data\ProductsManufacturersData;
use Shopimind\SdkShopimind\SpmProductsManufacturers;

class SyncProductsManufacturers
{
    /**
     * Process synchronization for products manufacturers
     *
     * @param $lastUpdate
     * @param $ids
     */
    public static function processSyncProductsManufacturers( $lastUpdate, $ids, $requestedBy )
    {
        $manufacturesIds = null;
        if ( !empty( $ids ) ) {
            $manufacturesIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
        }

        if ( empty( $lastUpdate ) ) {
            if ( empty( $manufacturesIds ) ) {
                $count = BrandQuery::create()->find()->count();
            }else {
                $count = BrandQuery::create()->filterById( $manufacturesIds )->find()->count();
            }
        } else {
            if ( empty( $manufacturesIds ) ) {
                $count = BrandQuery::create()->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }else {
                $count = BrandQuery::create()->filterById( $manufacturesIds )->filterByUpdatedAt( $lastUpdate, '>=')->count();
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
            isset($synchronizationStatus['synchronization_status']['products_manufacturers'])
            && $synchronizationStatus['synchronization_status']['products_manufacturers'] == 1
            ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus( 'products_manufacturers', 1 );

        Utils::launchSynchronisation( 'products-manufacturers', $lastUpdate, $manufacturesIds, $requestedBy );

        return [
            'success' => true,
            'count' => $count,
        ];
    }

     /**
     * Synchronizes products manufacturers.
     *
     * @return void
     */
    public static function syncProductsManufacturers( Request $request )
    {
        try {
            $body =  json_decode( $request->getContent(), true );

            $lastUpdate = ( isset( $body['last_update'] ) ) ? $body['last_update'] : null;

            $manufacturesIds = null;
            $ids = ( isset( $body['ids'] ) ) ? $body['ids'] : null;
            if ( !empty( $ids ) ) {
                $manufacturesIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
            }

            $requestedBy = ( isset( $body['requestedBy'] ) ) ? $body['requestedBy'] : null;

            $offset = 0;
            $limit = 20;

            $hasMore = true;

            do {
                if ( empty( $lastUpdate ) ) {
                    if ( empty( $manufacturesIds ) ) {
                        $productsManufacturers = BrandQuery::create()->offset( $offset )->limit( $limit )->find();
                    }else {
                        $productsManufacturers = BrandQuery::create()->filterById( $manufacturesIds )->offset( $offset )->limit( $limit )->find();
                    }
                } else {
                    $lastUpdate = trim( $lastUpdate, '"\'');
                    if ( empty( $manufacturesIds ) ) {
                        $productsManufacturers = BrandQuery::create()->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }else {
                        $productsManufacturers = BrandQuery::create()->filterById( $manufacturesIds )->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }
                }
        
                if ( $productsManufacturers->count() < $limit ) {
                    $hasMore = false;
                } else {
                    $offset += $limit;    
                }
        
                if ( $productsManufacturers->count() > 0 ) {
                    $data = [];
                    foreach ( $productsManufacturers as $productsManufacturer ) {
                        $data[] = ProductsManufacturersData::formatProductmanufacturer( $productsManufacturer );
                    }
        
                    $requestHeaders = $requestedBy ? [ 'answered-for' => $requestedBy ] : [];
                    $response = SpmProductsManufacturers::bulkSave( Utils::getAuth( $requestHeaders ), $data );
                    
                    Utils::handleResponse( $response );
        
                    Utils::log( 'productManufacturers' , 'passive synchronization', json_encode( $response ) );
                }
            } while ( $hasMore );
        
        } catch (\Throwable $th) {
            Utils::log( 'productManufacturers' , 'passive synchronization' , $th->getMessage() );
        }  finally {
            Utils::log( 'productManufacturers', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus( 'products_manufacturers', 0 );
        }
    }
}
