<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\CouponEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmVoucher;
use Shopimind\Data\VouchersData;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\CouponI18nQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\Coupon;

class VouchersListener
{
    /**
     * Synchronizes data after a coupon is inserted.
     *
     * @param CouponEvent $event The event object triggering the action.
     */
    public static function postCouponInsert(CouponEvent $event): void
    {
        self::syncCoupon( $event->getModel(), 'Insert' );
    }

    /**
     * Synchronizes data after a coupon is updated.
     *
     * @param CouponEvent $event The event object triggering the action.
     */
    public static function postCouponUpdate(CouponEvent $event): void
    {
        self::syncCoupon( $event->getModel(), 'Update' );
    }

    /**
     * Synchronizes data after a coupon is deleted.
     *
     * @param CouponEvent $event The event object triggering the action.
     */
    public static function postCouponDelete(CouponEvent $event): void
    {
        $coupon = $event->getModel()->getId();
        
        $response = SpmVoucher::delete( Utils::getAuth(), $coupon );
        
        Utils::handleResponse( $response );

        Utils::log( 'Voucher', 'Delete', json_encode( $response ), $coupon );
    }

    /**
     * Envoie le bon dans chaque langue active.
     *
     * @param Coupon $coupon
     * @param string $action libellé du log
     */
    public static function syncCoupon( Coupon $coupon, string $action ): void
    {
        $couponId = $coupon->getId();

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();

        $couponDefault = CouponI18nQuery::create()
            ->filterById( $couponId )
            ->filterByLocale( $defaultLocal )
            ->findOne();

        $data = [];

        foreach ( $langs as $lang ) {
            $couponTranslated = CouponI18nQuery::create()
                ->filterById( $couponId )
                ->filterByLocale( $lang->getLocale() )
                ->findOne();

            if ( !$couponTranslated ) {
                $couponTranslated = $couponDefault;
            }

            $data[] = VouchersData::formatVoucher( $coupon, $couponTranslated, $couponDefault );
        }

        $response = SpmVoucher::bulkSave( Utils::getAuth(), $data );

        Utils::handleResponse( $response );

        Utils::log( 'Voucher', $action, json_encode( $response ), $couponId );
    }
}
