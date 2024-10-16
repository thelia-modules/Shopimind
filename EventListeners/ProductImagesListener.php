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

use Shopimind\Data\ProductImagesData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProductsImages;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\Event\ProductImageEvent;

class ProductImagesListener
{
    /**
     * Synchronizes data after a product image is updated.
     *
     * @param ProductImageEvent $event the event object triggering the action
     * @param EventDispatcherInterface $dispatcher
     * @return void
     */
    public static function postProductImageInsert(ProductImageEvent $event, EventDispatcherInterface $dispatcher): void
    {
        $image = $event->getModel();

        $langs = LangQuery::create()->filterByActive(1)->find();

        $data = [];
        foreach ($langs as $lang) {
            $data[] = ProductImagesData::formatProductImage($image, $lang, $dispatcher);
        }

        $response = SpmProductsImages::bulkSave(Utils::getAuth(), $image->getProductId(), $data);

        Utils::handleResponse($response);

        Utils::log('ProductImage', 'Save', json_encode($response), $image->getId());
    }

    /**
     * Synchronizes data after a product image is updated.
     *
     * @param ProductImageEvent $event the event object triggering the action
     * @param EventDispatcherInterface $dispatcher
     * @return void
     */
    public static function postProductImageUpdate(ProductImageEvent $event, EventDispatcherInterface $dispatcher): void
    {
        $image = $event->getModel();
        $langs = LangQuery::create()->filterByActive(1)->find();

        $data = [];
        foreach ($langs as $lang) {
            $data[] = ProductImagesData::formatProductImage($image, $lang, $dispatcher, 'update');
        }

        $response = SpmProductsImages::bulkUpdate(Utils::getAuth(), $image->getProductId(), $data);

        Utils::handleResponse($response);

        Utils::log('ProductImage', 'Update', json_encode($response), $image->getId());
    }

    /**
     * Synchronizes data after a product image is deleted.
     *
     * @param ProductImageEvent $event the event object triggering the action
     * @return void
     */
    public static function postProductImageDelete(ProductImageEvent $event): void
    {
        $image = $event->getModel();

        $response = SpmProductsImages::delete(Utils::getAuth(), $image->getProductId(), $image->getId());

        Utils::handleResponse($response);

        Utils::log('ProductImage', 'Delete', json_encode($response), $image->getId());
    }
}
