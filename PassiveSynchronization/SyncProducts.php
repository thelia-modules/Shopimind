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

require_once THELIA_MODULE_DIR.'/Shopimind/vendor-module/autoload.php';

use Shopimind\Data\ProductsData;
use Shopimind\lib\Utils;
use Shopimind\Model\ShopimindSyncStatus;
use Shopimind\SdkShopimind\SpmProducts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\ProductQuery;

class SyncProducts extends AbstractController
{
    public function __construct(private ProductsData $productsData)
    {
    }

    /**
     * Process synchronization for products.
     */
    public function processSyncProducts($lastUpdate, $ids, $requestedBy, $idShopAskSyncs): array
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

        $langs = LangQuery::create()->filterByActive(1)->find();
        $count *= $langs->count();

        if (!empty($idShopAskSyncs)) {
            ShopimindSyncStatus::updateShopimindSyncStatus($idShopAskSyncs, 'products');

            $objectStatus = ShopimindSyncStatus::getObjectStatus($idShopAskSyncs, 'products');
            $oldCount = !empty($objectStatus) ? $objectStatus['total_objects_count'] : 0;
            if ($oldCount > 0) {
                $count = $oldCount;
            }

            $objectStatus = [
                'status' => 'in_progress',
                'total_objects_count' => $count + $oldCount,
            ];
            ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'products', $objectStatus);
        }

        if ($count == 0) {
            if (!empty($idShopAskSyncs)) {
                $objectStatus = [
                    'status' => 'completed',
                ];
                ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'products', $objectStatus);
            }

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

        Utils::launchSynchronisation('products', $lastUpdate, $productsIds, $requestedBy, $idShopAskSyncs);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes products.
     *
     * @return void
     */
    public function syncProducts(Request $request, EventDispatcherInterface $dispatcher): void
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

            $idShopAskSyncs = (isset($body['idShopAskSyncs'])) ? $body['idShopAskSyncs'] : null;

            $langs = LangQuery::create()->filterByActive(1)->find();
            $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();

            $offset = 0;
            $limit = intdiv(20, $langs->count());

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($productsIds)) {
                        $products = ProductQuery::create()
                            ->orderByUpdatedAt()
                            ->offset($offset)
                            ->limit($limit)
                            ->find();
                    } else {
                        $products = ProductQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById($productsIds)
                            ->offset($offset)
                            ->limit($limit)
                            ->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($productsIds)) {
                        $products = ProductQuery::create()
                            ->orderByUpdatedAt()
                            ->offset($offset)
                            ->limit($limit)
                            ->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $products = ProductQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById($productsIds)
                            ->offset($offset)
                            ->limit($limit)
                            ->filterByUpdatedAt($lastUpdate, '>=');
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

                            $data[] = $this->productsData->formatProduct($product, $productTranslated, $productDefault, $dispatcher);
                        }
                    }

                    $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                    $response = SpmProducts::bulkSave(Utils::getAuth($requestHeaders), $data);

                    if (!empty($idShopAskSyncs)) {
                        ShopimindSyncStatus::updateObjectStatusesCount($idShopAskSyncs, 'products', $response, \count($data));

                        $lastObject = end($data);
                        $lastObjectUpdate = $lastObject['updated_at'];
                        $objectStatus = [
                            'last_object_update' => $lastObjectUpdate,
                        ];
                        ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'products', $objectStatus);
                    }

                    Utils::handleResponse($response);

                    Utils::log('products', 'passive synchronization', json_encode($response));
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('products', 'passive synchronization', $th->getMessage());
        } finally {
            if (!empty($idShopAskSyncs)) {
                $objectStatus = [
                    'status' => 'completed',
                ];
                ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'products', $objectStatus);
            }

            Utils::log('products', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('products', 0);
        }
    }
}
