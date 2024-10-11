<?php

namespace Shopimind\Data;

use Thelia\Model\Category;
use Thelia\Model\CategoryI18n;
use Thelia\Model\Base\LangQuery;

class ProductsCategoriesData
{
    /**
     * Formats the product category data to match the Shopimind format.
     *
     * @param Category $productCategory
     * @param CategoryI18n $categoryTranslated
     * @param CategoryI18n $categoryDefault
     * @return array
     */
    public static function formatProductCategory( Category $productCategory, CategoryI18n $categoryTranslated, CategoryI18n $categoryDefault ): array
    {
        return [
            "category_id" => intval( $productCategory->getId() ),
            "lang" => substr( $categoryTranslated->getLocale()  , 0, 2 ),
            "name" => self::getName( $categoryTranslated, $categoryDefault ) ?? '',
            "description" => self::getDescription( $categoryTranslated, $categoryDefault ) ?? '',
            "parent_category_id" => $productCategory->getParent() ? intval( $productCategory->getParent() ) : null,
            "link" => $productCategory->getUrl( $categoryTranslated->getLocale() ),
            "is_active" => ( bool ) $productCategory->getVisible(),
            "created_at" => $productCategory->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            "updated_at" => $productCategory->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z')
        ];
    }

    /**
     * Retrieves category name
     *
     * @param CategoryI18n $categoryTranslated
     * @param CategoryI18n $categoryDefault
     * @return mixed
     */
    public static function getName( CategoryI18n $categoryTranslated, CategoryI18n $categoryDefault ){
        $name = $categoryTranslated->getTitle() ?? $categoryDefault->getTitle();

        return $name;
    }

    /**
     * Retrieves category dscription
     *
     * @param CategoryI18n $categoryTranslated
     * @param CategoryI18n $categoryDefault
     * @return mixed
     */
    public static function getDescription( CategoryI18n $categoryTranslated, CategoryI18n $categoryDefault ){
        $description = $categoryTranslated->getDescription() ?? $categoryDefault->getDescription();

        return $description;
    }
}
