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

namespace Shopimind\lib;

require_once \dirname(__DIR__).'/vendor-module/autoload.php';

use PHPUnit\Util\Json;
use Shopimind\Model\Base\ShopimindQuery;
use Shopimind\SdkShopimind\SpmUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

class Utils
{
    /**
     * Formats a floating-point number with two decimal places if the number has a decimal part.
     *
     * @param float $number
     * @return float
     */
    public static function formatNumber(float $number): float
    {
        if (floor($number) != $number) {
            $numberFormated = number_format(floor($number * 100) / 100, 2, '.', '');
            return (float) $numberFormated;
        }
        return $number;
    }

    /**
     * Check if the module is currently connected.
     *
     * @return bool
     */
    public static function isConnected(): bool
    {
        $config = ShopimindQuery::create()->findOne();
        if (empty($config) || (!empty($config) && (!$config->getIsConnected()))) {
            return false;
        }

        return true;
    }

    /**
     * Check if the module use real-time synchronization.
     *
     * @return bool
     */
    public static function useRealTimeSynchronization(): bool
    {
        $config = ShopimindQuery::create()->findOne();

        if (!empty($config)) {
            if ($config->getRealTimeSynchronization() && $config->getIsConnected()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves the authentication credentials.
     *
     * @param array $headers
     * @return \GuzzleHttp\Client
     */
    public static function getAuth(array $headers = []): \GuzzleHttp\Client
    {
        $config = ShopimindQuery::create()->findOne();
        $apiPassword = !empty($config) ? $config->getApiPassword() : '';

        return SpmUtils::getClient('v1', $apiPassword, $headers);
    }

    /**
     * Handles the response from a Shopimind API request.
     *
     * @param array $response
     * @return void
     */
    public static function handleResponse(array $response): void
    {

    }

    /**
     * Logs synchronization events.
     */
    public static function log($object, $action, $response, $objectId = null): void
    {
        $config = ShopimindQuery::create()->findOne();

        if (!empty($config) && $config->getLog()) {
            $date = date('Y-m-d H:i:s');
            $id = !empty($objectId) ? 'id : ['.$objectId.']' : '';
            $message = '- ['.$date.'] Synchronization : '.$action.' '.$object.' '.$id.' '.$response.\PHP_EOL;

            error_log($message, 3, THELIA_LOG_DIR.'/shopimind.log');
        }
    }

    /**
     * Logs an error message along with the stack trace.
     */
    public static function errorLog(\Throwable $th): void
    {
        $date = date('Y-m-d H:i:s');
        echo '- ['.$date.'] ';
        throw $th;
        echo \PHP_EOL;
    }

    /**
     * Retrieve module parameters.
     *
     * @return array
     */
    public static function getParameters(): array
    {
        $parametersFile = realpath(__DIR__.'/../').'/Config/parameters.yml';
        if (file_exists($parametersFile)) {
            return Yaml::parseFile($parametersFile);
        }

        return [];
    }

    /**
     * Validates a request by checking module connection, API key, and JSON data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public static function validateSpmRequest(Request $request): JsonResponse
    {
        if (!self::isConnected()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Module is not connected.',
            ]);
        }

        $config = ShopimindQuery::create()->findOne();
        $apiKey = $request->headers->get('api-spm-key');
        if (!($apiKey === $config->getApiPassword())) {
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

        return new JsonResponse([
            'success' => true,
            'message' => '',
        ]);
    }

    /**
     * Launches the passive synchronization process.
     */
    public static function launchSynchronisation($object, $lastUpdate, $ids = null, $requestedBy = null): void
    {
        try {
            if (\function_exists('exec') && \is_callable('exec')) {
                self::log($object, 'passive synchronization', 'exec callable', null);

                $baseUrl = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'];
                $url = $baseUrl.'/shopimind/sync-'.$object;

                $data = json_encode([
                    'last_update' => $lastUpdate,
                    'ids' => $ids,
                    'requestedBy' => $requestedBy,
                ]);

                $cmd = 'curl -o /dev/null -s -w "%{http_code}" '.escapeshellarg($url).
                    ' -H "Content-Type: application/json" -d '.escapeshellarg($data).' > /dev/null 2>&1 &';
                exec($cmd);
            } else {
                $formattedObject = str_replace('-', '_', $object);
                self::updateSynchronizationStatus($formattedObject, 0);
                self::log($formattedObject, 'passive synchronization', 'exec not callable', null);
            }
        } catch (\Throwable $th) {
            $formattedObject = str_replace('-', '_', $object);
            self::updateSynchronizationStatus($formattedObject, 0);
            self::log($formattedObject, 'passive synchronization', 'failed', null);
        }
    }

    /**
     * Loads the synchronization status from a YAML file.
     *
     * @return array|null
     */
    public static function loadSynchronizationStatus(): array|null
    {
        try {
            $synchronizationStatusFile = realpath(__DIR__.'/../').'/Config/synchronization_status.yml';

            if (!file_exists($synchronizationStatusFile)) {
                $defaultSyncStatus = [
                    'synchronization_status' => [
                        'customers' => 0,
                        'customers_addresses' => 0,
                        'newsletter_subscribers' => 0,
                        'orders' => 0,
                        'orders_statuses' => 0,
                        'products' => 0,
                        'products_images' => 0,
                        'products_categories' => 0,
                        'products_manufacturers' => 0,
                        'products_variations' => 0,
                        'vouchers' => 0,
                    ],
                ];

                $yaml = Yaml::dump($defaultSyncStatus, 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
                file_put_contents($synchronizationStatusFile, $yaml);
            }

            return Yaml::parseFile($synchronizationStatusFile);
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * pdates the synchronization status with a new value for the specified key.
     *
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public static function updateSynchronizationStatus(string $key, string $value): void
    {
        $status = self::loadSynchronizationStatus();

        $status['synchronization_status'][$key] = $value;

        $synchronizationStatusFile = realpath(__DIR__.'/../').'/Config/synchronization_status.yml';

        $yaml = Yaml::dump($status, 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($synchronizationStatusFile, $yaml);
    }
}
