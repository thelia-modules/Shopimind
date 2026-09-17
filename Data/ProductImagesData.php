<?php

namespace Shopimind\Data;

use Thelia\Model\ProductImage;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Base\Lang;
use Thelia\Model\ConfigQuery;
use Shopimind\lib\Utils;

class ProductImagesData
{
    /** Image envoyée quand aucune URL n'est trouvée, comme pour les produits et les déclinaisons. */
    const PLACEHOLDER_IMAGE_URL = 'https://placehold.co/300x300';

    /**
     * URL de l'image, ou l'image de remplacement si elle est absente : ShopiMind refuse une url vide.
     *
     * @param mixed $url
     * @return string
     */
    public static function urlOrPlaceholder( $url ): string
    {
        $url = is_string( $url ) ? trim( $url ) : '';

        return $url !== '' ? $url : self::PLACEHOLDER_IMAGE_URL;
    }

    /**
     * Formats the product image data to match the ShopiMind format.
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
                'url' => self::urlOrPlaceholder( $rootUrl ),
                'is_default' => false,
            ];
        } else if ( $action == 'update') {
            $url = self::getImageUrl( $productImage, $dispatcher);
            
            $productSaleElementsProductImages = $productImage->getProductSaleElementsProductImages()->getFirst();
    
            $data = [
                'image_id' => strval( $productImage->getId() ),
                'variation_id' => $productSaleElementsProductImages ? intval( $productSaleElementsProductImages->getProductSaleElementsId() ) : null,
                'lang' => $lang->getCode(),
                'url' => self::urlOrPlaceholder( $url ),
                'is_default' => ( $productImage->getPosition() == 1 ) ? true : false,
                'created_at' => $productImage->getCreatedAt()->format('Y-m-d\TH:i:s.uP'),
                'updated_at' => $productImage->getUpdatedAt()->format('Y-m-d\TH:i:s.uP')
            ];
        }
        
        $data['source_label'] = Utils::getSourceLabel();
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
