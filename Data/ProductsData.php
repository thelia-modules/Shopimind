<?php

namespace Shopimind\Data;

use Thelia\Model\ProductQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Shopimind\lib\Utils;
use Thelia\Model\Product;
use Thelia\Model\Base\ProductI18n;
use Shopimind\Model\Base\ShopimindQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Country;
use Shopimind\Data\ProductImagesData;

class ProductsData
{
    /**
     * Formats the product data to match the Shopimind format.
     *
     * @param Product $product
     * @param ProductI18n $productTranslated
     * @param ProductI18n $productDefault
     * @param EventDispatcherInterface $dispatcher
     * @return array
     */
    public static function formatProduct( Product $product, ProductI18n $productTranslated, ProductI18n $productDefault, EventDispatcherInterface $dispatcher ): array
    {
        $config = ShopimindQuery::create()->findOne();

        $active = (bool) $product->getVisible();

        $quantity = self::getQuantity( $product->getId() );
        if ( !$quantity && $config->getOutOfStockProductDisabling() ) {
            $active = false;
        }

        return [
            "product_id" => intval( $product->getId() ),
            "lang" => substr( $productTranslated->getLocale()  , 0, 2 ),
            "name" => self::getName( $productTranslated, $productDefault ) ?? '',
            "reference" => $product->getRef(),
            "ean13" => self::getEanCode( $product->getId() ),
            "description" => self::getDescription( $productTranslated, $productDefault ) ?? "",
            "description_short" => self::getShortDescription( $productTranslated, $productDefault ) ?? "",
            "link" => $product->getUrl( $productTranslated->getLocale() ),
            "category_ids" => self::formatCategoriesIds( $product->getProductCategories() ),
            "manufacturer_id" =>  ( !empty( $product->getBrandId() ) ) ? strval( $product->getBrandId( ) ) : null,
            "currency" => self::getCurrency( $product->getId() ),
            "image_link" => self::getDefaultImage( $product->getId(), $dispatcher ) ?? "https://placehold.co/300x300",
            "price" => $product->getTaxedPrice( Country::getDefaultCountry(), self::getPrice( $product->getId() ) ),
            "price_discount" => $product->getTaxedPrice( Country::getDefaultCountry(), self::getPromoPrice( $product->getId() ) ),
            "quantity_remaining" => $quantity,
            "is_active" => $active,
            "created_at" => $product->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            "updated_at" => $product->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z')
        ];
    }

    /**
     * Formats an array of product categories.
     *
     * @param array $productCategories The array of product categories.
     */
    public static function formatCategoriesIds( $productCategories ): array
    {
        $categoryIds = [];
        foreach ($productCategories as $productCategory) {
            $category = $productCategory->getCategory();
            if ($category) {
                array_push( $categoryIds, intval( $category->getId() ) );
            }
        }
        return $categoryIds;
    }

    /**
     * Retrieves the price for a product identified by its ID.
     *
     * @param int $productId The ID of the product.
     */
    public static function getPrice( int $productId ): float|int
    {
        $productSalesElement = ProductSaleElementsQuery::create()->filterByArray(['productId' => $productId, 'isDefault' => 1])->findOne();
        if ( !empty($productSalesElement) ) {
            $productSalesElementId = $productSalesElement->getId();
            $productPrice = ProductPriceQuery::create()->findOneByProductSaleElementsId( $productSalesElementId );
            if ( !empty( $productPrice ) ) {
                return Utils::formatNumber( $productPrice->getPrice() );
            }
        }

        return 0;
    }

    /**
     * Retrieves the promotional price for a product identified by its ID.
     *
     * @param int $productId The ID of the product.
     */
    public static function getPromoPrice( int $productId ){
        $productSalesElement = ProductSaleElementsQuery::create()->filterByArray(['productId' => $productId, 'isDefault' => 1])->findOne();
        if ( !empty($productSalesElement) ) {
            $productSalesElementId = $productSalesElement->getId();
            $productPromoPrice = ProductPriceQuery::create()->findOneByProductSaleElementsId( $productSalesElementId );
            if ( !empty( $productPromoPrice ) ) {
                return Utils::formatNumber( $productPromoPrice->getPromoPrice() );
            }
        }
    }

    /**
     * Retrieves the quantity for a product identified by its ID.
     *
     * @param int $productId The ID of the product.
     */
    public static function getQuantity( int $productId ){
        $productSalesElements = ProductSaleElementsQuery::create()->filterByArray([ 'productId' => $productId ])->find();
        
        $quantity = 0;

        foreach ($productSalesElements as $productSalesElement) {
            $quantity += $productSalesElement->getQuantity();
        }

        return $quantity;
    }

    /**
     * Retrieves the currency for a product identified by its ID.
     *
     * @param int $productId The ID of the product.
     */
    public static function getCurrency( int $productId ){
        $productSalesElement = ProductSaleElementsQuery::create()->filterByArray(['productId' => $productId, 'isDefault' => 1])->findOne();
        
        $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(true)->getCode();

        if ( !empty($productSalesElement) ) {
            $productSalesElementId = $productSalesElement->getId();
            $productCurrency = ProductPriceQuery::create()->findOneByProductSaleElementsId( $productSalesElementId );
            if ( !empty( $productCurrency ) ) {
                $currency = $productCurrency->getCurrency()->getCode();
                return $currency;
            }
        }

        return $defaultCurrency;
    }

    /**
     * Retrieves the EAN code for a product identified by its ID.
     *
     * @param int $productId The ID of the product.
     */
    public static function getEanCode( int $productId ){
        $product = ProductQuery::create()->findOneById($productId);

        $productEanCode = null;

        if ($product) {
            $productSaleElement = ProductSaleElementsQuery::create()->filterByArray(['productId' => $productId, 'isDefault' => 1])->findOne();
            if ($productSaleElement) {
                $eanCode = $productSaleElement->getEanCode();
            }

            if ( !empty($eanCode) ) $productEanCode = $eanCode;

            return $productEanCode;
        }
    }

    /**
     * Retrieves product name
     *
     * @param ProductI18n $productDefault
     * @param ProductI18n $orderStatusTranslated
     */
    public static function getName( ProductI18n $productTranslated, ProductI18n $productDefault ){
        $name = $productTranslated->getTitle() ?? $productDefault->getTitle();

        return $name;
    }

    /**
     * Retrieves product description
     *
     * @param ProductI18n $productDefault
     * @param ProductI18n $orderStatusTranslated
     */
    public static function getDescription( ProductI18n $productTranslated, ProductI18n $productDefault ){
        $description = $productTranslated->getDescription() ?? $productDefault->getDescription();

        return $description;
    }

    /**
     * Retrieves short description dscription
     *
     * @param ProductI18n $productDefault
     * @param ProductI18n $orderStatusTranslated
     */
    public static function getShortDescription( ProductI18n $productTranslated, ProductI18n $productDefault ){
        $description = $productTranslated->getChapo() ?? $productDefault->getChapo();

        return $description;
    }

    /**
     * Retrieves default images of product 
     *
     * @param int $productId
     * @param EventDispatcherInterface $dispatcher
     * 
     */
    public static function getDefaultImage( int $productId, $dispatcher ){
        $defaultImage = ProductImageQuery::create()
            ->filterByProductId($productId)
            ->filterByPosition(1)
            ->findOne();

        if ($defaultImage !== null) {
            return ProductImagesData::getImageUrl($defaultImage, $dispatcher);
        }

        return null;
    }
}
