<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\BrandEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProductsManufacturers;
use Shopimind\Data\ProductsManufacturersData;

class ProductsManufacturersListener
{
    /**
     * Synchronizes data after a product manufacturer is inserted.
     *
     * @param BrandEvent $event The event object triggering the action.
     */
    public static function postBrandInsert(BrandEvent $event): void
    {
        $brand = $event->getModel();
    
        $data[] = ProductsManufacturersData::formatProductmanufacturer( $brand );
        
        $response = SpmProductsManufacturers::bulkSave( Utils::getAuth(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'ProductManufacturer', 'Save', json_encode( $response ), $brand->getId() );
    }

    /**
     * Synchronizes data after a product manufacturer is updated.
     *
     * @param BrandEvent $event The event object triggering the action.
     */
    public static function postBrandUpdate(BrandEvent $event): void
    {
        $brand = $event->getModel();
        
        $data[] = ProductsManufacturersData::formatProductmanufacturer( $brand );
        
        $response = SpmProductsManufacturers::bulkUpdate( Utils::getAuth(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'ProductManufacturer', 'Update', json_encode( $response ), $brand->getId() );
    }

    /**
     * Synchronizes data after a product manufacturer is deleted.
     *
     * @param BrandEvent $event The event object triggering the action.
     */
    public static function postBrandDelete(BrandEvent $event): void
    {
        $brand = $event->getModel()->getId();

        $response = SpmProductsManufacturers::delete( Utils::getAuth(), $brand );
        
        Utils::handleResponse( $response );

        Utils::log( 'ProductManufacturer', 'Delete', json_encode( $response ), $brand );
    }
}
