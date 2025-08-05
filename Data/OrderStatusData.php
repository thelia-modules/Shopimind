<?php

namespace Shopimind\Data;

use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusI18n;
use Thelia\Model\Base\LangQuery;

class OrderStatusData
{
    /**
     * Formats the order status data to match the Shopimind format.
     *
     * @param OrderStatus $orderStatus
     * @param OrderStatusI18n $orderStatusTranslated
     * @param OrderStatusI18n $orderStatusDefault
     * @return array
     */
    public static function formatOrderStatus( OrderStatus $orderStatus, OrderStatusI18n $orderStatusTranslated, OrderStatusI18n $orderStatusDefault ): array
    {
        return [
            'status_id' => strval( $orderStatus->getId() ),
            'lang' => substr( $orderStatusTranslated->getLocale()  , 0, 2 ),
            'name' => self::getOrderStatusTitle($orderStatus, $orderStatusTranslated->getLocale()),
            'is_deleted'=> false,
            'created_at' => $orderStatus->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $orderStatus->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z')
        ];
    }

    /**
     * Gets the order status title for a given locale.
     *
     * @param OrderStatus $orderStatus
     * @param string $locale
     * @return string
     */
    public static function getOrderStatusTitle(OrderStatus $orderStatus, string $locale)
    {
        $defaultLocale = LangQuery::create()->findOneByByDefault(true)?->getLocale();
        $titleByDefault = null;

        foreach ( $orderStatus->getOrderStatusI18ns() as $i18n ) {
            if ( $i18n->getLocale() === $locale && $i18n->getTitle() !== null ) {
                return $i18n->getTitle();
            }

            if ( $i18n->getLocale() === $defaultLocale && $i18n->getTitle() !== null ) {
                $titleByDefault = $i18n->getTitle();
            }
        }

        return $titleByDefault;
    }
}
