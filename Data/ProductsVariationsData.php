<?php

namespace Shopimind\Data;

use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\ProductPriceQuery;
use Shopimind\lib\Utils;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\ProductSaleElementsProductImageQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


class ProductsVariationsData
{
    /**
     * Formats the product variation data to match the Shopimind format.
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

        return [
            "variation_id" => intval( $productVariation->getId() ),
            "lang" => substr( $locale , 0, 2 ),
            "name" =>  $productVariationTitle ? $productVariationTitle : $defaultProductTitle,
            "reference" => $productVariation->getRef(),
            "ean13" => ( !empty( $productVariation->getEanCode() ) ) ? $productVariation->getEanCode() : null,
            "link" => $productVariation->getProduct()->getUrl( $locale ),
            "image_link" => self::getDefaultImage( $productVariation->getId(), $productVariation->getProduct()->getId(), $dispatcher ),
            "price" => self::getPrice( $productVariation->getId() ),
            "price_discount" => self::getPromoPrice( $productVariation->getId() ),
            "quantity_remaining" => $productVariation->getQuantity() ?? 0,
            "is_default" => ( bool ) $productVariation->getIsDefault(),
            "created_at" => $productVariation->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            "updated_at" => $productVariation->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
        ];
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
    * Retrieves default images of productVariation
    *
    * @param int $productSaleElementId
    * @param int $productId
    * @param EventDispatcherInterface $dispatcher
    * 
    */
   public static function getDefaultImage( int $productSaleElementId, int $idProduct, EventDispatcherInterface $dispatcher ){
        $productSaleElementsProductImage = ProductSaleElementsProductImageQuery::create()
            ->filterByProductSaleElementsId( $productSaleElementId )
            ->findOne();

        $idParam = !empty( $productSaleElementsProductImage ) ? $productSaleElementsProductImage->getProductSaleElementsId() : $idProduct; 

        $defaultImage = ProductImageQuery::create()
                ->filterByProductId( $idParam )
                ->filterByPosition(1)
                ->findOne();
    
            if ( !empty( $defaultImage ) ) {
                try {
                    $imagePath = ConfigQuery::read('images_library_path') . DIRECTORY_SEPARATOR . $defaultImage->getFile();
        
                    $imgSourcePath = $imagePath;
                
                    $productImageEvent = new ImageEvent();
                    $productImageEvent->setSourceFilepath($imgSourcePath)->setCacheSubdirectory('product_image');
            
                    $dispatcher->dispatch($productImageEvent, TheliaEvents::IMAGE_PROCESS);
                    $url = $productImageEvent->getFileUrl();
            
                    return $url;    
                } catch (\Throwable $th) {
                    //throw $th;
                }
            }

        return null;
   }
}
