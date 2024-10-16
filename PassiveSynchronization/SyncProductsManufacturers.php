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

use Shopimind\Data\ProductsManufacturersData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProductsManufacturers;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\BrandQuery;

class SyncProductsManufacturers
{
    /**
     * Process synchronization for products manufacturers.
     *
     * @param string $lastUpdate
     * @param array|int $ids
     * @param string $requestedBy
     * @return array
     */
    public static function processSyncProductsManufacturers(string $lastUpdate, array|int $ids, string $requestedBy): array
    {
        $manufacturesIds = null;
        if (!empty($ids)) {
            $manufacturesIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
        }

        if (empty($lastUpdate)) {
            if (empty($manufacturesIds)) {
                $count = BrandQuery::create()->find()->count();
            } else {
                $count = BrandQuery::create()->filterById($manufacturesIds)->find()->count();
            }
        } else {
            if (empty($manufacturesIds)) {
                $count = BrandQuery::create()->filterByUpdatedAt($lastUpdate, '>=')->count();
            } else {
                $count = BrandQuery::create()->filterById($manufacturesIds)->filterByUpdatedAt($lastUpdate, '>=')->count();
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
            && isset($synchronizationStatus['synchronization_status']['products_manufacturers'])
            && $synchronizationStatus['synchronization_status']['products_manufacturers'] == 1
        ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus('products_manufacturers', 1);

        Utils::launchSynchronisation('products-manufacturers', $lastUpdate, $manufacturesIds, $requestedBy);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes products manufacturers.
     *
     * @param Request $request
     * @return void
     */
    public static function syncProductsManufacturers(Request $request): void
    {
        try {
            $body = json_decode($request->getContent(), true);

            $lastUpdate = (isset($body['last_update'])) ? $body['last_update'] : null;

            $manufacturesIds = null;
            $ids = (isset($body['ids'])) ? $body['ids'] : null;
            if (!empty($ids)) {
                $manufacturesIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
            }

            $requestedBy = (isset($body['requestedBy'])) ? $body['requestedBy'] : null;

            $offset = 0;
            $limit = 20;

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($manufacturesIds)) {
                        $productsManufacturers = BrandQuery::create()->offset($offset)->limit($limit)->find();
                    } else {
                        $productsManufacturers = BrandQuery::create()->filterById($manufacturesIds)->offset($offset)->limit($limit)->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($manufacturesIds)) {
                        $productsManufacturers = BrandQuery::create()->offset($offset)->limit($limit)->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $productsManufacturers = BrandQuery::create()->filterById($manufacturesIds)->offset($offset)->limit($limit)->filterByUpdatedAt($lastUpdate, '>=');
                    }
                }

                if ($productsManufacturers->count() < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }

                if ($productsManufacturers->count() > 0) {
                    $data = [];
                    foreach ($productsManufacturers as $productsManufacturer) {
                        $data[] = ProductsManufacturersData::formatProductmanufacturer($productsManufacturer);
                    }

                    $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                    $response = SpmProductsManufacturers::bulkSave(Utils::getAuth($requestHeaders), $data);

                    Utils::handleResponse($response);

                    Utils::log('productManufacturers', 'passive synchronization', json_encode($response));
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('productManufacturers', 'passive synchronization', $th->getMessage());
        } finally {
            Utils::log('productManufacturers', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('products_manufacturers', 0);
        }
    }
}
