<?php

namespace Shopimind\PassiveSynchronization;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Thelia\Model\ProductQuery;
use Thelia\Model\Base\LangQuery;
use Shopimind\lib\Utils;
use Shopimind\Data\ProductsData;
use Shopimind\SdkShopimind\SpmProducts;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Shopimind\Model\ShopimindSyncStatus;

class SyncProducts extends AbstractController
{
    /**
     * Process synchronization for products
     *
     * @param $lastUpdate
     * @param $ids
     * @param $idShopAskSyncs
     * @return array
     */
    public static function processSyncProducts( $lastUpdate, $ids, $requestedBy, $idShopAskSyncs ): array
    {
        $productsIds = null;
        if ( !empty( $ids ) ) {
            $productsIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
        }

        if ( empty( $lastUpdate ) ) {
            if ( empty( $productsIds ) ) {
                $count = ProductQuery::create()->find()->count();
            }else {
                $count = ProductQuery::create()->filterById( $productsIds )->find()->count();
            }
        } else {
            if ( empty( $productsIds ) ) {
                $count = ProductQuery::create()->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }else {
                $count = ProductQuery::create()->filterById( $productsIds )->filterByUpdatedAt( $lastUpdate, '>=')->count();                
            }
        }

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $count = $count * $langs->count();

        if ( !empty( $idShopAskSyncs ) ) {
            ShopimindSyncStatus::updateShopimindSyncStatus( $idShopAskSyncs, 'products' );
            
            $objectStatus = [
                "status" => "in_progress",
                "total_objects_count" => $count,
            ];
            ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'products', $objectStatus );
        }

        if ( $count == 0 ) {
            if ( !empty( $idShopAskSyncs ) ) {
                $objectStatus = [
                    "status" => "completed",
                ];
                ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'products', $objectStatus );
            }
            
            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();
        
        if (
            $synchronizationStatus &&
            isset($synchronizationStatus['synchronization_status']['products'])
            && $synchronizationStatus['synchronization_status']['products'] == 1
            ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus( 'products', 1 );

        Utils::launchSynchronisation( 'products', $lastUpdate, $productsIds, $requestedBy, $idShopAskSyncs );

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes products.
     *
     * @return void
     */
    public static function syncProducts( Request $request, EventDispatcherInterface $dispatcher )
    {
        try {
            $body =  json_decode( $request->getContent(), true );

            $lastUpdate = ( isset( $body['last_update'] ) ) ? $body['last_update'] : null;

            $productsIds = null;
            $ids = ( isset( $body['ids'] ) ) ? $body['ids'] : null;
            if ( !empty( $ids ) ) {
                $productsIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
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
                    if ( empty( $productsIds ) ) {
                        $products = ProductQuery::create()->offset( $offset )->limit( $limit )->find();
                    }else {
                        $products = ProductQuery::create()->filterById( $productsIds )->offset( $offset )->limit( $limit )->find();
                    }
                } else {
                    $lastUpdate = trim( $lastUpdate, '"\'');
                    if ( empty( $productsIds ) ) {
                        $products = ProductQuery::create()->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }else {
                        $products = ProductQuery::create()->filterById( $productsIds )->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }
                }
        
                if ( $products->count() < $limit ) {
                    $hasMore = false;
                } else {
                    $offset += $limit;    
                }
        
                if ( $products->count() > 0 ) {
                    $data = [];
        
                    foreach ( $products as $product ) {
                        $productDefault = $product->getTranslation( $defaultLocal );
        
                        foreach ( $langs as $lang ) {
                            $productTranslated = $product->getTranslation( $lang->getLocale() );
        
                            $data[] = ProductsData::formatProduct( $product, $productTranslated, $productDefault, $dispatcher );
                        }
                    }

                    $requestHeaders = $requestedBy ? [ 'answered-for' => $requestedBy ] : [];
                    $response = SpmProducts::bulkSave( Utils::getAuth( $requestHeaders ), $data );
                    
                    if ( !empty( $idShopAskSyncs ) ) {
                        Utils::updateObjectStatusesCount( $idShopAskSyncs, 'products', $response, count( $data ) );
                    }

                    Utils::handleResponse( $response );
        
                    Utils::log( 'products' , 'passive synchronization' , json_encode( $response ) );
                }
            } while ( $hasMore );
        
        } catch (\Throwable $th) {
            Utils::log( 'products' , 'passive synchronization' , $th->getMessage() );
        } finally {
            if ( !empty( $idShopAskSyncs ) ) {
                $objectStatus = [
                    "status" => "completed",
                ];
                ShopimindSyncStatus::updateObjectStatuses( $idShopAskSyncs, 'products', $objectStatus );
            }

            Utils::log( 'products', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus( 'products', 0 );
        }
    }
}
