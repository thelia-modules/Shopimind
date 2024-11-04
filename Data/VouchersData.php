<?php

namespace Shopimind\Data;

use Thelia\Model\CouponQuery;
use Thelia\Model\Coupon;
use Thelia\Model\Base\CouponI18n;
use Thelia\Model\CurrencyQuery;
use Shopimind\lib\Utils;

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
    public static function formatVoucher( Coupon $coupon, CouponI18n $couponTranslated, CouponI18n $couponDefault ): array
    {
        $amount = 0;
        $value = $coupon->getEffects();

        if ( isset( $value['amount'] ) ) {
            $amount = $value['amount'];
        } else if ( isset( $value['percentage'] ) ) {
            $amount = $value['percentage'];
        }

        return [
            "voucher_id" => strval( $coupon->getId() ),
            "lang" => substr( $couponTranslated->getLocale()  , 0, 2 ),
            "code" => $coupon->getCode(),
            "description" => self::getDescription( $couponTranslated, $couponDefault ) ?? 'Code de réduction',
            "started_at" => $coupon->getStartDate() ? $coupon->getStartDate()->format('Y-m-d\TH:i:s.u\Z') : $coupon->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            "ended_at" => $coupon->getExpirationDate() ? $coupon->getExpirationDate()->format('Y-m-d\TH:i:s.u\Z') : null,
            "customer_id" => self::getIdCustomer( $coupon->getId() ),
            "type_voucher" => self::getType( $coupon->getId() ),
            "value" => Utils::formatNumber( $amount ),
            "minimum_amount" => self::getMinimumAmount( $coupon->getId() ),
            "currency" => CurrencyQuery::create()->findOneByByDefault(true)->getCode(),
            "reduction_tax" => true,
            "is_used" => ( bool ) $coupon->getIsUsed(),
            "is_active" => ( bool ) $coupon->getIsEnabled(),
            "created_at" => $coupon->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            "updated_at" => $coupon->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z')
        ];
    }

    /**
     * Retrieves the minimum amount for a coupon identified by its ID.
     *
     * @param int $couponId The ID of the coupon.
     */
    public static function getMinimumAmount( int $couponId ): float|int
    {
        $coupon = CouponQuery::create()->findOneById( $couponId );

        if ( !empty($coupon) ) {
            $conditions = json_decode( base64_decode( $coupon->getSerializedConditions() ) );

            foreach ($conditions as $item) {
                if ( !empty($item->operators->price) && ( ( $item->operators->price === '>=' ) || ( $item->operators->price === '>' ) ) ) {
                    $values = $item->values;
                    foreach ($values as $value) {
                        return Utils::formatNumber( $value );
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Retrieves the minimum amount for a coupon identified by its ID.
     *
     * @param int $couponId The ID of the coupon.
     */
    public static function getIdCustomer( int $couponId ){
        $coupon = CouponQuery::create()->findOneById( $couponId );
        $idsCustomers = null;

        if ( !empty($coupon) ) {
            $conditions = json_decode( base64_decode( $coupon->getSerializedConditions() ) );
            foreach ($conditions as $item) {
                if ( !empty($item->operators->customers ) && $item->operators->customers === 'in') {
                    $customers = $item->values->customers;
                    if ( count($customers) > 1 ) {
                        $idsCustomers = null;
                    }else {
                        $idsCustomers = $customers[0];
                    }
                }
            }
        }

        return $idsCustomers;
    }

    /**
     * Retrieves type in Shopimind's format
     *
     * @param int $couponId
     * @return string|null
     */
    public static function getType( int $couponId ){
        $coupon = CouponQuery::create()->findOneById( $couponId );
        if ( !empty( $coupon ) ) {
            $typeFormated = "";
            
            switch ( $coupon->getType() ) {
                case 'thelia.coupon.type.remove_x_percent':
                    $typeFormated = 'percentage_reduction';
                    break;
                    
                case 'thelia.coupon.type.remove_x_amount':
                    $typeFormated = 'amount_reduction';
                    if ( $coupon->isRemovingPostage() == 1 && $coupon->getAmount() == 0 ) {
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
     * Retrieves description
     *
     * @param CouponI18n $couponTranslated
     * @param CouponI18n $couponDefault
     * @return mixed
     */
    public static function getDescription( CouponI18n $couponTranslated, CouponI18n $couponDefault ){
        return $couponTranslated->getTitle() ?? $couponDefault->getTitle();
    }
}
