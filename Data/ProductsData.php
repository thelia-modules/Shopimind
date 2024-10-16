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
use Shopimind\Model\Base\ShopimindQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\ProductI18n;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

class ProductsData
{
    /**
     * Formats the product data to match the Shopimind format.
     * @param Product $product
     * @param ProductI18n $productTranslated
     * @param ProductI18n $productDefault
     * @param EventDispatcherInterface $dispatcher
     * @return array
     */
    public static function formatProduct(Product $product, ProductI18n $productTranslated, ProductI18n $productDefault, EventDispatcherInterface $dispatcher): array
    {
        $config = ShopimindQuery::create()->findOne();

        $active = (bool) $product->getVisible();

        $quantity = self::getQuantity($product->getId());
        if (!$quantity && $config->getOutOfStockProductDisabling()) {
            $active = false;
        }

        return [
            'product_id' => $product->getId(),
            'lang' => substr($productTranslated->getLocale(), 0, 2),
            'name' => self::getName($productTranslated, $productDefault) ?? '',
            'reference' => $product->getRef(),
            'ean13' => self::getEanCode($product->getId()),
            'description' => self::getDescription($productTranslated, $productDefault) ?? '',
            'description_short' => self::getShortDescription($productTranslated, $productDefault) ?? '',
            'link' => $product->getUrl($productTranslated->getLocale()),
            'category_ids' => self::formatCategoriesIds($product->getProductCategories()),
            'manufacturer_id' => (!empty($product->getBrandId())) ? (string) ($product->getBrandId()) : null,
            'currency' => self::getCurrency($product->getId()),
            'image_link' => self::getDefaultImage($product->getId(), $dispatcher) ?? 'https://place-hold.it//350x150',
            'price' => self::getPrice($product->getId()),
            'price_discount' => self::getPromoPrice($product->getId()),
            'quantity_remaining' => $quantity,
            'is_active' => $active,
            'created_at' => $product->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $product->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    /**
     * Formats an array of product categories.
     *
     * @param array $productCategories
     * @return array
     */
    public static function formatCategoriesIds(array $productCategories): array
    {
        $categoryIds = [];
        foreach ($productCategories as $productCategory) {
            $category = $productCategory->getCategory();
            if ($category) {
                $categoryIds[] = (int) $category->getId();
            }
        }

        return $categoryIds;
    }

    /**
     * Retrieves the price for a product identified by its ID.
     *
     * @param int $productId
     * @return float|int
     */
    public static function getPrice(int $productId): float|int
    {
        $productSalesElement = ProductSaleElementsQuery::create()->filterByArray(['productId' => $productId, 'isDefault' => 1])->findOne();
        if (!empty($productSalesElement)) {
            $productSalesElementId = $productSalesElement->getId();
            $productPrice = ProductPriceQuery::create()->findOneByProductSaleElementsId($productSalesElementId);
            if (!empty($productPrice)) {
                return Utils::formatNumber($productPrice->getPrice());
            }
        }

        return 0;
    }

    /**
     * Retrieves the promotional price for a product identified by its ID.
     *
     * @param int $productId
     * @return float|int
     */
    public static function getPromoPrice(int $productId): float|int
    {
        $productSalesElement = ProductSaleElementsQuery::create()->filterByArray(['productId' => $productId, 'isDefault' => 1])->findOne();
        if (!empty($productSalesElement)) {
            $productSalesElementId = $productSalesElement->getId();
            $productPromoPrice = ProductPriceQuery::create()->findOneByProductSaleElementsId($productSalesElementId);
            if (!empty($productPromoPrice)) {
                return Utils::formatNumber($productPromoPrice->getPromoPrice());
            }
        }

        return 0;
    }

    /**
     * Retrieves the quantity for a product identified by its ID.
     *
     * @param int $productId
     * @return int
     */
    public static function getQuantity(int $productId): int
    {
        $productSalesElements = ProductSaleElementsQuery::create()->filterByArray(['productId' => $productId])->find();

        $quantity = 0;

        foreach ($productSalesElements as $productSalesElement) {
            $quantity += $productSalesElement->getQuantity();
        }

        return $quantity;
    }

    /**
     * Retrieves the currency for a product identified by its ID.
     *
     * @param int $productId
     * @return string|null
     */
    public static function getCurrency(int $productId): string|null
    {
        $productSalesElement = ProductSaleElementsQuery::create()->filterByArray(['productId' => $productId, 'isDefault' => 1])->findOne();

        $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(true)->getCode();

        if (!empty($productSalesElement)) {
            $productSalesElementId = $productSalesElement->getId();
            $productCurrency = ProductPriceQuery::create()->findOneByProductSaleElementsId($productSalesElementId);
            if (!empty($productCurrency)) {
                return $productCurrency->getCurrency()->getCode();
            }
        }

        return $defaultCurrency;
    }

    /**
     * Retrieves the EAN code for a product identified by its ID.
     *
     * @param int $productId
     * @return string|null
     */
    public static function getEanCode(int $productId): string|null
    {
        $product = ProductQuery::create()->findOneById($productId);

        $productEanCode = null;
        if ($product) {
            $productSaleElement = ProductSaleElementsQuery::create()->filterByArray(['productId' => $productId, 'isDefault' => 1])->findOne();
            if ($productSaleElement) {
                $eanCode = $productSaleElement->getEanCode();
            }

            if (!empty($eanCode)) {
                $productEanCode = $eanCode;
            }

            return $productEanCode;
        }

        return null;
    }

    /**
     * Retrieves product name.
     *
     * @param ProductI18n $productTranslated
     * @param ProductI18n $productDefault
     * @return string
     */
    public static function getName(ProductI18n $productTranslated, ProductI18n $productDefault): string
    {
        return $productTranslated->getTitle() ?? $productDefault->getTitle();
    }

    /**
     * Retrieves product description.
     *
     * @param ProductI18n $productTranslated
     * @param ProductI18n $productDefault
     * @return string
     */
    public static function getDescription(ProductI18n $productTranslated, ProductI18n $productDefault): string
    {
        return $productTranslated->getDescription() ?? $productDefault->getDescription();
    }

    /**
     * Retrieves short description dscription.
     *
     * @param ProductI18n $productTranslated
     * @param ProductI18n $productDefault
     * @return string
     */
    public static function getShortDescription(ProductI18n $productTranslated, ProductI18n $productDefault): string
    {
        return $productTranslated->getChapo() ?? $productDefault->getChapo();
    }

    /**
     * Retrieves default images of product.
     *
     * @param int $productId
     * @param EventDispatcherInterface $dispatcher
     * @return string|null
     * @throw \Throwable
     */
    public static function getDefaultImage(int $productId, EventDispatcherInterface $dispatcher): string|null
    {
        $defaultImage = ProductImageQuery::create()
            ->filterByProductId($productId)
            ->filterByVisible(true)
            ->filterByPosition(1)
            ->findOne();

        if (!empty($defaultImage)) {
            try {
                $imagePath = ConfigQuery::read('images_library_path').\DIRECTORY_SEPARATOR.'product'.\DIRECTORY_SEPARATOR.$defaultImage->getFile();
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
