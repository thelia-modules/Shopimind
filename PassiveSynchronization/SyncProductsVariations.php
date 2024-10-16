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

use Shopimind\Data\ProductsVariationsData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProductsVariations;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\ProductSaleElementsQuery;

class SyncProductsVariations extends AbstractController
{
    /**
     * Process synchronization for products variations.
     *
     * @param string $lastUpdate
     * @param array|int $ids
     * @param string $requestedBy
     * @return array
     */
    public static function processSyncProductsVariations(string $lastUpdate, array|int $ids, string $requestedBy): array
    {
        $productsVariationsIds = null;
        if (!empty($ids)) {
            $productsVariationsIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
        }

        if (empty($lastUpdate)) {
            if (empty($productsVariationsIds)) {
                $count = ProductSaleElementsQuery::create()->find()->count();
            } else {
                $count = ProductSaleElementsQuery::create()->filterById($productsVariationsIds)->find()->count();
            }
        } else {
            if (empty($productsVariationsIds)) {
                $count = ProductSaleElementsQuery::create()->filterByUpdatedAt($lastUpdate, '>=')->count();
            } else {
                $count = ProductSaleElementsQuery::create()->filterById($productsVariationsIds)->filterByUpdatedAt($lastUpdate, '>=')->count();
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
            !empty($synchronizationStatus)
            && isset($synchronizationStatus['synchronization_status'])
            && isset($synchronizationStatus['synchronization_status']['products_variations'])
            && $synchronizationStatus['synchronization_status']['products_variations'] == 1
        ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus('products_variations', 1);

        Utils::launchSynchronisation('products-variations', $lastUpdate, $productsVariationsIds, $requestedBy);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes product variations.
     *
     * @param Request $request
     * @param EventDispatcherInterface $dispatcher
     * @return void
     */
    public static function syncProductsVariations(Request $request, EventDispatcherInterface $dispatcher): void
    {
        try {
            $body = json_decode($request->getContent(), true);

            $lastUpdate = (isset($body['last_update'])) ? $body['last_update'] : null;

            $productsVariationsIds = null;
            $ids = (isset($body['ids'])) ? $body['ids'] : null;
            if (!empty($ids)) {
                $productsVariationsIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
            }

            $requestedBy = (isset($body['requestedBy'])) ? $body['requestedBy'] : null;

            $langs = LangQuery::create()->filterByActive(1)->find();

            $offset = 0;
            $limit = intdiv(20, $langs->count());

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($productsVariationsIds)) {
                        $productsVariations = ProductSaleElementsQuery::create()->offset($offset)->limit($limit)->orderBy('product_id')->find();
                    } else {
                        $productsVariations = ProductSaleElementsQuery::create()->filterById($productsVariationsIds)->offset($offset)->limit($limit)->orderBy('product_id')->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($productsVariationsIds)) {
                        $productsVariations = ProductSaleElementsQuery::create()->offset($offset)->limit($limit)->orderBy('product_id')->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $productsVariations = ProductSaleElementsQuery::create()->filterById($productsVariationsIds)->offset($offset)->limit($limit)->orderBy('product_id')->filterByUpdatedAt($lastUpdate, '>=');
                    }
                }

                if ($productsVariations->count() < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }

                if ($productsVariations->count() > 0) {
                    $data = [];
                    foreach ($productsVariations as $productVariation) {
                        $productId = $productVariation->getProductId();
                        foreach ($langs as $lang) {
                            $data[$productId][] = ProductsVariationsData::formatProductVariation($productVariation, $dispatcher, null, $lang->getLocale());
                        }
                    }

                    foreach ($data as $productId => $value) {
                        $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                        $response = SpmProductsVariations::bulkSave(Utils::getAuth($requestHeaders), $productId, $value);

                        Utils::handleResponse($response);

                        Utils::log('productsVariations', 'passive synchronization', json_encode($response));
                    }
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('productsVariations', 'passive synchronization', $th->getMessage());
        } finally {
            Utils::log('productsVariations', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('products_variations', 0);
        }
    }
}
