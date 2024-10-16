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

namespace Shopimind\Data;

use Shopimind\lib\Utils;
use Thelia\Model\Base\CouponI18n;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\CurrencyQuery;

class VouchersData
{
    /**
     * Formats the voucher data to match the Shopimind format.
     *
     * @param Coupon $coupon
     * @param CouponI18n $couponTranslated
     * @param CouponI18n $couponDefault
     * @return array
     */
    public static function formatVoucher(Coupon $coupon, CouponI18n $couponTranslated, CouponI18n $couponDefault): array
    {
        $amount = 0;
        $value = $coupon->getEffects();

        if (isset($value['amount'])) {
            $amount = $value['amount'];
        } elseif (isset($value['percentage'])) {
            $amount = $value['percentage'];
        }

        return [
            'voucher_id' => (string) $coupon->getId(),
            'lang' => substr($couponTranslated->getLocale(), 0, 2),
            'code' => $coupon->getCode(),
            'description' => self::getDescription($couponTranslated, $couponDefault),
            'started_at' => $coupon->getStartDate() ? $coupon->getStartDate()->format('Y-m-d\TH:i:s.u\Z') : $coupon->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'ended_at' => $coupon->getExpirationDate() ? $coupon->getExpirationDate()->format('Y-m-d\TH:i:s.u\Z') : null,
            'customer_id' => self::getIdCustomer($coupon->getId()),
            'type_voucher' => self::getType($coupon->getId()),
            'value' => Utils::formatNumber($amount),
            'minimum_amount' => self::getMinimumAmount($coupon->getId()),
            'currency' => CurrencyQuery::create()->findOneByByDefault(true)->getCode(),
            'reduction_tax' => true,
            'is_used' => $coupon->getIsUsed(),
            'is_active' => $coupon->getIsEnabled(),
            'created_at' => $coupon->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $coupon->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    /**
     * Retrieves the minimum amount for a coupon identified by its ID.
     *
     * @param int $couponId
     * @return float|int
     */
    public static function getMinimumAmount(int $couponId): float|int
    {
        $coupon = CouponQuery::create()->findOneById($couponId);

        if (!empty($coupon)) {
            $conditions = json_decode(base64_decode($coupon->getSerializedConditions()));

            foreach ($conditions as $item) {
                if (!empty($item->operators->price) && (($item->operators->price === '>=') || ($item->operators->price === '>'))) {
                    $values = $item->values;
                    foreach ($values as $value) {
                        return Utils::formatNumber($value);
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Retrieves the minimum amount for a coupon identified by its ID.
     *
     * @param int $couponId
     * @return int|null
     */
    public static function getIdCustomer(int $couponId): int|null
    {
        $coupon = CouponQuery::create()->findOneById($couponId);
        $idsCustomers = null;

        if (!empty($coupon)) {
            $conditions = json_decode(base64_decode($coupon->getSerializedConditions()));
            foreach ($conditions as $item) {
                if (!empty($item->operators->customers) && $item->operators->customers === 'in') {
                    $customers = $item->values->customers;
                    $idsCustomers = $customers[0];
                    if (\count($customers) > 1) {
                        $idsCustomers = null;
                    }
                }
            }
        }

        return $idsCustomers;
    }

    /**
     * Retrieves type in Shopimind's format.
     *
     * @param int $couponId
     * @return string|null
     */
    public static function getType(int $couponId): string|null
    {
        $coupon = CouponQuery::create()->findOneById($couponId);
        if (!empty($coupon)) {
            switch ($coupon->getType()) {
                case 'thelia.coupon.type.remove_x_percent':
                    $typeFormated = 'percentage_reduction';
                    break;

                case 'thelia.coupon.type.remove_x_amount':
                    $typeFormated = 'amount_reduction';
                    if ($coupon->isRemovingPostage() == 1 && $coupon->getAmount() == 0) {
                        $typeFormated = 'free_shipping';
                    }
                    break;

                default:
                    $typeFormated = 'amount_reduction';
                    break;
            }

            return $typeFormated;
        }

        return null;
    }

    /**
     * Retrieves description.
     *
     * @param CouponI18n $couponTranslated
     * @param CouponI18n $couponDefault
     * @return string
     */
    public static function getDescription(CouponI18n $couponTranslated, CouponI18n $couponDefault): string
    {
        return $couponTranslated->getTitle() ?? $couponDefault->getTitle();
    }
}
