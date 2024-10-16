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

use Shopimind\Data\ProductsManufacturersData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProductsManufacturers;
use Thelia\Model\Event\BrandEvent;

class ProductsManufacturersListener
{
    /**
     * Synchronizes data after a product manufacturer is inserted.
     *
     * @param BrandEvent $event the event object triggering the action
     * @return void
     */
    public static function postBrandInsert(BrandEvent $event): void
    {
        $brand = $event->getModel();

        $data[] = ProductsManufacturersData::formatProductmanufacturer($brand);

        $response = SpmProductsManufacturers::bulkSave(Utils::getAuth(), $data);

        Utils::handleResponse($response);

        Utils::log('ProductManufacturer', 'Save', json_encode($response), $brand->getId());
    }

    /**
     * Synchronizes data after a product manufacturer is updated.
     *
     * @param BrandEvent $event the event object triggering the action
     * @return void
     */
    public static function postBrandUpdate(BrandEvent $event): void
    {
        $brand = $event->getModel();

        $data[] = ProductsManufacturersData::formatProductmanufacturer($brand);

        $response = SpmProductsManufacturers::bulkUpdate(Utils::getAuth(), $data);

        Utils::handleResponse($response);

        Utils::log('ProductManufacturer', 'Update', json_encode($response), $brand->getId());
    }

    /**
     * Synchronizes data after a product manufacturer is deleted.
     *
     * @param BrandEvent $event the event object triggering the action
     * @return void
     */
    public static function postBrandDelete(BrandEvent $event): void
    {
        $brand = $event->getModel()->getId();

        $response = SpmProductsManufacturers::delete(Utils::getAuth(), $brand);

        Utils::handleResponse($response);

        Utils::log('ProductManufacturer', 'Delete', json_encode($response), $brand);
    }
}
