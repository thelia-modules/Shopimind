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

use CustomerFamily\Model\CustomerFamilyQuery;
use Shopimind\Data\CustomersGroupsData;
use Shopimind\lib\Utils;
use Shopimind\Model\ShopimindSyncStatus;
use Shopimind\SdkShopimind\SpmCustomersGroups;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Base\LangQuery;

class SyncCustomersGroups
{
    public function __construct(private CustomersGroupsData $customersGroupsData)
    {
    }

    /**
     * Process synchronization for customers groups.
     */
    public function processSyncCustomersGroups($lastUpdate, $ids, $requestedBy, $idShopAskSyncs): array
    {
        $customersGroupsIds = null;
        if (!empty($ids)) {
            $customersGroupsIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
        }

        if (empty($lastUpdate)) {
            if (empty($customersGroupsIds)) {
                $count = CustomerFamilyQuery::create()->find()->count();
            } else {
                $count = CustomerFamilyQuery::create()->filterById($customersGroupsIds)->find()->count();
            }
        } else {
            if (empty($customersGroupsIds)) {
                $count = CustomerFamilyQuery::create()->filterByUpdatedAt($lastUpdate, '>=')->count();
            } else {
                $count = CustomerFamilyQuery::create()->filterById($customersGroupsIds)->filterByUpdatedAt($lastUpdate, '>=')->count();
            }
        }

        $langs = LangQuery::create()->filterByActive(1)->find();
        $count *= $langs->count();

        if (!empty($idShopAskSyncs)) {
            ShopimindSyncStatus::updateShopimindSyncStatus($idShopAskSyncs, 'customers_groups');

            $objectStatus = ShopimindSyncStatus::getObjectStatus($idShopAskSyncs, 'customers_groups');
            $oldCount = !empty($objectStatus) ? $objectStatus['total_objects_count'] : 0;
            if ($oldCount > 0) {
                $count = $oldCount;
            }

            $objectStatus = [
                'status' => 'in_progress',
                'total_objects_count' => $count,
            ];
            ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers_groups', $objectStatus);
        }

        if ($count == 0) {
            if (!empty($idShopAskSyncs)) {
                $objectStatus = [
                    'status' => 'completed',
                ];
                ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers_groups', $objectStatus);
            }

            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();

        if (
            $synchronizationStatus
            && isset($synchronizationStatus['synchronization_status']['customers_groups'])
            && $synchronizationStatus['synchronization_status']['customers_groups'] == 1
        ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus('customers_groups', 1);

        Utils::launchSynchronisation('customers-groups', $lastUpdate, $customersGroupsIds, $requestedBy, $idShopAskSyncs);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes customers groups.
     *
     * @return void
     */
    public function syncCustomersGroups(Request $request): void
    {
        try {
            $body = json_decode($request->getContent(), true);

            $lastUpdate = (isset($body['last_update'])) ? $body['last_update'] : null;

            $customersGroupsIds = null;
            $ids = (isset($body['ids'])) ? $body['ids'] : null;
            if (!empty($ids)) {
                $customersGroupsIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
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
                    if (empty($customersGroupsIds)) {
                        $customersGroups = CustomerFamilyQuery::create()
                            ->orderByUpdatedAt()
                            ->offset($offset)
                            ->limit($limit)
                            ->find();
                    } else {
                        $customersGroups = CustomerFamilyQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById($customersGroupsIds)
                            ->offset($offset)
                            ->limit($limit)
                            ->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($customersGroupsIds)) {
                        $customersGroups = CustomerFamilyQuery::create()
                            ->orderByUpdatedAt()
                            ->offset($offset)
                            ->limit($limit)
                            ->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $customersGroups = CustomerFamilyQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById($customersGroupsIds)
                            ->offset($offset)
                            ->limit($limit)
                            ->filterByUpdatedAt($lastUpdate, '>=');
                    }
                }

                if ($customersGroups->count() < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }

                if ($customersGroups->count() > 0) {
                    $data = [];
                    foreach ($customersGroups as $customersGroup) {
                        $customersGroupDefault = $customersGroup->getTranslation($defaultLocal);

                        foreach ($langs as $lang) {
                            $customersGroupTranslated = $customersGroup->getTranslation($lang->getLocale());

                            $data[] = $this->customersGroupsData->formatCustomerGroup($customersGroup, $customersGroupTranslated, $customersGroupDefault);
                        }
                    }

                    $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                    $response = SpmCustomersGroups::bulkSave(Utils::getAuth($requestHeaders), $data);

                    if (!empty($idShopAskSyncs)) {
                        ShopimindSyncStatus::updateObjectStatusesCount($idShopAskSyncs, 'customers_groups', $response, \count($data));

                        $lastObject = end($data);
                        $lastObjectUpdate = $lastObject['updated_at'];
                        $objectStatus = [
                            'last_object_update' => $lastObjectUpdate,
                        ];
                        ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers_groups', $objectStatus);
                    }

                    Utils::handleResponse($response);

                    Utils::log('customersGroups', 'passive synchronization', json_encode($response));
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('customersGroups', 'passive synchronization', $th->getMessage());
        } finally {
            if (!empty($idShopAskSyncs)) {
                $objectStatus = [
                    'status' => 'completed',
                ];
                ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers_groups', $objectStatus);
            }

            Utils::log('customersGroups', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('customers_groups', 0);
        }
    }
}
