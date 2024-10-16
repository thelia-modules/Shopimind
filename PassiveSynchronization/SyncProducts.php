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

use Shopimind\Data\ProductsData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmProducts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\ProductQuery;

class SyncProducts extends AbstractController
{
    /**
     * Process synchronization for products.
     *
     * @param string $lastUpdate
     * @param array|int $ids
     * @param string $requestedBy
     * @return array
     */
    public static function processSyncProducts(string $lastUpdate, array|int $ids, string $requestedBy): array
    {
        $productsIds = null;
        if (!empty($ids)) {
            $productsIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
        }

        if (empty($lastUpdate)) {
            if (empty($productsIds)) {
                $count = ProductQuery::create()->find()->count();
            } else {
                $count = ProductQuery::create()->filterById($productsIds)->find()->count();
            }
        } else {
            if (empty($productsIds)) {
                $count = ProductQuery::create()->filterByUpdatedAt($lastUpdate, '>=')->count();
            } else {
                $count = ProductQuery::create()->filterById($productsIds)->filterByUpdatedAt($lastUpdate, '>=')->count();
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
            && isset($synchronizationStatus['synchronization_status']['products'])
            && $synchronizationStatus['synchronization_status']['products'] == 1
        ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus('products', 1);

        Utils::launchSynchronisation('products', $lastUpdate, $productsIds, $requestedBy);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes products.
     *
     * @param Request $request
     * @param EventDispatcherInterface $dispatcher
     * @return void
     */
    public static function syncProducts(Request $request, EventDispatcherInterface $dispatcher): void
    {
        try {
            $body = json_decode($request->getContent(), true);

            $lastUpdate = (isset($body['last_update'])) ? $body['last_update'] : null;

            $productsIds = null;
            $ids = (isset($body['ids'])) ? $body['ids'] : null;
            if (!empty($ids)) {
                $productsIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
            }

            $requestedBy = (isset($body['requestedBy'])) ? $body['requestedBy'] : null;

            $langs = LangQuery::create()->filterByActive(1)->find();
            $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();

            $offset = 0;
            $limit = intdiv(20, $langs->count());

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($productsIds)) {
                        $products = ProductQuery::create()->offset($offset)->limit($limit)->find();
                    } else {
                        $products = ProductQuery::create()->filterById($productsIds)->offset($offset)->limit($limit)->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($productsIds)) {
                        $products = ProductQuery::create()->offset($offset)->limit($limit)->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $products = ProductQuery::create()->filterById($productsIds)->offset($offset)->limit($limit)->filterByUpdatedAt($lastUpdate, '>=');
                    }
                }

                if ($products->count() < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }

                if ($products->count() > 0) {
                    $data = [];

                    foreach ($products as $product) {
                        $productDefault = $product->getTranslation($defaultLocal);

                        foreach ($langs as $lang) {
                            $productTranslated = $product->getTranslation($lang->getLocale());

                            $data[] = ProductsData::formatProduct($product, $productTranslated, $productDefault, $dispatcher);
                        }
                    }

                    $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                    $response = SpmProducts::bulkSave(Utils::getAuth($requestHeaders), $data);

                    Utils::handleResponse($response);

                    Utils::log('products', 'passive synchronization', json_encode($response));
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('products', 'passive synchronization', $th->getMessage());
        } finally {
            Utils::log('products', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('products', 0);
        }
    }
}
