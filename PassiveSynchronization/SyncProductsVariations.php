<?php

namespace Shopimind\PassiveSynchronization;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Thelia\Model\ProductSaleElementsQuery;
use Symfony\Component\HttpFoundation\Request;
use Shopimind\lib\Utils;
use Thelia\Model\Base\LangQuery;
use Shopimind\SdkShopimind\SpmProductsVariations;
use Shopimind\Data\ProductsVariationsData;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Shopimind\Model\ShopimindSyncStatus;

class SyncProductsVariations extends AbstractController
{

    /**
     * Process synchronization for products variations
     *
     * @param string $lastUpdate
     * @param $ids
     * @param $requestedBy
     * @param $idShopAskSyncs
     * @return array
     */
    public static function processSyncProductsVariations ( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs ): array
    {
        $productsVariationsIds = null;
        if ( !empty( $ids ) ) {
            $productsVariationsIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
        }

        if ( empty( $lastUpdate ) ) {
            if ( empty( $productsVariationsIds ) ) {
                $count = ProductSaleElementsQuery::create()->find()->count();
            }else {
                $count = ProductSaleElementsQuery::create()->filterById( $productsVariationsIds )->find()->count();                
            }
        } else {
            if ( empty( $productsVariationsIds ) ) {
                $count = ProductSaleElementsQuery::create()->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }else {
                $count = ProductSaleElementsQuery::create()->filterById( $productsVariationsIds )->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }
        }

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $count = $count * $langs->count();

        if ( !empty( $idShopAskSyncs ) ) {
            ShopimindSyncStatus::updateShopimindSyncStatus( $idShopAskSyncs, 'products_variations' );
            
            $objectStatus = ShopimindSyncStatus::getObjectStatus( $idShopAskSyncs, 'products_variations' );
            $oldCount = !empty( $objectStatus ) ? $objectStatus['total_objects_count'] : 0;
            if( $oldCount > 0 ){
                $count = $oldCount;
            }

            $objectStatus = [
                "status" => "in_progress",
                "total_objects_count" => $count,
            ];
            ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'products_variations', $objectStatus );
        }

        if ( $count == 0 ) {
            if ( !empty( $idShopAskSyncs ) ) {
                $objectStatus = [
                    "status" => "completed",
                ];
                ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'products_variations', $objectStatus );
            }
            
            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();
        
        if ( 
            !empty( $synchronizationStatus ) 
            && isset( $synchronizationStatus['synchronization_status'] ) 
            && isset( $synchronizationStatus['synchronization_status']['products_variations'] ) 
            && $synchronizationStatus['synchronization_status']['products_variations'] == 1 
            ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus( 'products_variations', 1 );

        Utils::launchSynchronisation( 'products-variations', $lastUpdate, $productsVariationsIds, $requestedBy, $idShopAskSyncs  );

        return [
            'success' => true,
            'count' => $count,
        ]; 
    }
  
    /**
     * Synchronizes product variations.
     *
     * @return void
     */
    public static function syncProductsVariations( Request $request, EventDispatcherInterface $dispatcher )
    {
        try {
            $body =  json_decode( $request->getContent(), true );

            $lastUpdate = ( isset( $body['last_update'] ) ) ? $body['last_update'] : null;

            $productsVariationsIds = null;
            $ids = ( isset( $body['ids'] ) ) ? $body['ids'] : null;
            if ( !empty( $ids ) ) {
                $productsVariationsIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
            }

            $requestedBy = ( isset( $body['requestedBy'] ) ) ? $body['requestedBy'] : null;

            $idShopAskSyncs = ( isset( $body['idShopAskSyncs'] ) ) ? $body['idShopAskSyncs'] : null;

            $langs = LangQuery::create()->filterByActive( 1 )->find();

            $offset = 0;
            $limit = intdiv( 20, $langs->count() );

            $hasMore = true;

            do {
                if ( empty( $lastUpdate ) ) {
                    if ( empty( $productsVariationsIds ) ) {
                        $productsVariations = ProductSaleElementsQuery::create()
                            ->orderByUpdatedAt()
                            ->offset( $offset )
                            ->limit( $limit )
                            ->orderBy('product_id')
                            ->find();
                    }else {
                        $productsVariations = ProductSaleElementsQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById( $productsVariationsIds )
                            ->offset( $offset )
                            ->limit( $limit )
                            ->orderBy('product_id')
                            ->find();
                    }
                } else {
                    $lastUpdate = trim( $lastUpdate, '"\'');
                    if ( empty( $productsVariationsIds ) ) {
                        $productsVariations = ProductSaleElementsQuery::create()
                            ->orderByUpdatedAt()
                            ->offset( $offset )
                            ->limit( $limit )
                            ->orderBy('product_id')
                            ->filterByUpdatedAt( $lastUpdate, '>=' );
                    }else {
                        $productsVariations = ProductSaleElementsQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById( $productsVariationsIds )
                            ->offset( $offset )
                            ->limit( $limit )
                            ->orderBy('product_id')
                            ->filterByUpdatedAt( $lastUpdate, '>=' );
                    }
                }

                if ( $productsVariations->count() < $limit ) {
                    $hasMore = false;
                } else {
                    $offset += $limit;    
                }

                if ( $productsVariations->count() > 0 ) {
                    $data = [];
                    foreach ( $productsVariations as $productVariation ) {
                        $productId = $productVariation->getProductId();
                        foreach ( $langs as $lang ) {
                            $data[ $productId ][] = ProductsVariationsData::formatProductVariation( $productVariation, null, $lang->getLocale(), $dispatcher );
                        }
                    }

                    foreach ( $data as $productId => $value ) {
                        $requestHeaders = $requestedBy ? [ 'answered-for' => $requestedBy ] : [];
                        $response = SpmProductsVariations::bulkSave( Utils::getAuth( $requestHeaders ), $productId, $value );
                    
                        if ( !empty( $idShopAskSyncs ) ) {
                            ShopimindSyncStatus::updateObjectStatusesCount( $idShopAskSyncs, 'products_variations', $response, count( $value ) );

                            $lastObject = end( $value );
                            $lastObjectUpdate = $lastObject['updated_at'];
                            $objectStatus = [
                                "last_object_update" => $lastObjectUpdate,
                            ];
                            ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'products_variations', $objectStatus );  
                        }

                        Utils::handleResponse( $response );

                        Utils::log( 'productsVariations' , 'passive synchronization', json_encode( $response ) );
                    }
                }
            } while ( $hasMore );

        } catch (\Throwable $th) {
            Utils::log( 'productsVariations' , 'passive synchronization', $th->getMessage() );
        }  finally {
            if ( !empty( $idShopAskSyncs ) ) {
                $objectStatus = [
                    "status" => "completed",
                ];
                ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'products_variations', $objectStatus );
            }

            Utils::log( 'productsVariations', 'passive synchronization', 'finally', null );
            Utils::updateSynchronizationStatus( 'products_variations', 0 );
        }
    }
}
