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

use Shopimind\Data\CustomersAddressesData;
use Shopimind\lib\Utils;
use Shopimind\Model\ShopimindSyncStatus;
use Shopimind\SdkShopimind\SpmCustomersAddresses;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\AddressQuery;

class SyncCustomersAddresses
{
    public function __construct(private CustomersAddressesData $customersAddressesData)
    {
    }

    /**
     * Process synchronization for customers addresses.
     */
    public function processSyncCustomersAddresses($lastUpdate, $ids, $requestedBy, $idShopAskSyncs): array
    {
        $customerAddressesIds = null;
        if (!empty($ids)) {
            $customerAddressesIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
        }

        if (empty($lastUpdate)) {
            if (empty($customerAddressesIds)) {
                $count = AddressQuery::create()->find()->count();
            } else {
                $count = AddressQuery::create()->filterById($customerAddressesIds)->find()->count();
            }
        } else {
            if (empty($customerAddressesIds)) {
                $count = AddressQuery::create()->filterByUpdatedAt($lastUpdate, '>=')->count();
            } else {
                $count = AddressQuery::create()->filterById($customerAddressesIds)->filterByUpdatedAt($lastUpdate, '>=')->count();
            }
        }

        if (!empty($idShopAskSyncs)) {
            ShopimindSyncStatus::updateShopimindSyncStatus($idShopAskSyncs, 'customers_addresses');

            $objectStatus = ShopimindSyncStatus::getObjectStatus($idShopAskSyncs, 'customers_addresses');
            $oldCount = !empty($objectStatus) ? $objectStatus['total_objects_count'] : 0;
            if ($oldCount > 0) {
                $count = $oldCount;
            }

            $objectStatus = [
                'status' => 'in_progress',
                'total_objects_count' => $count,
            ];
            ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers_addresses', $objectStatus);
        }

        if ($count == 0) {
            if (!empty($idShopAskSyncs)) {
                $objectStatus = [
                    'status' => 'completed',
                ];
                ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers_addresses', $objectStatus);
            }

            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();

        if (
            $synchronizationStatus
            && isset($synchronizationStatus['synchronization_status']['customers_addresses'])
            && $synchronizationStatus['synchronization_status']['customers_addresses'] == 1
        ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus('customers_addresses', 1);

        Utils::launchSynchronisation('customers-addresses', $lastUpdate, $customerAddressesIds, $requestedBy, $idShopAskSyncs);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes customers addresses.
     *
     * @return void
     */
    public function syncCustomersAddresses(Request $request): void
    {
        try {
            $body = json_decode($request->getContent(), true);

            $lastUpdate = (isset($body['last_update'])) ? $body['last_update'] : null;

            $customerAddressesIds = null;
            $ids = (isset($body['ids'])) ? $body['ids'] : null;
            if (!empty($ids)) {
                $customerAddressesIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
            }

            $requestedBy = (isset($body['requestedBy'])) ? $body['requestedBy'] : null;

            $idShopAskSyncs = (isset($body['idShopAskSyncs'])) ? $body['idShopAskSyncs'] : null;

            $offset = 0;
            $limit = 20;

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($customerAddressesIds)) {
                        $customersAddresses = AddressQuery::create()
                            ->orderByUpdatedAt()
                            ->offset($offset)
                            ->limit($limit)
                            ->orderBy('customer_id')
                            ->find();
                    } else {
                        $customersAddresses = AddressQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById($customerAddressesIds)
                            ->offset($offset)
                            ->limit($limit)
                            ->orderBy('customer_id')
                            ->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($customerAddressesIds)) {
                        $customersAddresses = AddressQuery::create()
                            ->orderByUpdatedAt()
                            ->offset($offset)
                            ->limit($limit)
                            ->orderBy('customer_id')
                            ->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $customersAddresses = AddressQuery::create()
                            ->orderByUpdatedAt()
                            ->filterById($customerAddressesIds)
                            ->offset($offset)
                            ->limit($limit)
                            ->orderBy('customer_id')
                            ->filterByUpdatedAt($lastUpdate, '>=');
                    }
                }

                if ($customersAddresses->count() < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }

                if ($customersAddresses->count() > 0) {
                    $data = [];
                    foreach ($customersAddresses as $customerAddress) {
                        $customerId = $customerAddress->getCustomerId();
                        $data[$customerId][] = $this->ustomersAddressesData->formatCustomerAddress($customerAddress);
                    }

                    foreach ($data as $customerId => $value) {
                        $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                        $response = SpmCustomersAddresses::bulkSave(Utils::getAuth($requestHeaders), $customerId, $value);

                        if (!empty($idShopAskSyncs)) {
                            ShopimindSyncStatus::updateObjectStatusesCount($idShopAskSyncs, 'customers_addresses', $response, \count($value));

                            $lastObject = end($value);
                            $lastObjectUpdate = $lastObject['updated_at'];
                            $objectStatus = [
                                'last_object_update' => $lastObjectUpdate,
                            ];
                            ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers_addresses', $objectStatus);
                        }

                        Utils::handleResponse($response);

                        Utils::log('customersAddresses', 'passive synchronization', json_encode($response));
                    }
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('customersAddresses', 'passive synchronization', $th->getMessage());
        } finally {
            if (!empty($idShopAskSyncs)) {
                $objectStatus = [
                    'status' => 'completed',
                ];
                ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers_addresses', $objectStatus);
            }

            Utils::log('customersAddresses', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('customers_addresses', 0);
        }
    }
}
