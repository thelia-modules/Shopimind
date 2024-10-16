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

use Shopimind\Data\VouchersData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmVoucher;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\CouponQuery;

class SyncVouchers
{
    /**
     * Process synchronization for coupons.
     *
     * @param string $lastUpdate
     * @param array|int $ids
     * @param string $requestedBy
     * @return array
     */
    public static function processSyncVouchers(string $lastUpdate, array|int $ids, string $requestedBy): array
    {
        $vouchersIds = null;
        if (!empty($ids)) {
            $vouchersIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
        }

        if (empty($lastUpdate)) {
            if (empty($vouchersIds)) {
                $count = CouponQuery::create()->find()->count();
            } else {
                $count = CouponQuery::create()->filterById($vouchersIds)->find()->count();
            }
        } else {
            if (empty($vouchersIds)) {
                $count = CouponQuery::create()->filterByUpdatedAt($lastUpdate, '>=')->count();
            } else {
                $count = CouponQuery::create()->filterById($vouchersIds)->filterByUpdatedAt($lastUpdate, '>=')->count();
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
            && isset($synchronizationStatus['synchronization_status']['vouchers'])
            && $synchronizationStatus['synchronization_status']['vouchers'] == 1
        ) {
            return [
                'success' => false,
                'message' => 'A previous process is still running.',
            ];
        }

        Utils::updateSynchronizationStatus('vouchers', 1);

        Utils::launchSynchronisation('vouchers', $lastUpdate, $vouchersIds, $requestedBy);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Synchronizes vouchres.
     *
     * @param Request $request
     * @return void
     */
    public static function syncVouchers(Request $request): void
    {
        try {
            $body = json_decode($request->getContent(), true);

            $lastUpdate = (isset($body['last_update'])) ? $body['last_update'] : null;

            $vouchersIds = null;
            $ids = (isset($body['ids'])) ? $body['ids'] : null;
            if (!empty($ids)) {
                $vouchersIds = (!\is_array($ids) && $ids > 0) ? [$ids] : $ids;
            }

            $requestedBy = (isset($body['requestedBy'])) ? $body['requestedBy'] : null;

            $langs = LangQuery::create()->filterByActive(1)->find();
            $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();

            $offset = 0;
            $limit = intdiv(20, $langs->count());

            $hasMore = true;

            do {
                if (empty($lastUpdate)) {
                    if (empty($vouchersIds)) {
                        $coupons = CouponQuery::create()->offset($offset)->limit($limit)->find();
                    } else {
                        $coupons = CouponQuery::create()->filterById($vouchersIds)->offset($offset)->limit($limit)->find();
                    }
                } else {
                    $lastUpdate = trim($lastUpdate, '"\'');
                    if (empty($vouchersIds)) {
                        $coupons = CouponQuery::create()->offset($offset)->limit($limit)->filterByUpdatedAt($lastUpdate, '>=');
                    } else {
                        $coupons = CouponQuery::create()->filterById($vouchersIds)->offset($offset)->limit($limit)->filterByUpdatedAt($lastUpdate, '>=');
                    }
                }

                if ($coupons->count() < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }

                if ($coupons->count() > 0) {
                    $data = [];

                    foreach ($coupons as $coupon) {
                        $couponDefault = $coupon->getTranslation($defaultLocal);

                        foreach ($langs as $lang) {
                            $couponTranslated = $coupon->getTranslation($lang->getLocale());

                            $data[] = VouchersData::formatVoucher($coupon, $couponTranslated, $couponDefault);
                        }
                    }

                    $requestHeaders = $requestedBy ? ['answered-for' => $requestedBy] : [];
                    $response = SpmVoucher::bulkSave(Utils::getAuth($requestHeaders), $data);

                    Utils::handleResponse($response);

                    Utils::log('vouchers', 'passive synchronization', json_encode($response));
                }
            } while ($hasMore);
        } catch (\Throwable $th) {
            Utils::log('vouchers', 'passive synchronization', $th->getMessage());
        } finally {
            Utils::log('vouchers', 'passive synchronization', 'finally', null);
            Utils::updateSynchronizationStatus('vouchers', 0);
        }
    }
}
