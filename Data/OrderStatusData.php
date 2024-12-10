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

use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusI18n;

class OrderStatusData
{
    /**
     * Formats the order status data to match the Shopimind format.
     */
    public function formatOrderStatus(OrderStatus $orderStatus, OrderStatusI18n $orderStatusTranslated): array
    {
        return [
            'status_id' => (string) $orderStatus->getId(),
            'lang' => substr($orderStatusTranslated->getLocale(), 0, 2),
            'name' => $orderStatus->getCode(),
            'is_deleted' => false,
            'created_at' => $orderStatus->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $orderStatus->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
