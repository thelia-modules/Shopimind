<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\CategoryEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProductsCategories;
use Shopimind\Data\ProductsCategoriesData;
use Thelia\Model\Base\LangQuery;


class ProductsCategoriesListener
{
    /**
     * Synchronizes data after a category is inserted.
     *
     * @param CategoryEvent $event The event object triggering the action.
     */
    public static function postCategoryInsert(CategoryEvent $event): void
    {
        $category = $event->getModel();

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $categoryDefault = $category->getTranslation( $defaultLocal );

        $data = [];

        foreach ( $langs as $lang ) {
            $categoryTranslated = $category->getTranslation( $lang->getLocale() );
            $data[] = ProductsCategoriesData::formatProductCategory( $category, $categoryTranslated, $categoryDefault );
        }

        $response = SpmProductsCategories::bulkSave( Utils::getAuth(), $data );

        Utils::handleResponse( $response );

        Utils::log( 'ProductCategory', 'Save', json_encode( $response ), $category->getId() );
    }

    /**
     * Synchronizes data after a category is updated.
     *
     * @param CategoryEvent $event The event object triggering the action.
     */
    public static function postCategoryUpdate(CategoryEvent $event): void
    {
        $category = $event->getModel();

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $categoryDefault = $category->getTranslation( $defaultLocal );

        $data = [];
        
        foreach ( $langs as $lang ) {
            $categoryTranslated = $category->getTranslation( $lang->getLocale() );
        
            $data[] = ProductsCategoriesData::formatProductCategory( $category, $categoryTranslated, $categoryDefault );
        }
        
        $response = SpmProductsCategories::bulkUpdate( Utils::getAuth(), $data );

        Utils::handleResponse( $response );

        Utils::log( 'ProductCategory', 'Update', json_encode( $response ), $category->getId() );
    }

    /**
     * Synchronizes data after a category is deleted.
     *
     * @param CategoryEvent $event The event object triggering the action.
     */
    public static function postCategoryDelete(CategoryEvent $event): void
    {
        $category = $event->getModel()->getId();

        $response = SpmProductsCategories::delete( Utils::getAuth(), $category );
        
        Utils::handleResponse( $response );

        Utils::log( 'ProductCategory', 'Delete', json_encode( $response ), $category );
    }
}
