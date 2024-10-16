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

namespace Shopimind\PassiveSynchronization;

require_once \dirname(__DIR__).'/vendor-module/autoload.php';

use Shopimind\Data\ProductImagesData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProductsImages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\ProductImageQuery;

class SyncProductsImages extends AbstractController
{
    /**
     * Process synchronization for products images.
     *
     * @param string $lastUpdate
     * @param array|int $ids
     * @param string $requestedBy
     * @return array
     */
    public static function processSyncProductsImages(string $lastUpdate, array|int $ids, string $requestedBy): array
    {
        $productsImagesIds = null;
        if (!empty($ids)) {
            $productsImagesIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
        }

        if (empty($lastUpdate)) {
            if (empty($productsImagesIds)) {
                $count = ProductImageQuery::create()->find()->count();
            } else {
                $count = ProductImageQuery::create()->filterById($productsImagesIds)->find()->count();
            }
        } else {
            if (empty($productsImagesIds)) {
                $count = ProductImageQuery::create()->filterByUpdatedAt($lastUpdate, '>=')->count();
            } else {
                $count = ProductImageQuery::create()->filterById($productsImagesIds)->filterByUpdatedAt($lastUpdate, '>=')->count();
            }
        }

        if ($count == 0) {
            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();

        if (
            $synchronizationStatus
            && isset($synchronizationStatus['synchronization_status']['products_images'])
            && $synchronizationStatus['synchronization_status']['products_images'] == 1
        ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus('products_images', 1);

        Utils::launchSynchronisation('products-images', $lastUpdate, $productsImagesIds, $requestedBy);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes products images.
     *
     * @param Request $request
     * @param EventDispatcherInterface $dispatcher
     * @return void
     */
    public static function syncProductsImages(Request $request, EventDispatcherInterface $dispatcher): void
    {
        try {
            $body = json_decode($request->getContent(), true);

            $lastUpdate = (isset($body['last_update'])) ? $body['last_update'] : null;

            $productsImagesIds = null;
            $ids = (isset($body['ids'])) ? $body['ids'] : null;
            if (!empty($ids)) {
                $productsImagesIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
            }

            $requestedBy = (isset($body['requestedBy'])) ? $body['requestedBy'] : null;

            $langs = LangQuery::create()->filterByActive(1)->find();

            $offset = 0;
            $limit = intdiv(20, $langs->count());

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($productsImagesIds)) {
                        $productImages = ProductImageQuery::create()->offset($offset)->limit($limit)->orderBy('product_id')->find();
                    } else {
                        $productImages = ProductImageQuery::create()->filterById($productsImagesIds)->offset($offset)->limit($limit)->orderBy('product_id')->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($productsImagesIds)) {
                        $productImages = ProductImageQuery::create()->offset($offset)->limit($limit)->orderBy('product_id')->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $productImages = ProductImageQuery::create()->filterById($productsImagesIds)->offset($offset)->limit($limit)->orderBy('product_id')->filterByUpdatedAt($lastUpdate, '>=');
                    }
                }

                if ($productImages->count() < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }

                if ($productImages->count() > 0) {
                    $data = [];
                    foreach ($productImages as $productImage) {
                        $productId = $productImage->getProductId();
                        foreach ($langs as $lang) {
                            $data[$productId][] = ProductImagesData::formatProductImage($productImage, $lang, $dispatcher, 'update');
                        }
                    }

                    foreach ($data as $productId => $value) {
                        $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                        $response = SpmProductsImages::bulkSave(Utils::getAuth($requestHeaders), $productId, $value);

                        Utils::handleResponse($response);

                        Utils::log('productImage', 'passive synchronization', json_encode($response));
                    }
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('productImage', 'passive synchronization', $th->getMessage());
        } finally {
            Utils::log('productImage', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('products_images', 0);
        }
    }
}
