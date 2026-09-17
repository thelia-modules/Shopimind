<?php

namespace Shopimind\PassiveSynchronization;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Thelia\Model\AddressQuery;
use Shopimind\SdkShopimind\SpmCustomersAddresses;
use Shopimind\Data\CustomersAddressesData;
use Shopimind\lib\Utils;
use Shopimind\lib\SyncHttpClient;

class SyncCustomersAddresses
{
    /**
     * Process synchronization for customers addresses
     *
     * @param array $param
     * @return array
     */
    public static function processSyncCustomersAddresses( array $param ): array
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

            $query = AddressQuery::create();

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
            $query->orderBy( 'customer_id' );
            // Tri total (updated_at, customer_id, id) : sans id, les adresses d'un même client au même updated_at peuvent changer d'ordre d'une page à l'autre.
            $query->orderById();
            $query->offset( $offset );
            $query->limit( $limit );

            $customersAddresses = $query->find();

            if ( $customersAddresses->count() < $limit ) {
                $hasMore = false;
            }

            if ( $customersAddresses->count() > 0 ) {
                $sentCount = 0;
                $failedCount = 0;
                $rejectedCount = 0;
                $errors = [];

                $data = [];
                foreach ( $customersAddresses as $customerAddress ) {
                    $formattedAddress = CustomersAddressesData::formatCustomerAddress( $customerAddress );
                    $formattedAddress['customer_id'] = strval( $customerAddress->getCustomerId() );
                    $data[] = $formattedAddress;
                }

                $lastObjectUpdate = null;
                foreach ($data as $item) {
                    $dateUpd = $item['updated_at'] ?? $item['date_upd'] ?? null;
                    if ($dateUpd !== null && ($lastObjectUpdate === null || $dateUpd > $lastObjectUpdate)) {
                        $lastObjectUpdate = $dateUpd;
                    }
                }

                $apiMaxObjects = 20;
                $chunks = array_chunk($data, $apiMaxObjects);
                foreach ($chunks as $chunk) {
                    $response = SyncHttpClient::executeWithRetry(
                        function() use ($chunk, $extraHeaders) {
                            return SpmCustomersAddresses::bulkSaveAll(Utils::getAuth($extraHeaders), $chunk);
                        },
                        'SyncCustomersAddresses (bulk ' . count($chunk) . ' addresses)'
                    );

                    Utils::log( 'customersAddresses', 'passive synchronization', json_encode( $response ) );

                    $success = isset( $response['statusCode'] ) && $response['statusCode'] === 200;

                    if ($success) {
                        $counters = SyncHttpClient::extractCounters($response, count($chunk));
                        $sentCount += $counters['sent_count'];
                        $rejectedCount += $counters['rejected_count'];
                    } else {
                        $failedCount += count($chunk);
                        $errors[] = [
                            'message' => $response['message'] ?? 'Unknown error',
                            'status_code' => $response['statusCode'] ?? 0
                        ];
                    }
                }

                return [
                    'success' => $failedCount === 0,
                    'sent_count' => $sentCount,
                    'failed_count' => $failedCount,
                    'rejected_count' => $rejectedCount,
                    'has_more' => $hasMore,
                    'last_object_update' => $lastObjectUpdate,
                    'errors' => $errors
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
            Utils::logException( 'Passive', 'customersAddresses', $th );
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
