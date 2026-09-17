<?php

namespace Shopimind\PassiveSynchronization;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Thelia\Model\ProductImageQuery;
use Shopimind\lib\Utils;
use Shopimind\lib\SyncHttpClient;
use Shopimind\SdkShopimind\SpmProductsImages;
use Shopimind\Data\ProductImagesData;
use Thelia\Model\Base\LangQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SyncProductsImages extends AbstractController
{
    /**
     * Process synchronization for products images
     *
     * @param array $param
     * @param EventDispatcherInterface $dispatcher
     * @return array
     */
    public static function processSyncProductsImages( array $param, EventDispatcherInterface $dispatcher = null ): array
    {
        try {
            $langs = LangQuery::create()->filterByActive( 1 )->find();
            $langsCount = $langs->count();

            $offset = isset( $param['start'] ) ? (int) $param['start'] : 0;
            $limit = isset( $param['limit'] ) ? (int) $param['limit'] : 20;
            $apiMaxObjects = 20;
            $idsRequested = ( array_key_exists( 'ids', $param ) ) ? $param['ids'] : '';
            $lastUpdate = ( array_key_exists( 'lastUpdate', $param ) ) ? $param['lastUpdate'] : '';

            $extraHeaders = [];
            if (!empty($param['id_shop_ask_syncs'])) {
                $extraHeaders['X-Shopimind-Sync-Id'] = $param['id_shop_ask_syncs'];
            }

            $hasMore = true;

            $query = ProductImageQuery::create();

            if ( !empty( $lastUpdate ) && $lastUpdate !== 'null' ) {
                $lastUpdate = trim( $lastUpdate, '"\'' );
                $query->filterByUpdatedAt( $lastUpdate, '>=' );
            }

            if ( !empty( $idsRequested ) ) {
                $idsRequested = is_array( $idsRequested ) ? $idsRequested : explode( ',', (string) $idsRequested );
                $query->filterById( $idsRequested );
            }

            if ( isset( $param['just_count'] ) && $param['just_count'] ) {
                $count = $query->count();

                return [
                    'success' => true,
                    'total_count' => (int) $count * $langsCount
                ];
            }

            $query->orderByUpdatedAt();
            $query->orderBy( 'product_id' );
            // Tri total (updated_at, product_id, id) : sans id, les images d'un même produit au même updated_at peuvent changer d'ordre d'une page à l'autre.
            $query->orderById();
            $query->offset( $offset );
            $query->limit( $limit );

            $productImages = $query->find();

            if ( $productImages->count() < $limit ) {
                $hasMore = false;
            }

            if ( $productImages->count() > 0 ) {
                $sentCount = 0;
                $failedCount = 0;
                $rejectedCount = 0;
                $errors = [];

                $data = [];
                foreach ( $productImages as $productImage ) {
                    foreach ( $langs as $lang ) {
                        $formattedImage = ProductImagesData::formatProductImage( $productImage, $lang, $dispatcher, 'update' );
                        $formattedImage['product_id'] = strval( $productImage->getProductId() );
                        $data[] = $formattedImage;
                    }
                }

                $lastObjectUpdate = null;
                foreach ($data as $item) {
                    $dateUpd = $item['updated_at'] ?? $item['date_upd'] ?? null;
                    if ($dateUpd !== null && ($lastObjectUpdate === null || $dateUpd > $lastObjectUpdate)) {
                        $lastObjectUpdate = $dateUpd;
                    }
                }

                $chunks = array_chunk($data, $apiMaxObjects);
                foreach ($chunks as $chunk) {
                    $response = SyncHttpClient::executeWithRetry(
                        function() use ($chunk, $extraHeaders) {
                            return SpmProductsImages::bulkSaveAll(Utils::getAuth($extraHeaders), $chunk);
                        },
                        'SyncProductsImages (bulk ' . count($chunk) . ' images)'
                    );

                    Utils::log( 'productImage', 'passive synchronization', json_encode( $response ) );

                    $success = isset( $response['statusCode'] ) && $response['statusCode'] === 200;

                    if ($success) {
                        $counters = SyncHttpClient::extractCounters($response, count($chunk));
                        $sentCount += $counters['sent_count'];
                        $rejectedCount += $counters['rejected_count'];
                    } else {
                        $failedCount += count($chunk);
                        $errors[] = [
                            'message' => $response['message'] ?? 'Unknown error',
                            'status_code' => $response['statusCode'] ?? 0
                        ];
                    }
                }

                return [
                    'success' => $failedCount === 0,
                    'sent_count' => $sentCount,
                    'failed_count' => $failedCount,
                    'rejected_count' => $rejectedCount,
                    'has_more' => $hasMore,
                    'last_object_update' => $lastObjectUpdate,
                    'errors' => $errors
                ];
            } else {
                return [
                    'success' => true,
                    'sent_count' => 0,
                    'failed_count' => 0,
                    'rejected_count' => 0,
                    'has_more' => false,
                    'last_object_update' => null
                ];
            }
        } catch ( \Throwable $th ) {
            Utils::logException( 'Passive', 'productImage', $th );
            return [
                'success' => false,
                'sent_count' => 0,
                'failed_count' => 0,
                'rejected_count' => 0,
                'has_more' => false,
                'last_object_update' => null,
                'errors' => [ [ 'message' => Utils::toUtf8( $th->getMessage() ), 'status_code' => 0 ] ]
            ];
        }
    }
}
