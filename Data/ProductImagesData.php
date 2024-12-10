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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Lang;
use Thelia\Model\ProductImage;

class ProductImagesData
{
    /**
     * Formats the product image data to match the Shopimind format.
     *
     * @param string $action
     */
    public function formatProductImage(ProductImage $productImage, Lang $lang, EventDispatcherInterface $dispatcher, $action = 'insert'): array
    {
        $data = [];
        if ($action == 'insert') {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $serverName = (\array_key_exists('SERVER_NAME',
                $_SERVER) ? $_SERVER['SERVER_NAME'] : \array_key_exists('HTTP_HOST',
                    $_SERVER)) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $scriptPath = \dirname($_SERVER['SCRIPT_NAME']);
            $rootUrl = $protocol.$serverName.rtrim($scriptPath, '/').'/';

            $data = [
                'image_id' => (string) $productImage->getId(),
                'variation_id' => null,
                'lang' => $lang->getCode(),
                'url' => $rootUrl,
                'is_default' => false,
            ];
        } elseif ($action == 'update') {
            $url = '';

            try {
                $imgSourcePath = $productImage->getUploadDir().DS.$productImage->getFile();

                $productImageEvent = new ImageEvent();
                $productImageEvent->setSourceFilepath($imgSourcePath)->setCacheSubdirectory('product_image');

                $dispatcher->dispatch($productImageEvent, TheliaEvents::IMAGE_PROCESS);
                $url = $productImageEvent->getFileUrl();
            } catch (\Throwable $th) {
                $url = null;
            }

            $productSaleElementsProductImages = $productImage->getProductSaleElementsProductImages()->getFirst();

            $data = [
                'image_id' => (string) $productImage->getId(),
                'variation_id' => $productSaleElementsProductImages ? (int) ($productSaleElementsProductImages->getProductSaleElementsId()) : null,
                'lang' => $lang->getCode(),
                'url' => $url,
                'is_default' => ($productImage->getPosition() == 1) ? true : false,
                'created_at' => $productImage->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
                'updated_at' => $productImage->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            ];
        }

        return $data;
    }
}
