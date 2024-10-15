<?php

namespace Shopimind\PassiveSynchronization;

require_once realpath(__DIR__.'/../').'/vendor-module/autoload.php';

use Thelia\Model\CategoryQuery;
use Thelia\Model\Base\LangQuery;
use Symfony\Component\HttpFoundation\Request;
use Shopimind\lib\Utils;
use Shopimind\Data\ProductsCategoriesData;
use Shopimind\SdkShopimind\SpmProductsCategories;

class SyncProductsCategories
{
    /**
     * Process synchronization for products categories
     *
     * @param $lastUpdate
     * @param $ids
     * @return array
     */
    public static function processSyncProductsCategories( $lastUpdate, $ids, $requestedBy ): array
    {
        $productsCategoriesIds = null;
        if ( !empty( $ids ) ) {
            $productsCategoriesIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
        }

        if ( empty( $lastUpdate ) ) {
            if ( empty( $productsCategoriesIds ) ) {
                $count = CategoryQuery::create()->find()->count();
            }else {
                $count = CategoryQuery::create()->filterById( $productsCategoriesIds )->find()->count();
            }
        }else {
            if ( empty( $productsCategoriesIds ) ) {
                $count = CategoryQuery::create()->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }else {
                $count = CategoryQuery::create()->filterById( $productsCategoriesIds )->filterByUpdatedAt( $lastUpdate, '>=')->count();
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
            isset($synchronizationStatus['synchronization_status']['products_categories'])
            && $synchronizationStatus['synchronization_status']['products_categories'] == 1
            ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus( 'products_categories', 1 );

        Utils::launchSynchronisation( 'products-categories', $lastUpdate, $productsCategoriesIds, $requestedBy );

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes products categories.
     *
     * @return void
     */
    public static function syncProductsCategories( Request $request )
    {
        try {
            $body =  json_decode( $request->getContent(), true );

            $lastUpdate = ( isset( $body['last_update'] ) ) ? $body['last_update'] : null;

            $productsCategoriesIds = null;
            $ids = ( isset( $body['ids'] ) ) ? $body['ids'] : null;
            if ( !empty( $ids ) ) {
                $productsCategoriesIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
            }

            $requestedBy = ( isset( $body['requestedBy'] ) ) ? $body['requestedBy'] : null;

            $langs = LangQuery::create()->filterByActive( 1 )->find();
            $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();

            $offset = 0;
            $limit = intdiv( 20, $langs->count() );

            $hasMore = true;

            do {
                if ( empty( $lastUpdate ) ) {
                    if ( empty( $productsCategoriesIds ) ) {
                        $categories = CategoryQuery::create()->offset( $offset )->limit( $limit )->find();
                    }else {
                        $categories = CategoryQuery::create()->filterById( $productsCategoriesIds )->offset( $offset )->limit( $limit )->find();
                    }
                } else {
                    $lastUpdate = trim( $lastUpdate, '"\'');
                    if ( empty( $productsCategoriesIds ) ) {
                        $categories = CategoryQuery::create()->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }else {
                        $categories = CategoryQuery::create()->filterById( $productsCategoriesIds )->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }
                }

                if ( $categories->count() < $limit ) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }

                if ( $categories->count() > 0 ) {
                    $data = [];

                    foreach ( $categories as $category ) {
                        $categoryDefault = $category->getTranslation( $defaultLocal );

                        foreach ( $langs as $lang ) {
                            $categoryTranslated = $category->getTranslation( $lang->getLocale() );

                            $data[] = ProductsCategoriesData::formatProductCategory( $category, $categoryTranslated, $categoryDefault );
                        }
                    }

                    $requestHeaders = $requestedBy ? [ 'answered-for' => $requestedBy ] : [];
                    $response = SpmProductsCategories::bulkSave( Utils::getAuth( $requestHeaders ), $data );

                    Utils::handleResponse( $response );

                    Utils::log( 'productCategories' , 'passive synchronization', json_encode( $response ) );
                }

            } while ( $hasMore );

        } catch (\Throwable $th) {
            Utils::log( 'productCategories' , 'passive synchronization', $th->getMessage() );
        }  finally {
            Utils::log( 'productCategories', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus( 'products_categories', 0 );
        }
    }
}
