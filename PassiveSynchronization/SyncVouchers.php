<?php

namespace Shopimind\PassiveSynchronization;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Thelia\Model\CouponQuery;
use Shopimind\lib\Utils;
use Shopimind\lib\SyncHttpClient;
use Thelia\Model\Base\LangQuery;
use Shopimind\SdkShopimind\SpmVoucher;
use Shopimind\Data\VouchersData;

class SyncVouchers
{
    /**
     * Process synchronization for coupons
     *
     * @param array $param
     * @return array
     */
    public static function processSyncVouchers( array $param ): array
    {
        try {
            $langs = LangQuery::create()->filterByActive( 1 )->find();
            $defaultLocal = LangQuery::create()->findOneByByDefault( true )->getLocale();
            $langsCount = $langs->count();

            $offset = isset( $param['start'] ) ? (int) $param['start'] : 0;
            $limit = isset( $param['limit'] ) ? (int) $param['limit'] : 20;
            $apiMaxObjects = 20;
            $idsRequested = ( array_key_exists( 'ids', $param ) ) ? $param['ids'] : '';
            $lastUpdate = ( array_key_exists( 'lastUpdate', $param ) ) ? $param['lastUpdate'] : '';

            $extraHeaders = [];
            if (!empty($param['id_shop_ask_syncs'])) {
                $extraHeaders['X-Shopimind-Sync-Id'] = $param['id_shop_ask_syncs'];
            }

            $hasMore = true;

            $query = CouponQuery::create();

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
                    'total_count' => (int) $count * $langsCount
                ];
            }

            $query->orderByUpdatedAt();
            // Tri total (updated_at, id) : sans id, les lignes au même updated_at peuvent changer d'ordre d'une page à l'autre.
            $query->orderById();
            $query->offset( $offset );
            $query->limit( $limit );

            $coupons = $query->find();

            if ( $coupons->count() < $limit ) {
                $hasMore = false;
            }

            if ( $coupons->count() > 0 ) {
                $data = [];
                foreach ( $coupons as $coupon ) {
                    $couponDefault = $coupon->getTranslation( $defaultLocal );

                    foreach ( $langs as $lang ) {
                        $couponTranslated = $coupon->getTranslation( $lang->getLocale() );
                        $data[] = VouchersData::formatVoucher( $coupon, $couponTranslated, $couponDefault );
                    }
                }

                $lastObjectUpdate = null;
                foreach ($data as $item) {
                    $dateUpd = $item['updated_at'] ?? $item['date_upd'] ?? null;
                    if ($dateUpd !== null && ($lastObjectUpdate === null || $dateUpd > $lastObjectUpdate)) {
                        $lastObjectUpdate = $dateUpd;
                    }
                }

                $chunks = array_chunk( $data, $apiMaxObjects );
                $sentCount = 0;
                $failedCount = 0;
                $rejectedCount = 0;
                $errors = [];

                foreach ( $chunks as $chunk ) {
                    $response = SyncHttpClient::executeWithRetry(
                        function() use ($chunk, $extraHeaders) {
                            return SpmVoucher::bulkSave(Utils::getAuth($extraHeaders), $chunk);
                        },
                        'SyncVouchers'
                    );

                    Utils::log( 'vouchers', 'passive synchronization', json_encode( $response ) );

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
            Utils::logException( 'Passive', 'vouchers', $th );
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
