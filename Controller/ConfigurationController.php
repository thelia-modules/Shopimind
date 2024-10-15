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

namespace Shopimind\Controller;

require_once __DIR__.'/../vendor-module/autoload.php';

use Shopimind\lib\Utils;
use Shopimind\Model\Shopimind;
use Shopimind\Model\ShopimindQuery;
use Shopimind\SdkShopimind\SpmShopConnection;
use Shopimind\SdkShopimind\SpmUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Model\Base\ConfigQuery;
use Thelia\Model\Base\CurrencyQuery;
use Thelia\Model\Base\LangQuery;
use Thelia\Tools\URL;

class ConfigurationController extends BaseAdminController
{
    /**
     * Save configuration settings.
     *
     * @param Request $request The request object
     */
    public function saveConfiguration(Request $request): RedirectResponse
    {
        $response = $this->redirectToConfigurationPage();

        $data = $request->request->all('shopimind_form_shopimind_form');
        $apiId = $data['api-id'];
        $apiPassword = $data['api-password'];
        $realTimeSynchronization = array_key_exists('real-time-synchronization', $data) ? 1 : 0;
        $nominativeReductions = array_key_exists('nominative-reductions', $data) ? 1 : 0;
        $cumulativeVouchers = array_key_exists('cumulative-vouchers', $data) ? 1 : 0;
        $outOfStockProductDisabling = array_key_exists('out-of-stock-product-disabling', $data) ? 1 : 0;
        $scriptTag = array_key_exists('script-tag', $data) ? 1 : 0;
        $activeLog = array_key_exists('log', $data) ? 1 : 0;

        $headers = ['client-id' => $apiId];

        $auth = SpmUtils::getClient('v1', $apiPassword, $headers);

        $connection = self::connectModule($auth);

        $session = new Session();
        $config = new Shopimind();
        $config->setRealTimeSynchronization($realTimeSynchronization);
        $config->setNominativeReductions($nominativeReductions);
        $config->setCumulativeVouchers($cumulativeVouchers);
        $config->setOutOfStockProductDisabling($outOfStockProductDisabling);
        $config->setScriptTag($scriptTag);
        $config->setLog($activeLog);
        if (isset($connection['statusCode']) && ($connection['statusCode'] == 200)) {
            $config->setApiId($apiId);
            $config->setApiPassword($apiPassword);
            $config->setIsConnected(true);
            $session->getFlashBag()->add('success', 'Module connected to Shopimind.');
        } else {
            $config->setApiId('');
            $config->setApiPassword('');
            $config->setIsConnected(false);
            $session->getFlashBag()->add('error', 'Module not connected to Shopimind.');
        }

        ShopimindQuery::clearTable();
        $config->save();

        return $response;
    }

    /**
     * Redirects to the configuration page.
     */
    protected function redirectToConfigurationPage(): RedirectResponse
    {
        Utils::log('products', 'passive synchronization', 'erreur desc');

        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/Shopimind'));
    }

    /**
     * Connect the module to Shopimind.
     */
    public static function connectModule($auth)
    {
        $currencyQuery = CurrencyQuery::create()->findOneByByDefault(1);
        if (empty($currencyQuery)) {
            return '';
        }

        $langQuery = LangQuery::create()->findOneByByDefault(1);
        if (empty($langQuery)) {
            return '';
        }

        $langsQuery = LangQuery::create()->filterByActive(1)->find();
        $langs = [];
        foreach ($langsQuery as $lang) {
            $langs[] = $lang->getCode();
        }

        $timezone = date_default_timezone_get();

        $urlClient = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].'/shopimind';

        $configQuery = ConfigQuery::create()->findByName('thelia_version');
        $ecommerce_version = $configQuery->getColumnValues('value');

        $config = [
            'default_currency' => $currencyQuery->getCode(),
            'default_lang' => $langQuery->getCode(),
            'langs' => $langs,
            'timezone' => $timezone,
            'url_client' => $urlClient,
            'ecommerce_version' => reset($ecommerce_version),
            'module_version' => '1.0.0',
        ];

        return SpmShopConnection::saveConfiguration($auth, $config);
    }
}
