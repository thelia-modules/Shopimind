<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\ProductEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProducts;
use Shopimind\Data\ProductsData;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Model\Base\LangQuery;
use Shopimind\Data\ProductsVariationsData;
use Shopimind\SdkShopimind\SpmProductsVariations;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductsListener
{
    public static function postProductInsert(ProductCreateEvent $event, EventDispatcherInterface $dispatcher): void
    {
        $product = $event->getProduct();
        $defaultTitle = '';

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $productDefault = $product->getTranslation( $defaultLocal );

        $dataProduct = [];
        foreach ( $langs as $lang ) {
            $productTranslated = $product->getTranslation( $lang->getLocale() );
            
            $dataProduct[] = ProductsData::formatProduct( $product, $productTranslated, $productDefault, $dispatcher );
            
            if ( !empty( $productTranslated->getTitle() ) ) $defaultTitle = $productTranslated->getTitle();
        }
        $response = SpmProducts::bulkSave( Utils::getAuth(), $dataProduct );
        Utils::handleResponse( $response );
        Utils::log( 'Products', 'Insert', json_encode( $response ), $product->getId() );

        $productSales = $event->getProduct()->getProductSaleElementss();
        $dataProductSaleElement = [];
        foreach ( $productSales as $productSale ) {
            foreach ( $langs as $lang ) {
                $dataProductSaleElement[] = ProductsVariationsData::formatProductVariation( $productSale, $defaultTitle, $lang->getLocale(), $dispatcher );
            }
        }
        $response = SpmProductsVariations::bulkSave( Utils::getAuth(), $product->getId(), $dataProductSaleElement );
        Utils::handleResponse( $response );
        Utils::log( 'ProductsVariations', 'Insert', json_encode( $response ), $product->getId() );

    }

    /**
     * Synchronizes data after a product is updated.
     *
     * @param ProductUpdateEvent $event The event object triggering the action.
     */
    public static function postProductUpdate(ProductUpdateEvent $event, EventDispatcherInterface $dispatcher){
        $product = $event->getProduct();

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $productDefault = $product->getTranslation( $defaultLocal );

        $data = [];
        foreach ( $langs as $lang ) {
            $productTranslated = $product->getTranslation( $lang->getLocale() );
            $data[] = ProductsData::formatProduct( $product, $productTranslated, $productDefault, $dispatcher );
        }
        $response = SpmProducts::bulkUpdate( Utils::getAuth(), $data );
        Utils::handleResponse( $response );
        Utils::log( 'Products', 'Update', json_encode( $response ), $product->getId() );
    }

    /**
     * Synchronizes data after a product is deleted.
     *
     * @param ProductEvent $event The event object triggering the action.
     */
    public static function postProductDelete(ProductEvent $event){
        $product = $event->getModel()->getId();

        $response = SpmProducts::delete( Utils::getAuth(), $product );
        
        Utils::handleResponse( $response );

        Utils::log( 'Products', 'Delete', json_encode( $response ), $product );
    }
}
