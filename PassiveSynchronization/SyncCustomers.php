<?php

namespace Shopimind\PassiveSynchronization;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Thelia\Model\CustomerQuery;
use Shopimind\Data\CustomersData;
use Shopimind\lib\Utils;
use Shopimind\lib\SyncHttpClient;
use Shopimind\SdkShopimind\SpmCustomers;

class SyncCustomers
{
    /**
     * Process synchronization for customers
     *
     * @param array $param
     * @return array
     */
    public static function processSyncCustomers( array $param ): array
    {
        try {
            $offset = isset( $param['start'] ) ? (int) $param['start'] : 0;
            $limit = isset( $param['limit'] ) ? (int) $param['limit'] : 20;
            $idsRequested = ( array_key_exists( 'ids', $param ) ) ? $param['ids'] : '';
            $lastUpdate = ( array_key_exists( 'lastUpdate', $param ) ) ? $param['lastUpdate'] : '';

            $extraHeaders = [];
            if (!empty($param['id_shop_ask_syncs'])) {
                $extraHeaders['X-Shopimind-Sync-Id'] = $param['id_shop_ask_syncs'];
            }

            $hasMore = true;

            $query = CustomerQuery::create();

            if ( !empty( $lastUpdate ) && $lastUpdate !== 'null' ) {
                $lastUpdate = trim( $lastUpdate, '"\'' );
                $query->filterByUpdatedAt( $lastUpdate, '>=' );
            }

            if ( !empty( $idsRequested ) ) {
                $idsRequested = is_array( $idsRequested ) ? $idsRequested : explode( ',', (string) $idsRequested );
                $query->filterById( $idsRequested );
            }

            if ( isset( $param['just_count'] ) && $param['just_count'] ) {
                $count = $query->count();

                return [
                    'success' => true,
                    'total_count' => (int) $count
                ];
            }

            $query->orderByUpdatedAt();
            // Tri total (updated_at, id) : sans id, les lignes au même updated_at peuvent changer d'ordre d'une page à l'autre.
            $query->orderById();
            $query->offset( $offset );
            $query->limit( $limit );

            $customers = $query->find();

            if ( $customers->count() < $limit ) {
                $hasMore = false;
            }

            if ( $customers->count() > 0 ) {
                $data = [];
                foreach ( $customers as $customer ) {
                    $data[] = CustomersData::formatCustomer( $customer );
                }

                $lastObjectUpdate = null;
                foreach ($data as $item) {
                    $dateUpd = $item['updated_at'] ?? $item['date_upd'] ?? null;
                    if ($dateUpd !== null && ($lastObjectUpdate === null || $dateUpd > $lastObjectUpdate)) {
                        $lastObjectUpdate = $dateUpd;
                    }
                }

                $response = SyncHttpClient::executeWithRetry(
                    function() use ($data, $extraHeaders) {
                        return SpmCustomers::bulkSave(Utils::getAuth($extraHeaders), $data);
                    },
                    'SyncCustomers'
                );

                Utils::log( 'customers', 'passive synchronization', json_encode( $response ) );

                $success = isset( $response['statusCode'] ) && $response['statusCode'] === 200;

                if ($success) {
                    $counters = SyncHttpClient::extractCounters($response, count($data));
                    $sentCount = $counters['sent_count'];
                    $rejectedCount = $counters['rejected_count'];
                    $failedCount = 0;
                } else {
                    $sentCount = 0;
                    $rejectedCount = 0;
                    $failedCount = count($data);
                }

                return [
                    'success' => $success,
                    'sent_count' => $sentCount,
                    'failed_count' => $failedCount,
                    'rejected_count' => $rejectedCount,
                    'has_more' => $hasMore,
                    'last_object_update' => $lastObjectUpdate,
                    'errors' => !$success ? [ [ 'message' => $response['message'] ?? 'Unknown error', 'status_code' => $response['statusCode'] ?? 0 ] ] : []
                ];
            } else {
                return [
                    'success' => true,
                    'sent_count' => 0,
                    'failed_count' => 0,
                    'rejected_count' => 0,
                    'has_more' => false,
                    'last_object_update' => null
                ];
            }
        } catch ( \Throwable $th ) {
            Utils::logException( 'Passive', 'customers', $th );
            return [
                'success' => false,
                'sent_count' => 0,
                'failed_count' => 0,
                'rejected_count' => 0,
                'has_more' => false,
                'last_object_update' => null,
                'errors' => [ [ 'message' => Utils::toUtf8( $th->getMessage() ), 'status_code' => 0 ] ]
            ];
        }
    }
}
