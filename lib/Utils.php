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

require_once THELIA_MODULE_DIR.'/Shopimind/vendor-module/autoload.php';

use Shopimind\Model\Base\ShopimindQuery;
use Shopimind\SdkShopimind\SpmUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;
use Thelia\Model\ModuleQuery;

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
     */
    public static function getAuth($headers = []): \GuzzleHttp\Client
    {
        $config = ShopimindQuery::create()->findOne();
        $apiPassword = !empty($config) ? $config->getApiPassword() : '';

        return SpmUtils::getClient('v1', $apiPassword, $headers);
    }

    /**
     * Handles the response from a Shopimind API request.
     *
     * @return void
     */
    public static function handleResponse(array $response): void
    {
        return;
    }

    /**
     * Logs synchronization events.
     */
    public static function log($object, $action, $response, $objectId = null): void
    {
        $config = ShopimindQuery::create()->findOne();

        if (!empty($config) && $config->getLog()) {
            $logDir = THELIA_MODULE_DIR.'/Shopimind/logs';
            $logFile = $logDir.'/module.log';

            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }

            if (!file_exists($logFile)) {
                file_put_contents($logFile, '');
            }
            $date = date('Y-m-d H:i:s');
            $id = !empty($objectId) ? 'id : ['.$objectId.']' : '';
            $message = '- ['.$date.'] Synchronization : '.$action.' '.$object.' '.$id.' '.$response.\PHP_EOL;

            error_log($message, 3, $logFile);
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
     */
    public static function getParameters()
    {
        $parametersFile = THELIA_MODULE_DIR.'/Shopimind/parameters.yml';
        if (file_exists($parametersFile)) {
            return Yaml::parseFile($parametersFile);
        }

        return [];
    }

    /**
     * Validates a request by checking module connection, API key, and JSON data.
     */
    public static function validateSpmRequest(Request $request)
    {
        if (!self::isConnected()) {
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

        $content = $request->getContent();
        parse_str($content, $body);

        if (empty($body)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid form data.',
            ], 400);
        }
    }

    /**
     * Launches the passive synchronization process.
     */
    public static function launchSynchronisation($object, $lastUpdate, $ids = null, $requestedBy = null, $idShopAskSyncs): void
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
                    'idShopAskSyncs' => $idShopAskSyncs,
                ]);

                // Construction de la commande curl pour exec
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
     * @return array
     */
    public static function loadSynchronizationStatus()
    {
        try {
            $synchronizationStatusFile = THELIA_MODULE_DIR.'/Shopimind/synchronization_status.yml';

            if (!file_exists($synchronizationStatusFile)) {
                $defaultSyncStatus = [
                    'synchronization_status' => [
                        'customers' => 0,
                        'customers_addresses' => 0,
                        'customers_groups' => 0,
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
     * @return void
     */
    public static function updateSynchronizationStatus($key, $value): void
    {
        $status = self::loadSynchronizationStatus();

        $status['synchronization_status'][$key] = $value;

        $synchronizationStatusFile = THELIA_MODULE_DIR.'/Shopimind/synchronization_status.yml';

        $yaml = Yaml::dump($status, 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($synchronizationStatusFile, $yaml);
    }

    /**
     * Check if the module customer family is active.
     *
     * @return bool
     */
    public static function isCustomerFamilyActive()
    {
        $module = ModuleQuery::create()->findOneByCode('CustomerFamily');

        if (!empty($module)) {
            if ($module->getActivate()) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
