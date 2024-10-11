<?php

namespace Shopimind\Data;

use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusI18n;

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
            'name' => $orderStatus->getCode(),
            'is_deleted'=> false,
            'created_at' => $orderStatus->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $orderStatus->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z')
        ];
    }
}
