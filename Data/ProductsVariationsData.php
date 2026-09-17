<?php

namespace Shopimind\Data;

use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\ProductPriceQuery;
use Shopimind\lib\Utils;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\Base\ProductSaleElementsProductImageQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Country;


class ProductsVariationsData
{
    /**
     * Formats the product variation data to match the ShopiMind format.
     *
     * @param ProductSaleElements $productVariation
     * @param null $defaultTitle
     * @param null $localeParam
     * @param EventDispatcherInterface $dispatcher
     * @return array
     */
    public static function formatProductVariation( ProductSaleElements $productVariation, $defaultTitle = null, $localeParam = null, EventDispatcherInterface $dispatcher ): array
    {
        $locale = !empty( $localeParam ) ? $localeParam : LangQuery::create()->findOneByByDefault(true)->getLocale();

        $attribute = $productVariation->getAttributeCombinations();
        $title = self::getTitle( $attribute, $locale );
        $productTitle = $productVariation->getProduct()->getTranslation( $locale )->getTitle();
        $defaultProductTitle = $productVariation->getProduct()->getTranslation( LangQuery::create()->findOneByByDefault(true)->getLocale() )->getTitle();

        $productVariationTitle = '';

        if ( !empty( $title ) ) {
            $productVariationTitle = $title;
        } else if ( !empty( $productTitle ) ) {
            $productVariationTitle = $productTitle;
        } else {
            $productVariationTitle = $defaultTitle;
        }

        // Prix promo seulement si la promotion est active sur la déclinaison (drapeau promo) :
        // promo_price reste renseigné, à 0 par défaut, quand elle ne l'est pas.
        $promoPrice = (int) $productVariation->getPromo() === 1 ? self::getPromoPrice( $productVariation->getId() ) : null;
        // Prix TTC arrondis, et price_discount renseigné seulement quand il est inférieur à price,
        // null sinon.
        $price = Utils::formatNumber( (float) $productVariation->getProduct()->getTaxedPrice( Country::getDefaultCountry(), self::getPrice( $productVariation->getId() ) ) );
        $priceDiscount = null !== $promoPrice
            ? Utils::formatNumber( (float) $productVariation->getProduct()->getTaxedPrice( Country::getDefaultCountry(), $promoPrice ) )
            : null;
        if ( null !== $priceDiscount && $priceDiscount >= $price ) {
            $priceDiscount = null;
        }

        $data = [
            "variation_id" => intval( $productVariation->getId() ),
            "lang" => substr( $locale , 0, 2 ),
            "name" =>  $productVariationTitle ? $productVariationTitle : $defaultProductTitle,
            "reference" => $productVariation->getRef(),
            "ean13" => ( !empty( $productVariation->getEanCode() ) ) ? $productVariation->getEanCode() : null,
            "link" => $productVariation->getProduct()->getUrl( $locale ),
            "image_link" => ProductImagesData::urlOrPlaceholder( self::getDefaultImage( $productVariation->getId(), $productVariation->getProduct()->getId(), $dispatcher ) ),
            "price" => $price,
            "price_discount" => $priceDiscount,
            // Entier exigé par ShopiMind : Thelia stocke le stock en FLOAT (vente au poids).
            "quantity_remaining" => (int) ( $productVariation->getQuantity() ?? 0 ),
            "is_default" => ( bool ) $productVariation->getIsDefault(),
            "created_at" => $productVariation->getCreatedAt()->format('Y-m-d\TH:i:s.uP'),
            "updated_at" => $productVariation->getUpdatedAt()->format('Y-m-d\TH:i:s.uP'),
        ];

        $data['source_label'] = Utils::getSourceLabel();
        return $data;
    }

    /**
     * Retrieves the name for a product variation.
     *
     * @param $attribute
     */
    public static function getTitle( $attribute, $locale ){
        $title = "";
        foreach ($attribute as $item) {
            $attribute = AttributeAvI18nQuery::create()->filterByLocale( $locale )->findOneById( $item->getAttributeAvId() );
            if ($attribute) {
                $title .= $attribute->getTitle(). " ";
            }
        }
        return trim( $title );
    }

    /**
     * Retrieves the price for a product variation.
     *
     * @param int $productSalesElementId The ID of the product sales element.
     */
    public static function getPrice( int $productSalesElementId ){
        $productSalesElement = ProductPriceQuery::create()->findOneByProductSaleElementsId( $productSalesElementId );
        if ( $productSalesElement ) {
            $productPrice = $productSalesElement->getPrice();
            return Utils::formatNumber( $productPrice );
        }
        return 0;
    }

    /**
     * Retrieves the promotional price for a product variation identified by its ID.
     *
     * @param int $productSalesElementId The ID of the product variation.
     */
    public static function getPromoPrice( int $productSalesElementId ){
        $productSalesElement = ProductPriceQuery::create()->findOneByProductSaleElementsId( $productSalesElementId );
        if ( $productSalesElement ) {
            $productPromoPrice = $productSalesElement->getPromoPrice();
            return Utils::formatNumber( $productPromoPrice );
        }
    }  

    /**
     * Image de la déclinaison : la première des images qui lui sont associées, sinon la première image visible du produit.
     *
     * @param int $productSaleElementId
     * @param int $idProduct
     * @param EventDispatcherInterface $dispatcher
     * @return string|null
     */
    public static function getDefaultImage( int $productSaleElementId, int $idProduct, EventDispatcherInterface $dispatcher ){
        $imageIds = [];
        $associations = ProductSaleElementsProductImageQuery::create()
            ->filterByProductSaleElementsId( $productSaleElementId )
            ->find();
        foreach ( $associations as $association ) {
            $imageIds[] = (int) $association->getProductImageId();
        }

        $image = !empty( $imageIds )
            ? ProductImageQuery::create()->filterById( $imageIds )->orderByPosition()->findOne()
            : null;

        if ( empty( $image ) ) {
            $image = ProductImageQuery::create()
                ->filterByProductId( $idProduct )
                ->filterByVisible( 1 )
                ->orderByPosition()
                ->findOne();
        }

        return !empty( $image ) ? ProductImagesData::getImageUrl( $image, $dispatcher ) : null;
    }
}
