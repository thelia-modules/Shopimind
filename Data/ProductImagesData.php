<?php

namespace Shopimind\Data;

use Thelia\Model\ProductImage;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Base\Lang;
use Thelia\Model\ConfigQuery;

class ProductImagesData
{
    /**
     * Formats the product image data to match the Shopimind format.
     *
     * @param ProductImage $productImage
     * @param $imageTranslated
     * @param $imageDefault
     * @param EventDispatcherInterface $dispatcher
     * @param string $action
     * @return array
     */
    public static function formatProductImage( ProductImage $productImage, Lang $lang, EventDispatcherInterface $dispatcher, $action = 'insert' ): array
    {
        $data = [];
        if ( $action == 'insert' ) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $serverName = (array_key_exists('SERVER_NAME',
                $_SERVER) ? $_SERVER['SERVER_NAME'] : array_key_exists('HTTP_HOST',
                $_SERVER)) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
            $rootUrl = $protocol . $serverName . rtrim($scriptPath, '/') . '/';
            
            $data = [
                'image_id' => strval( $productImage->getId() ),
                'variation_id' => null,
                'lang' => $lang->getCode(),
                'url' => $rootUrl,
                'is_default' => false,
            ];
        } else if ( $action == 'update') {
            $url = self::getImageUrl( $productImage, $dispatcher);
            
            $productSaleElementsProductImages = $productImage->getProductSaleElementsProductImages()->getFirst();
    
            $data = [
                'image_id' => strval( $productImage->getId() ),
                'variation_id' => $productSaleElementsProductImages ? intval( $productSaleElementsProductImages->getProductSaleElementsId() ) : null,
                'lang' => $lang->getCode(),
                'url' => $url,
                'is_default' => ( $productImage->getPosition() == 1 ) ? true : false,
                'created_at' => $productImage->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
                'updated_at' => $productImage->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z')
            ];
        }
        
        return $data;
    }

    /**
     * Retrieves image url 
     *
     * @param ProductImage $productImage
     * @param EventDispatcherInterface $dispatcher
     * 
     */
    public static function getImageUrl( ProductImage $productImage, $dispatcher ){

        if ( !empty( $productImage ) ) {
            try {
                $imgSourcePath = $productImage->getUploadDir().DS.$productImage->getFile();
    
                $productImageEvent = new ImageEvent();
                $productImageEvent->setSourceFilepath($imgSourcePath)->setCacheSubdirectory('product_image');
        
                $dispatcher->dispatch($productImageEvent, TheliaEvents::IMAGE_PROCESS);
                $url = $productImageEvent->getFileUrl();    

                return $url;
            } catch (\Throwable $th) {
                //throw $th;
            }

            try {
                $cacheDirFromWebRoot = ConfigQuery::read('image_cache_dir_from_web_root', 'cache/images/');
                $cacheSubdirectory = '/product_image/';
                $cacheDirectory = THELIA_ROOT . 'web/' . $cacheDirFromWebRoot . $cacheSubdirectory;
                $pattern = $cacheDirectory . '*-' . strtolower($productImage->getFile());
                $cachedFiles = glob($pattern);
                if (!empty($cachedFiles)) {
                    $largestFile = null;
                    $largestSize = 0;

                    foreach ($cachedFiles as $file) {
                        if (file_exists($file)) {
                            $size = filesize($file);
                            if ($size > $largestSize) {
                                $largestSize = $size;
                                $largestFile = $file;
                            }
                        }
                    }

                    $fileName = basename($largestFile);
                    $sourceFilePath = sprintf(
                        "%s%s/%s/%s",
                        THELIA_ROOT,
                        ConfigQuery::read('image_cache_dir_from_web_root'),
                        "product_image",
                        $fileName
                    );
                
                    $productImageEvent = new ImageEvent();
                    $productImageEvent->setSourceFilepath($sourceFilePath)->setCacheSubdirectory('product_image');
                    
                    $dispatcher->dispatch($productImageEvent, TheliaEvents::IMAGE_PROCESS);
                    $url = $productImageEvent->getFileUrl();

                    return $url;
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
        }

        return null;
    }
}
