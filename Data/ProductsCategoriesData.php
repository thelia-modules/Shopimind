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

use Thelia\Model\Category;
use Thelia\Model\CategoryI18n;

class ProductsCategoriesData
{
    /**
     * Formats the product category data to match the Shopimind format.
     */
    public function formatProductCategory(Category $productCategory, CategoryI18n $categoryTranslated, CategoryI18n $categoryDefault): array
    {
        return [
            'category_id' => $productCategory->getId(),
            'lang' => substr($categoryTranslated->getLocale(), 0, 2),
            'name' => self::getName($categoryTranslated, $categoryDefault) ?? '',
            'description' => self::getDescription($categoryTranslated, $categoryDefault) ?? '',
            'parent_category_id' => $productCategory->getParent(),
            'link' => $productCategory->getUrl($categoryTranslated->getLocale()),
            'is_active' => (bool) $productCategory->getVisible(),
            'created_at' => $productCategory->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $productCategory->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    /**
     * Retrieves category name.
     */
    public static function getName(CategoryI18n $categoryTranslated, CategoryI18n $categoryDefault): string
    {
        return $categoryTranslated->getTitle() ?? $categoryDefault->getTitle();
    }

    /**
     * Retrieves category dscription.
     */
    public static function getDescription(CategoryI18n $categoryTranslated, CategoryI18n $categoryDefault): string
    {
        return $categoryTranslated->getDescription() ?? $categoryDefault->getDescription();
    }
}
