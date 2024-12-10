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

use Thelia\Model\Brand;

class ProductsManufacturersData
{
    /**
     * Formats the product manufacturer data to match the Shopimind format.
     */
    public function formatProductmanufacturer(Brand $brand): array
    {
        return [
            'manufacturer_id' => (string) $brand->getId(),
            'name' => $brand->getTitle() ?? '',
            'is_active' => (bool) $brand->getVisible(),
            'created_at' => $brand->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $brand->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
