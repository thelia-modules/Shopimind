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

use Shopimind\lib\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\Base\ProductSaleElementsProductImageQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;

class ProductsVariationsData
{
    /**
     * Formats the product variation data to match the Shopimind format.
     *
     * @param ProductSaleElements $productVariation
     * @param EventDispatcherInterface $dispatcher
     * @param string $defaultTitle
     * @param string $localeParam
     * @return void
     */
    public static function formatProductVariation(ProductSaleElements $productVariation, EventDispatcherInterface $dispatcher, string $defaultTitle = null, string $localeParam = null): array
    {
        $locale = $localeParam ?? LangQuery::create()->findOneByByDefault(true)->getLocale();

        $attribute = $productVariation->getAttributeCombinations();
        $title = self::getTitle($attribute, $locale);
        $productTitle = $productVariation->getProduct()->getTranslation($locale)->getTitle();
        $defaultProductTitle = $productVariation->getProduct()->getTranslation(LangQuery::create()->findOneByByDefault(true)->getLocale())->getTitle();

        if (!empty($title)) {
            $productVariationTitle = $title;
        } elseif (!empty($productTitle)) {
            $productVariationTitle = $productTitle;
        } else {
            $productVariationTitle = $defaultTitle;
        }

        return [
            'variation_id' => $productVariation->getId(),
            'lang' => substr($locale, 0, 2),
            'name' => $productVariationTitle ?? $defaultProductTitle,
            'reference' => $productVariation->getRef(),
            'ean13' => (!empty($productVariation->getEanCode())) ? $productVariation->getEanCode() : null,
            'link' => $productVariation->getProduct()->getUrl($locale),
            'image_link' => self::getDefaultImage($productVariation->getId(), $productVariation->getProduct()->getId(), $dispatcher),
            'price' => self::getPrice($productVariation->getId()),
            'price_discount' => self::getPromoPrice($productVariation->getId()),
            'quantity_remaining' => $productVariation->getQuantity() ?? 0,
            'is_default' => (bool) $productVariation->getIsDefault(),
        ];
    }

    /**
     * Retrieves the name for a product variation.
     */
    public static function getTitle($attribute, $locale): string
    {
        $title = '';
        foreach ($attribute as $item) {
            $attribute = AttributeAvI18nQuery::create()->filterByLocale($locale)->findOneById($item->getAttributeAvId());
            if ($attribute) {
                $title .= $attribute->getTitle().' ';
            }
        }

        return trim($title);
    }

    /**
     * Retrieves the price for a product variation.
     *
     * @param int $productSalesElementId
     * @return float
     */
    public static function getPrice(int $productSalesElementId): float
    {
        $productSalesElement = ProductPriceQuery::create()->findOneByProductSaleElementsId($productSalesElementId);
        if ($productSalesElement) {
            $productPrice = $productSalesElement->getPrice();

            return Utils::formatNumber($productPrice);
        }

        return 0.0;
    }

    /**
     * Retrieves the promotional price for a product variation identified by its ID.
     *
     * @param int $productSalesElementId
     * @return float
     */
    public static function getPromoPrice(int $productSalesElementId): float
    {
        $productSalesElement = ProductPriceQuery::create()->findOneByProductSaleElementsId($productSalesElementId);
        if ($productSalesElement) {
            $productPromoPrice = $productSalesElement->getPromoPrice();

            return Utils::formatNumber($productPromoPrice);
        }

        return 0.0;
    }

    /**
     * Retrieves default images of productVariation.
     *
     * @param int $productSalesElementId
     * @param int $idProduct
     * @param EventDispatcherInterface $dispatcher
     * @return float
     * @throw \Throwable
     */
    public static function getDefaultImage(int $productSaleElementId, int $idProduct, EventDispatcherInterface $dispatcher): string|null
    {
        $productSaleElementsProductImage = ProductSaleElementsProductImageQuery::create()
            ->filterByProductSaleElementsId($productSaleElementId)
            ->findOne();

        $idParam = !empty($productSaleElementsProductImage) ? $productSaleElementsProductImage->getProductSaleElementsId() : $idProduct;

        $defaultImage = ProductImageQuery::create()
                ->filterByProductId($idParam)
                ->filterByVisible(true)
                ->filterByPosition(1)
                ->findOne();

        if (!empty($defaultImage)) {
            try {
                $imagePath = ConfigQuery::read('images_library_path').\DIRECTORY_SEPARATOR.$defaultImage->getFile();

                $imgSourcePath = $imagePath;

                $productImageEvent = new ImageEvent();
                $productImageEvent->setSourceFilepath($imgSourcePath)->setCacheSubdirectory('product_image');

                $dispatcher->dispatch($productImageEvent, TheliaEvents::IMAGE_PROCESS);

                return $productImageEvent->getFileUrl();
            } catch (\Throwable $th) {
                // throw $th;
            }
        }

        return null;
    }
}
