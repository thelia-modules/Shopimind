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
     * Formats the voucher data to match the ShopiMind format.
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

        $data = [
            "voucher_id" => strval( $coupon->getId() ),
            "lang" => substr( $couponTranslated->getLocale()  , 0, 2 ),
            "code" => $coupon->getCode(),
            "description" => self::getDescription( $couponTranslated, $couponDefault ) ?? 'Code de réduction',
            "started_at" => $coupon->getStartDate() ? $coupon->getStartDate()->format('Y-m-d\TH:i:s.uP') : $coupon->getCreatedAt()->format('Y-m-d\TH:i:s.uP'),
            "ended_at" => $coupon->getExpirationDate() ? $coupon->getExpirationDate()->format('Y-m-d\TH:i:s.uP') : null,
            "customer_id" => self::getIdCustomer( $coupon->getId() ),
            "type_voucher" => self::getType( $coupon->getId() ),
            "value" => Utils::formatNumber( $amount ),
            "minimum_amount" => self::getMinimumAmount( $coupon->getId() ),
            "currency" => self::getCurrency( $coupon ),
            "reduction_tax" => true,
            "is_used" => self::isUsed( $coupon ),
            "is_active" => ( bool ) $coupon->getIsEnabled(),
            "created_at" => $coupon->getCreatedAt()->format('Y-m-d\TH:i:s.uP'),
            "updated_at" => $coupon->getUpdatedAt()->format('Y-m-d\TH:i:s.uP')
        ];

        $data['source_label'] = Utils::getSourceLabel();
        return $data;
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
            foreach ( is_array( $conditions ) ? $conditions : [] as $item ) {
                if ( !empty($item->operators->customers ) && $item->operators->customers === 'in') {
                    $customers = $item->values->customers;
                    // Bon nominatif : un seul client. customer_id doit être une chaîne pour ShopiMind :
                    // l'identifiant entier stocké dans la condition faisait rejeter le bon.
                    if ( is_array( $customers ) && count( $customers ) === 1 ) {
                        $idsCustomers = strval( reset( $customers ) );
                    } else {
                        $idsCustomers = null;
                    }
                }
            }
        }

        return $idsCustomers;
    }

    /**
     * Retrieves type in ShopiMind's format
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
                    // Unknown Thelia coupon type (custom or extension) —
                    // map to 'specific_coupon' rather than guessing
                    // 'amount_reduction', which would mislabel the voucher
                    // on the ShopiMind side.
                    $typeFormated = 'specific_coupon';
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

    /**
     * Devise de la condition « montant minimum » du coupon (celle choisie à la création du bon),
     * sinon devise par défaut de la boutique. Le module envoyait toujours la devise par défaut.
     *
     * @param Coupon $coupon
     * @return string
     */
    public static function getCurrency( Coupon $coupon ): string
    {
        $conditions = json_decode( base64_decode( (string) $coupon->getSerializedConditions() ), true );
        if ( is_array( $conditions ) ) {
            foreach ( $conditions as $condition ) {
                if ( !empty( $condition['values']['currency'] ) && is_string( $condition['values']['currency'] ) ) {
                    return $condition['values']['currency'];
                }
            }
        }

        return CurrencyQuery::create()->findOneByByDefault(true)->getCode();
    }

    /**
     * Bon épuisé. Thelia ne met jamais la colonne is_used à jour : un usage diminue max_usage, ou seulement
     * le compteur du client pour un bon à usage par client.
     *
     * @param Coupon $coupon
     * @return bool
     */
    public static function isUsed( Coupon $coupon ): bool
    {
        if ( (bool) $coupon->getIsUsed() ) {
            return true;
        }

        if ( $coupon->isUsageUnlimited() ) {
            return false;
        }

        if ( $coupon->getPerCustomerUsageCount() ) {
            $customerId = self::getIdCustomer( (int) $coupon->getId() );

            return null !== $customerId && $coupon->getUsagesLeft( (int) $customerId ) <= 0;
        }

        return (int) $coupon->getMaxUsage() <= 0;
    }
}
