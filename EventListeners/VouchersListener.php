<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Thelia\Model\Event\CouponEvent;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmVoucher;
use Shopimind\Data\VouchersData;
use Thelia\Model\Base\LangQuery;

class VouchersListener
{
    /**
     * Synchronizes data after a coupon is inserted.
     *
     * @param CouponEvent $event The event object triggering the action.
     */
    public static function postCouponInsert(CouponEvent $event): void
    {
        $coupon = $event->getModel();

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $couponDefault = $coupon->getTranslation( $defaultLocal );

        $data = [];

        foreach ( $langs as $lang ) {
            $couponTranslated = $coupon->getTranslation( $lang->getLocale() );

            $data[] = VouchersData::formatVoucher( $coupon, $couponTranslated, $couponDefault );
        }

        $response = SpmVoucher::bulkSave( Utils::getAuth(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'Voucher', 'Insert', json_encode( $response ), $coupon->getId() );
    }

    /**
     * Synchronizes data after a coupon is updated.
     *
     * @param CouponEvent $event The event object triggering the action.
     */
    public static function postCouponUpdate(CouponEvent $event): void
    {
        $coupon = $event->getModel();
        
        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $couponDefault = $coupon->getTranslation( $defaultLocal );
        
        $data = [];

        foreach ( $langs as $lang ) {
            $couponTranslated = $coupon->getTranslation( $lang->getLocale() );

            $data[] = VouchersData::formatVoucher( $coupon, $couponTranslated, $couponDefault );
        }

        $response = SpmVoucher::bulkUpdate( Utils::getAuth(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'Voucher', 'Update', json_encode( $response ), $coupon->getId() );
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
}
