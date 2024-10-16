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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RequestHandler
{
    /**
     * Controller to handle synchronization requests.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public static function requestController(Request $request): JsonResponse
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
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON.',
            ], 400);
        }

        $type = $body['data']['type'];
        $lastUpdate = (\array_key_exists('last_update', $body['data'])) ? $body['data']['last_update'] : '';
        $ids = (\array_key_exists('ids', $body['data'])) ? $body['data']['ids'] : '';
        $requestedBy = (\array_key_exists('requested-by', $body['data'])) ? $body['data']['requested-by'] : null;

        $response = '';
        switch ($type) {
            case 'customers':
                $response = SyncCustomers::processSyncCustomers($lastUpdate, $ids, $requestedBy);
                break;
            case 'customers_addresses':
                $response = SyncCustomersAddresses::processSyncCustomersAddresses($lastUpdate, $ids, $requestedBy);
                break;
            case 'newsletter_subscribers':
                $response = SyncNewsletterSubscribers::processSyncNewsletterSubscribers($lastUpdate, $ids, $requestedBy);
                break;
            case 'orders':
                $response = SyncOrders::processSyncOrders($lastUpdate, $ids, $requestedBy);
                break;
            case 'orders_statuses':
                $response = SyncOrderStatus::processSyncOrderStatus($lastUpdate, $ids, $requestedBy);
                break;
            case 'products':
                $response = SyncProducts::processSyncProducts($lastUpdate, $ids, $requestedBy);
                break;
            case 'products_variations':
                $response = SyncProductsVariations::processSyncProductsVariations($lastUpdate, $ids, $requestedBy);
                break;
            case 'products_images':
                $response = SyncProductsImages::processSyncProductsImages($lastUpdate, $ids, $requestedBy);
                break;
            case 'products_categories':
                $response = SyncProductsCategories::processSyncProductsCategories($lastUpdate, $ids, $requestedBy);
                break;
            case 'products_manufacturers':
                $response = SyncProductsManufacturers::processSyncProductsManufacturers($lastUpdate, $ids, $requestedBy);
                break;
            case 'vouchers':
                $response = SyncVouchers::processSyncVouchers($lastUpdate, $ids, $requestedBy);
                break;
        }

        return new JsonResponse($response);
    }

    /**
     * Validate syncrhonize parameters.
     *
     * @param array $params
     * @return JsonResponse
     */
    public static function validate(array $params): JsonResponse
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

        return new JsonResponse([
            'success' => true,
            'message' => $message,
        ]);
    }
}
