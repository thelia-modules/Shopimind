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

use Shopimind\Data\CustomersData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmCustomers;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\CustomerQuery;

class SyncCustomers
{
    /**
     * Process synchronization for customers.
     *
     * @param string $lastUpdate
     * @param array|int $ids
     * @param string $requestedBy
     * @return array
     */
    public static function processSyncCustomers(string $lastUpdate, array|int $ids, string $requestedBy): array
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

        if ($count == 0) {
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

        Utils::launchSynchronisation('customers', $lastUpdate, $customerIds, $requestedBy);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes customers.
     *
     * @param Request $request
     * @return void
     */
    public static function syncCustomers(Request $request): void
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

            $offset = 0;
            $limit = 20;

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($customerIds)) {
                        $customers = CustomerQuery::create()->offset($offset)->limit($limit)->find();
                    } else {
                        $customers = CustomerQuery::create()->filterById($customerIds)->offset($offset)->limit($limit)->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($customerIds)) {
                        $customers = CustomerQuery::create()->offset($offset)->limit($limit)->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $customers = CustomerQuery::create()->filterById($customerIds)->offset($offset)->limit($limit)->filterByUpdatedAt($lastUpdate, '>=');
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
                        $data[] = CustomersData::formatCustomer($customer);
                    }

                    $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                    $response = SpmCustomers::bulkSave(Utils::getAuth($requestHeaders), $data);

                    Utils::handleResponse($response);

                    Utils::log('customers', 'passive synchronization', json_encode($response));
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('customers', 'passive synchronization', $th->getMessage());
        } finally {
            Utils::log('customers', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('customers', 0);
        }
    }
}
