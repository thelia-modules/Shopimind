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

require_once dirname(__DIR__) . '/vendor-module/autoload.php';

use Shopimind\Data\CustomersAddressesData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmCustomersAddresses;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\AddressQuery;

class SyncCustomersAddresses
{
    /**
     * Process synchronization for customers addresses.
     *
     * @param string $lastUpdate
     * @param array|int $ids
     * @param string $requestedBy
     * @return array
     */
    public static function processSyncCustomersAddresses(string $lastUpdate, array|int $ids, string $requestedBy): array
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

        if ($count == 0) {
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

        Utils::launchSynchronisation('customers-addresses', $lastUpdate, $customerAddressesIds, $requestedBy);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes customers addresses.
     *
     * @param Request $request
     * @return void
     */
    public static function syncCustomersAddresses(Request $request): void
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

            $offset = 0;
            $limit = 20;

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($customerAddressesIds)) {
                        $customersAddresses = AddressQuery::create()->offset($offset)->limit($limit)->orderBy('customer_id')->find();
                    } else {
                        $customersAddresses = AddressQuery::create()->filterById($customerAddressesIds)->offset($offset)->limit($limit)->orderBy('customer_id')->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($customerAddressesIds)) {
                        $customersAddresses = AddressQuery::create()->offset($offset)->limit($limit)->orderBy('customer_id')->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $customersAddresses = AddressQuery::create()->filterById($customerAddressesIds)->offset($offset)->limit($limit)->orderBy('customer_id')->filterByUpdatedAt($lastUpdate, '>=');
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
                        $data[$customerId][] = CustomersAddressesData::formatCustomerAddress($customerAddress);
                    }

                    foreach ($data as $customerId => $value) {
                        $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                        $response = SpmCustomersAddresses::bulkSave(Utils::getAuth($requestHeaders), $customerId, $value);

                        Utils::handleResponse($response);

                        Utils::log('customersAddresses', 'passive synchronization', json_encode($response));
                    }
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('customersAddresses', 'passive synchronization', $th->getMessage());
        } finally {
            Utils::log('customersAddresses', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('customers_addresses', 0);
        }
    }
}
