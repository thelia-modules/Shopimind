<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\ProductSaleElementsEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProductsVariations;
use Shopimind\Data\ProductsVariationsData;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Model\Base\LangQuery;
use Shopimind\SdkShopimind\SpmProducts;
use Shopimind\Data\ProductsData;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;



class ProductsVariationsListener
{
    /**
     * Synchronizes data after a product combinaison is inserted.
     *
     * @param ProductSaleElementCreateEvent $event The event object triggering the action.
     */
    public static function postProductSaleElementsInsert( ProductSaleElementCreateEvent $event, EventDispatcherInterface $dispatcher ){
        $productSaleElements = $event->getProductSaleElement();

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        
        $data = [];
        
        foreach ( $langs as $lang ) {
            $data[] = ProductsVariationsData::formatProductVariation( $productSaleElements, null, $lang->getLocale(), $dispatcher );
        }

        $response = SpmProductsVariations::bulkSave( Utils::getAuth(), $productSaleElements->getProductId(), $data );
    
        Utils::handleResponse( $response );

        Utils::log( 'ProductsVariations', 'Insert', json_encode( $response ), $productSaleElements->getId() );
    }

    /**
     * Synchronizes data after a product combinaison is updateed.
     *
     * @param ProductSaleElementUpdateEvent  $event The event object triggering the action.
     */
    public static function postProductSaleElementsUpdate(ProductSaleElementUpdateEvent  $event, EventDispatcherInterface $dispatcher){
        $productSaleElementsId = $event->getProductSaleElementId();
        $productSaleElements = ProductSaleElementsQuery::create()->findOneById( $productSaleElementsId );
        
        $dataProductSaleElement = [];

        $product = $event->getProduct();

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $productDefault = $product->getTranslation( $defaultLocal );

        $dataProduct = [];
        foreach ( $langs as $lang ) {
            $dataProductSaleElement[] = ProductsVariationsData::formatProductVariation( $productSaleElements, null, $lang->getLocale(), $dispatcher );

            $productTranslated = $product->getTranslation( $lang->getLocale() );
            $dataProduct[] = ProductsData::formatProduct( $product, $productTranslated, $productDefault, $dispatcher );
        }

        $response = SpmProductsVariations::bulkUpdate( Utils::getAuth(), $productSaleElements->getProductId(), $dataProductSaleElement );
        Utils::handleResponse( $response );
        Utils::log( 'ProductsVariations', 'Update', json_encode( $response ), $productSaleElements->getId() );

        $response = SpmProducts::bulkUpdate( Utils::getAuth(), $dataProduct );
        Utils::handleResponse( $response );
        Utils::log( 'Products', 'Update', json_encode( $response ), $product->getId() );
    }

    /**
     * Synchronizes data after a product combinaison is deleted.
     *
     * @param ProductSaleElementsEvent $event The event object triggering the action.
     */
    public static function postProductSaleElementsDelete(ProductSaleElementsEvent $event){
        $productSaleElements = $event->getModel();

        $response = SpmProductsVariations::delete( Utils::getAuth(), $productSaleElements->getProductId(), $productSaleElements->getId() );
        
        Utils::handleResponse( $response );

        Utils::log( 'ProductsVariations', 'Delete', json_encode( $response ), $productSaleElements->getId() );
    }
}
