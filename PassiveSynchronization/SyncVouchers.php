<?php

namespace Shopimind\PassiveSynchronization;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Thelia\Model\CouponQuery;
use Shopimind\lib\Utils;
use Thelia\Model\Base\LangQuery;
use Shopimind\SdkShopimind\SpmVoucher;
use Shopimind\Data\VouchersData;
use Symfony\Component\HttpFoundation\Request;

class SyncVouchers
{
    /**
     * Process synchronization for coupons
     *
     * @param $lastUpdate
     * @param $ids
     * @return array
     */
    public static function processSyncVouchers( $lastUpdate, $ids, $requestedBy ): array
    {
        $vouchersIds = null;
        if ( !empty( $ids ) ) {
            $vouchersIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
        }

        if ( empty( $lastUpdate ) ) {
            if ( empty( $vouchersIds ) ) {
                $count = CouponQuery::create()->find()->count();
            }else {
                $count = CouponQuery::create()->filterById( $vouchersIds )->find()->count();                
            }
        } else {
            if ( empty( $vouchersIds ) ) {
                $count = CouponQuery::create()->filterByUpdatedAt( $lastUpdate, '>=')->count();
            }else {
                $count = CouponQuery::create()->filterById( $vouchersIds )->filterByUpdatedAt( $lastUpdate, '>=')->count();                
            }
        }

        if ( $count == 0 ) {
            return [
                'success' => true,
                'count' => 0,
            ];
        }

        $synchronizationStatus = Utils::loadSynchronizationStatus();
        
        if (
            $synchronizationStatus &&
            isset($synchronizationStatus['synchronization_status']['vouchers'])
            && $synchronizationStatus['synchronization_status']['vouchers'] == 1
            ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus( 'vouchers', 1 );

        Utils::launchSynchronisation( 'vouchers', $lastUpdate, $vouchersIds, $requestedBy );

        return [
            'success' => true,
            'count' => $count,
        ];
    }

     /**
     * Synchronizes vouchres.
     *
     * @return void
     */
    public static function syncVouchers( Request $request )
    {
        try {
            $body =  json_decode( $request->getContent(), true );

            $lastUpdate = ( isset( $body['last_update'] ) ) ? $body['last_update'] : null;

            $vouchersIds = null;
            $ids = ( isset( $body['ids'] ) ) ? $body['ids'] : null;
            if ( !empty( $ids ) ) {
                $vouchersIds = ( !is_array( $ids ) && $ids > 0 ) ? array( $ids ) : $ids;
            }

            $requestedBy = ( isset( $body['requestedBy'] ) ) ? $body['requestedBy'] : null;

            $langs = LangQuery::create()->filterByActive( 1 )->find();
            $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();

            $offset = 0;
            $limit = intdiv( 20, $langs->count() );

            $hasMore = true;

            do {
                if ( empty( $lastUpdate ) ) {
                    if ( empty( $vouchersIds ) ) {
                        $coupons = CouponQuery::create()->offset( $offset )->limit( $limit )->find();
                    }else {
                        $coupons = CouponQuery::create()->filterById( $vouchersIds )->offset( $offset )->limit( $limit )->find();
                    }
                } else {
                    $lastUpdate = trim( $lastUpdate, '"\'');
                    if ( empty( $vouchersIds ) ) {
                        $coupons = CouponQuery::create()->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );
                    }else {
                        $coupons = CouponQuery::create()->filterById( $vouchersIds )->offset( $offset )->limit( $limit )->filterByUpdatedAt( $lastUpdate, '>=' );                        
                    }
                }
        
                if ( $coupons->count() < $limit ) {
                    $hasMore = false;
                } else {
                    $offset += $limit;    
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
        
                    $requestHeaders = $requestedBy ? [ 'answered-for' => $requestedBy ] : [];
                    $response = SpmVoucher::bulkSave( Utils::getAuth( $requestHeaders ), $data );
                    
                    Utils::handleResponse( $response );
        
                    Utils::log( 'vouchers' , 'passive synchronization', json_encode( $response ) );
                }
            } while ( $hasMore );
        
        } catch (\Throwable $th) {
            Utils::log( 'vouchers' , 'passive synchronization' , $th->getMessage() );
        }  finally {
            Utils::log( 'vouchers', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus( 'vouchers', 0 );
        }
    }
}
