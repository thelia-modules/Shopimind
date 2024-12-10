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

use Shopimind\Data\CustomersData;
use Shopimind\lib\Utils;
use Shopimind\Model\ShopimindSyncStatus;
use Shopimind\SdkShopimind\SpmCustomers;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\CustomerQuery;

class SyncCustomers
{
    public function __construct(private CustomersData $customersData)
    {
    }

    /**
     * Process synchronization for customers.
     */
    public function processSyncCustomers($lastUpdate, $ids, $requestedBy, $idShopAskSyncs): array
    {
        $customerIds = null;
        if (!empty($ids)) {
            $customerIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
        }

        if (empty($lastUpdate)) {
            if (empty($customerIds)) {
                $count = CustomerQuery::create()->find()->count();
            } else {
                $count = CustomerQuery::create()->filterById($customerIds)->find()->count();
            }
        } else {
            if (empty($customerIds)) {
                $count = CustomerQuery::create()->filterByUpdatedAt($lastUpdate, '>=')->count();
            } else {
                $count = CustomerQuery::create()->filterById($customerIds)->filterByUpdatedAt($lastUpdate, '>=')->count();
            }
        }

        if (!empty($idShopAskSyncs)) {
            ShopimindSyncStatus::updateShopimindSyncStatus($idShopAskSyncs, 'customers');

            $objectStatus = ShopimindSyncStatus::getObjectStatus($idShopAskSyncs, 'customers');
            $oldCount = !empty($objectStatus) ? $objectStatus['total_objects_count'] : 0;
            if ($oldCount > 0) {
                $count = $oldCount;
            }

            $objectStatus = [
                'status' => 'in_progress',
                'total_objects_count' => $count,
            ];
            ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers', $objectStatus);
        }

        if ($count == 0) {
            if (!empty($idShopAskSyncs)) {
                $objectStatus = [
                    'status' => 'completed',
                ];
                ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers', $objectStatus);
            }

            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();

        if (
            $synchronizationStatus
            && isset($synchronizationStatus['synchronization_status']['customers'])
            && $synchronizationStatus['synchronization_status']['customers'] == 1
        ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus('customers', 1);

        Utils::launchSynchronisation('customers', $lastUpdate, $customerIds, $requestedBy, $idShopAskSyncs);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes customers.
     *
     * @return void
     */
    public function syncCustomers(Request $request): void
    {
        try {
            $body = json_decode($request->getContent(), true);

            $lastUpdate = (isset($body['last_update'])) ? $body['last_update'] : null;

            $customerIds = null;
            $ids = (isset($body['ids'])) ? $body['ids'] : null;
            if (!empty($ids)) {
                $customerIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
            }

            $requestedBy = (isset($body['requestedBy'])) ? $body['requestedBy'] : null;

            $idShopAskSyncs = (isset($body['idShopAskSyncs'])) ? $body['idShopAskSyncs'] : null;

            $offset = 0;
            $limit = 20;

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($customerIds)) {
                        $customers = CustomerQuery::create()
                            ->orderByUpdatedAt()
                            ->offset($offset)
                            ->limit($limit)
                            ->find();
                    } else {
                        $customers = CustomerQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById($customerIds)
                            ->offset($offset)
                            ->limit($limit)
                            ->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($customerIds)) {
                        $customers = CustomerQuery::create()
                            ->orderByUpdatedAt()
                            ->offset($offset)
                            ->limit($limit)
                            ->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $customers = CustomerQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById($customerIds)
                            ->offset($offset)
                            ->limit($limit)
                            ->filterByUpdatedAt($lastUpdate, '>=');
                    }
                }

                if ($customers->count() < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }

                if ($customers->count() > 0) {
                    $data = [];
                    foreach ($customers as $customer) {
                        $data[] = $this->customersData->formatCustomer($customer);
                    }

                    $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                    $response = SpmCustomers::bulkSave(Utils::getAuth($requestHeaders), $data);

                    if (!empty($idShopAskSyncs)) {
                        ShopimindSyncStatus::updateObjectStatusesCount($idShopAskSyncs, 'customers', $response, \count($data));

                        $lastObject = end($data);
                        $lastObjectUpdate = $lastObject['updated_at'];
                        $objectStatus = [
                            'last_object_update' => $lastObjectUpdate,
                        ];
                        ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers', $objectStatus);
                    }

                    Utils::handleResponse($response);

                    Utils::log('customers', 'passive synchronization', json_encode($response));
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('customers', 'passive synchronization', $th->getMessage());
        } finally {
            if (!empty($idShopAskSyncs)) {
                $objectStatus = [
                    'status' => 'completed',
                ];
                ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers', $objectStatus);
            }

            Utils::log('customers', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('customers', 0);
        }
    }
}
