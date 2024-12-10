<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopimind\EventListeners;

require_once \dirname(__DIR__).'/vendor-module/autoload.php';

use Shopimind\Data\ProductsCategoriesData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProductsCategories;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\Event\CategoryEvent;

class ProductsCategoriesListener
{
    public function __construct(private ProductsCategoriesData $productsCategoriesData)
    {
    }

    /**
     * Synchronizes data after a category is inserted.
     *
     * @param CategoryEvent $event the event object triggering the action
     */
    public function postCategoryInsert(CategoryEvent $event): void
    {
        $category = $event->getModel();

        $langs = LangQuery::create()->filterByActive(1)->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $categoryDefault = $category->getTranslation($defaultLocal);

        $data = [];

        foreach ($langs as $lang) {
            $categoryTranslated = $category->getTranslation($lang->getLocale());
            $data[] = $this->productsCategoriesData->formatProductCategory($category, $categoryTranslated, $categoryDefault);
        }

        $response = SpmProductsCategories::bulkSave(Utils::getAuth(), $data);

        Utils::handleResponse($response);

        Utils::log('ProductCategory', 'Save', json_encode($response), $category->getId());
    }

    /**
     * Synchronizes data after a category is updated.
     *
     * @param CategoryEvent $event the event object triggering the action
     */
    public function postCategoryUpdate(CategoryEvent $event): void
    {
        $category = $event->getModel();

        $langs = LangQuery::create()->filterByActive(1)->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $categoryDefault = $category->getTranslation($defaultLocal);

        $data = [];

        foreach ($langs as $lang) {
            $categoryTranslated = $category->getTranslation($lang->getLocale());

            $data[] = $this->productsCategoriesData->formatProductCategory($category, $categoryTranslated, $categoryDefault);
        }

        $response = SpmProductsCategories::bulkUpdate(Utils::getAuth(), $data);

        Utils::handleResponse($response);

        Utils::log('ProductCategory', 'Update', json_encode($response), $category->getId());
    }

    /**
     * Synchronizes data after a category is deleted.
     *
     * @param CategoryEvent $event the event object triggering the action
     */
    public function postCategoryDelete(CategoryEvent $event): void
    {
        $category = $event->getModel()->getId();

        $response = SpmProductsCategories::delete(Utils::getAuth(), $category);

        Utils::handleResponse($response);

        Utils::log('ProductCategory', 'Delete', json_encode($response), $category);
    }
}
