<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\ProductImageEvent;
use Shopimind\lib\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Shopimind\SdkShopimind\SpmProductsImages;
use Shopimind\Data\ProductImagesData;
use Thelia\Model\Base\LangQuery;


class ProductImagesListener
{
    /**
     * Synchronizes data after a product image is updated.
     *
     * @param ProductImageEvent $event The event object triggering the action.
     */
    public static function postProductImageInsert( ProductImageEvent $event, EventDispatcherInterface $dispatcher ): void
    {
        $image = $event->getModel();

        $langs = LangQuery::create()->filterByActive( 1 )->find();

        foreach ( $langs as $lang ) {
            $data[] = ProductImagesData::formatProductImage( $image, $lang, $dispatcher );
        }

        $response = SpmProductsImages::bulkSave( Utils::getAuth(), $image->getProductId(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'ProductImage', 'Save', json_encode( $response ), $image->getId() );
    }

    /**
     * Synchronizes data after a product image is updated.
     *
     * @param ProductImageEvent $event The event object triggering the action.
     */
    public static function postProductImageUpdate( ProductImageEvent $event, EventDispatcherInterface $dispatcher ): void
    {
        $image = $event->getModel();
        $langs = LangQuery::create()->filterByActive( 1 )->find();

        foreach ( $langs as $lang ) {
            $data[] = ProductImagesData::formatProductImage( $image, $lang, $dispatcher, 'update' );
        }

        $response = SpmProductsImages::bulkUpdate( Utils::getAuth(), $image->getProductId(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'ProductImage', 'Update', json_encode( $response ), $image->getId() );
    }

    /**
     * Synchronizes data after a product image is deleted.
     *
     * @param ProductImageEvent $event The event object triggering the action.
     */
    public static function postProductImageDelete(ProductImageEvent $event): void
    {
        $image = $event->getModel();
        
        $response = SpmProductsImages::delete( Utils::getAuth(), $image->getProductId(), $image->getId() );
        
        Utils::handleResponse( $response );

        Utils::log( 'ProductImage', 'Delete', json_encode( $response ), $image->getId() );
    }
}
