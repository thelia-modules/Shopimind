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

namespace Shopimind\PassiveSynchronization;

use Shopimind\lib\Utils;
use Shopimind\Model\Base\ShopimindQuery;
use Shopimind\Model\ShopimindSyncStatus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RequestHandler
{
    public function __construct(
        private SyncOrders $syncOrders,
        private SyncOrderStatus $syncOrderStatus,
        private SyncProducts $syncProducts,
        private SyncProductsVariations $syncProductsVariations,
        private SyncProductsImages $syncProductsImages,
        private SyncProductsCategories $syncProductsCategories,
        private SyncProductsManufacturers $syncProductsManufacturers,
        private SyncVouchers $syncVouchers,
        private SyncCustomers $syncCustomers,
        private SyncCustomersAddresses $syncCustomersAddresses,
        private SyncCustomersGroups $syncCustomersGroups,
        private SyncNewsletterSubscribers $syncNewsletterSubscribers,
    ) {
    }

    /**
     * Controller to handle synchronization requests.
     *
     * @return JsonResponse
     */
    public function requestController(Request $request)
    {
        if (!Utils::isConnected()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Module is not connected.',
            ]);
        }

        $config = ShopimindQuery::create()->findOne();
        $apiKey = $request->headers->get('api-spm-key');
        if (!($apiKey === sha1($config->getApiPassword()))) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $body = json_decode($request->getContent(), true);
        if (json_last_error() !== \JSON_ERROR_NONE) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON.',
            ], 400);
        }

        $errors = self::validate($body);
        if (!empty($errors)) {
            return $errors;
        }

        $type = $body['data']['type'];
        $lastUpdate = (\array_key_exists('last_update', $body['data'])) ? $body['data']['last_update'] : '';
        $ids = (\array_key_exists('ids', $body['data'])) ? $body['data']['ids'] : '';
        $requestedBy = (\array_key_exists('requested-by', $body['data'])) ? $body['data']['requested-by'] : null;

        $idShopAskSyncs = $request->headers->get('id-shop-ask-syncs');

        $response = '';
        switch ($type) {
            case 'customers':
                $response = $this->syncCustomers->processSyncCustomers($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                break;
            case 'customers_addresses':
                $response = $this->syncCustomersAddresses->processSyncCustomersAddresses($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                break;
            case 'customers_groups':
                if (Utils::isCustomerFamilyActive()) {
                    $response = $this->syncCustomersGroups->processSyncCustomersGroups($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                } else {
                    if (!empty($idShopAskSyncs)) {
                        $objectStatus = [
                            'status' => 'completed',
                        ];
                        ShopimindSyncStatus::updateObjectStatuses($idShopAskSyncs, 'customers_groups', $objectStatus);
                    }

                    $response = [
                        'success' => true,
                        'count' => 0,
                    ];
                }
                break;
            case 'newsletter_subscribers':
                $response = $this->syncNewsletterSubscribers->processSyncNewsletterSubscribers($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                break;
            case 'orders':
                $response = $this->syncOrders->processSyncOrders($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                break;
            case 'orders_statuses':
                $response = $this->syncOrderStatus->processSyncOrderStatus($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                break;
            case 'products':
                $response = $this->syncProducts->processSyncProducts($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                break;
            case 'products_variations':
                $response = $this->syncProductsVariations->processSyncProductsVariations($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                break;
            case 'products_images':
                $response = $this->syncProductsImages->processSyncProductsImages($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                break;
            case 'products_categories':
                $response = $this->syncProductsCategories->processSyncProductsCategories($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                break;
            case 'products_manufacturers':
                $response = $this->syncProductsManufacturers->processSyncProductsManufacturers($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                break;
            case 'vouchers':
                $response = $this->syncVouchers->processSyncVouchers($lastUpdate, $ids, $requestedBy, $idShopAskSyncs);
                break;
        }

        return new JsonResponse($response);
    }

    /**
     * Validate syncrhonize parameters.
     *
     * @param array $params an array containing the parameters
     */
    public static function validate($params)
    {
        $message = '';

        if (!isset($params['data']) || !isset($params['hmac'])) {
            if (!isset($params['data'])) {
                $message = 'data is required. ';
            }
            if (!isset($params['hmac'])) {
                $message = 'hmac is required. ';
            }
        } else {
            $requiredParams = [
                'type',
            ];
            foreach ($requiredParams as $param) {
                if (!\array_key_exists('type', $params['data']) || empty($params['data'][$param])) {
                    $message = $param.' is required. ';
                }
            }
        }

        if (!empty($message)) {
            return new JsonResponse([
                'success' => false,
                'message' => $message,
            ]);
        }
    }
}
