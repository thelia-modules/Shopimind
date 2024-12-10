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

use Shopimind\Data\ProductsData;
use Shopimind\Data\ProductsVariationsData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProducts;
use Shopimind\SdkShopimind\SpmProductsVariations;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementUpdateEvent;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\Event\ProductSaleElementsEvent;

class ProductsVariationsListener
{
    public function __construct(private ProductsData $productsData, ProductsVariationsData $productsVariationsData)
    {
    }

    /**
     * Synchronizes data after a product combinaison is inserted.
     *
     * @param ProductSaleElementCreateEvent $event the event object triggering the action
     */
    public function postProductSaleElementsInsert(ProductSaleElementCreateEvent $event, EventDispatcherInterface $dispatcher): void
    {
        $productSaleElements = $event->getProductSaleElement();

        $langs = LangQuery::create()->filterByActive(1)->find();

        $data = [];

        foreach ($langs as $lang) {
            $data[] = $this->productsVariationsData->formatProductVariation($productSaleElements, $dispatcher, null, $lang->getLocale());
        }

        $response = SpmProductsVariations::bulkSave(Utils::getAuth(), $productSaleElements->getProductId(), $data);

        Utils::handleResponse($response);

        Utils::log('ProductsVariations', 'Insert', json_encode($response), $productSaleElements->getId());
    }

    /**
     * Synchronizes data after a product combinaison is updateed.
     *
     * @param ProductSaleElementUpdateEvent $event the event object triggering the action
     */
    public function postProductSaleElementsUpdate(ProductSaleElementUpdateEvent $event, EventDispatcherInterface $dispatcher): void
    {
        $productSaleElementsId = $event->getProductSaleElementId();
        $productSaleElements = ProductSaleElementsQuery::create()->findOneById($productSaleElementsId);

        $dataProductSaleElement = [];

        $product = $event->getProduct();

        $langs = LangQuery::create()->filterByActive(1)->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $productDefault = $product->getTranslation($defaultLocal);

        $dataProduct = [];
        foreach ($langs as $lang) {
            $dataProductSaleElement[] = $this->productsVariationsData->formatProductVariation($productSaleElements, $dispatcher, null, $lang->getLocale());

            $productTranslated = $product->getTranslation($lang->getLocale());
            $dataProduct[] = $this->productsData->formatProduct($product, $productTranslated, $productDefault, $dispatcher);
        }

        $response = SpmProductsVariations::bulkUpdate(Utils::getAuth(), $productSaleElements->getProductId(), $dataProductSaleElement);
        Utils::handleResponse($response);
        Utils::log('ProductsVariations', 'Update', json_encode($response), $productSaleElements->getId());

        $response = SpmProducts::bulkUpdate(Utils::getAuth(), $dataProduct);
        Utils::handleResponse($response);
        Utils::log('Products', 'Update', json_encode($response), $product->getId());
    }

    /**
     * Synchronizes data after a product combinaison is deleted.
     *
     * @param ProductSaleElementsEvent $event the event object triggering the action
     */
    public function postProductSaleElementsDelete(ProductSaleElementsEvent $event): void
    {
        $productSaleElements = $event->getModel();

        $response = SpmProductsVariations::delete(Utils::getAuth(), $productSaleElements->getProductId(), $productSaleElements->getId());

        Utils::handleResponse($response);

        Utils::log('ProductsVariations', 'Delete', json_encode($response), $productSaleElements->getId());
    }
}
