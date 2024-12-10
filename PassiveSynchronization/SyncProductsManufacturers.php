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

use Shopimind\Data\ProductsManufacturersData;
use Shopimind\lib\Utils;
use Shopimind\Model\ShopimindSyncStatus;
use Shopimind\SdkShopimind\SpmProductsManufacturers;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\BrandQuery;

class SyncProductsManufacturers
{
    public function __construct(private ProductsManufacturersData $productsManufacturersData)
    {
    }

    /**
     * Process synchronization for products manufacturers.
     */
    public function processSyncProductsManufacturers($lastUpdate, $ids, $requestedBy, $idShopAskSyncs)
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

        if (!empty($idShopAskSyncs)) {
            ShopimindSyncStatus::updateShopimindSyncStatus($idShopAskSyncs, 'products_manufacturers');

            $objectStatus = ShopimindSyncStatus::getObjectStatus($idShopAskSyncs, 'products_manufacturers');
            $oldCount = !empty($objectStatus) ? $objectStatus['total_objects_count'] : 0;
            if ($oldCount > 0) {
                $count = $oldCount;
            }

            $objectStatus = [
                'status' => 'in_progress',
                'total_objects_count' => $count,
            ];
            ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'products_manufacturers', $objectStatus);
        }

        if ($count == 0) {
            if (!empty($idShopAskSyncs)) {
                $objectStatus = [
                    'status' => 'completed',
                ];
                ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'products_manufacturers', $objectStatus);
            }

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

        Utils::launchSynchronisation('products-manufacturers', $lastUpdate, $manufacturesIds, $requestedBy, $idShopAskSyncs);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes products manufacturers.
     *
     * @return void
     */
    public function syncProductsManufacturers(Request $request): void
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

            $idShopAskSyncs = (isset($body['idShopAskSyncs'])) ? $body['idShopAskSyncs'] : null;

            $offset = 0;
            $limit = 20;

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($manufacturesIds)) {
                        $productsManufacturers = BrandQuery::create()
                            ->orderByUpdatedAt()
                            ->offset($offset)
                            ->limit($limit)
                            ->find();
                    } else {
                        $productsManufacturers = BrandQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById($manufacturesIds)
                            ->offset($offset)
                            ->limit($limit)
                            ->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($manufacturesIds)) {
                        $productsManufacturers = BrandQuery::create()
                            ->orderByUpdatedAt()
                            ->offset($offset)
                            ->limit($limit)
                            ->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $productsManufacturers = BrandQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById($manufacturesIds)
                            ->offset($offset)
                            ->limit($limit)
                            ->filterByUpdatedAt($lastUpdate, '>=');
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
                        $data[] = $this->productsManufacturersData->formatProductmanufacturer($productsManufacturer);
                    }

                    $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                    $response = SpmProductsManufacturers::bulkSave(Utils::getAuth($requestHeaders), $data);

                    if (!empty($idShopAskSyncs)) {
                        ShopimindSyncStatus::updateObjectStatusesCount($idShopAskSyncs, 'products_manufacturers', $response, \count($data));

                        $lastObject = end($data);
                        $lastObjectUpdate = $lastObject['updated_at'];
                        $objectStatus = [
                            'last_object_update' => $lastObjectUpdate,
                        ];
                        ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'products_manufacturers', $objectStatus);
                    }

                    Utils::handleResponse($response);

                    Utils::log('productManufacturers', 'passive synchronization', json_encode($response));
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('productManufacturers', 'passive synchronization', $th->getMessage());
        } finally {
            if (!empty($idShopAskSyncs)) {
                $objectStatus = [
                    'status' => 'completed',
                ];
                ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'products_manufacturers', $objectStatus);
            }

            Utils::log('productManufacturers', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('products_manufacturers', 0);
        }
    }
}
