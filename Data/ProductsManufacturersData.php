<?php

namespace Shopimind\Data;

use Shopimind\lib\Utils;
use Thelia\Model\Brand;

class ProductsManufacturersData
{
    /**
     * Formats the product manufacturer data to match the Shopimind format.
     *
     * @param Brand $brand
     * @return array
     */
    public static function formatProductmanufacturer( Brand $brand ): array
    {
        return [
            'manufacturer_id' => strval( $brand->getId() ),
            'name' => $brand->getTitle() ?? '',
            'is_active' => ( bool ) $brand->getVisible(),
            "created_at" => $brand->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            "updated_at" => $brand->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z')
        ];
    }
}
