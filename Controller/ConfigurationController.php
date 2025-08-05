<?php

namespace Shopimind\Controller;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Shopimind\Model\Shopimind;
use Shopimind\Model\ShopimindQuery;
use Thelia\Controller\Admin\BaseAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Tools\URL;
use Symfony\Component\HttpFoundation\Session\Session;
use Shopimind\SdkShopimind\SpmShopConnection;
use Thelia\Model\Base\ConfigQuery;
use Thelia\Model\Base\CurrencyQuery;
use Thelia\Model\Base\LangQuery;
use Shopimind\SdkShopimind\SpmUtils;
use Thelia\Core\Translation\Translator;

class ConfigurationController extends BaseAdminController
{
    /**
     * Save configuration settings.
     *
     * @param Request $request The request object.
     */
    public function saveConfiguration( Request $request )
    {
        $response = $this->redirectToConfigurationPage();

        $data = $request->request->all( 'shopimind_form_shopimind_form' );
        $apiId = $data['api-id'];
        $apiPassword = $data['api-password'];
        $realTimeSynchronization = array_key_exists('real-time-synchronization', $data) ? 1 : 0;
        $nominativeReductions = array_key_exists('nominative-reductions', $data) ? 1 : 0;
        $cumulativeVouchers = array_key_exists('cumulative-vouchers', $data) ? 1 : 0;
        $outOfStockProductDisabling = array_key_exists('out-of-stock-product-disabling', $data) ? 1 : 0;
        $scriptTag = array_key_exists('script-tag', $data) ? 1 : 0;
        $activeLog = array_key_exists('log', $data) ? 1 : 0;
        $confirmedStatuses = array_key_exists('confirmed-statuses', $data) ? $data['confirmed-statuses'] : null;
        $headers = [ 'client-id' => $apiId ];

        $session = new Session();
        if ( empty( $confirmedStatuses ) ) {
            $session->getFlashBag()->add('error', Translator::getInstance()->trans('You must select at least one status.', [], 'shopimind'));
            return $response;
        }

        $auth = SpmUtils::getClient( 'v1', $apiPassword, $headers );

        $connection = self::connectModule( $auth );
        
        $config = new Shopimind();
        $config->setRealTimeSynchronization($realTimeSynchronization);
        $config->setNominativeReductions($nominativeReductions);
        $config->setCumulativeVouchers($cumulativeVouchers);
        $config->setOutOfStockProductDisabling($outOfStockProductDisabling);
        $config->setScriptTag($scriptTag);
        $config->setLog($activeLog);
        $config->setConfirmedStatuses(json_encode($confirmedStatuses));

        if ( isset( $connection['statusCode'] ) && ( $connection['statusCode'] == 200 ) ) {
            $config->setApiId($apiId);
            $config->setApiPassword($apiPassword);
            $config->setIsConnected(true);
            $session->getFlashBag()->add('success', Translator::getInstance()->trans('Module connected to Shopimind.', [], 'shopimind'));
        }else {
            $config->setApiId('');
            $config->setApiPassword('');
            $config->setIsConnected(false);
            $session->getFlashBag()->add('error', Translator::getInstance()->trans('Module not connected to Shopimind.', [], 'shopimind'));
        }

        ShopimindQuery::clearTable();
        $config->save();

        return $response;
    }

    /**
     * Redirects to the configuration page.
     *
     * @return void
     */
    protected function redirectToConfigurationPage()
    {
        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/Shopimind'));
    }

    /**
     * Connect the module to Shopimind
     *
     * @param  $auth
     */
    public static function connectModule( $auth )
    {
        $currencyQuery = CurrencyQuery::create()->findOneByByDefault( 1 );
        if ( empty( $currencyQuery ) ) return '';

        $langQuery = LangQuery::create()->findOneByByDefault( 1 );
        if ( empty( $langQuery ) ) return '';

        $langsQuery = LangQuery::create()->filterByActive( 1 )->find();
        $langs = [];
        foreach ($langsQuery as $lang ) {
            array_push( $langs, $lang->getCode() );
        }

        $timezone = date_default_timezone_get();

        $urlClient = $_SERVER['REQUEST_SCHEME']. '://' .$_SERVER['HTTP_HOST'] . '/shopimind';

        $configQuery = ConfigQuery::create()->findByName( 'thelia_version' );
        $ecommerce_version = $configQuery->getColumnValues( 'value' );

        $config = [
            'default_currency' => $currencyQuery->getCode(),
            'default_lang' => $langQuery->getCode(),
            'langs' => $langs,
            'timezone' => $timezone,
            'url_client' => $urlClient,
            'ecommerce_version' => reset( $ecommerce_version ),
            'module_version' => '1.0.4'
        ];
        
        $response = SpmShopConnection::saveConfiguration( $auth, $config );
        
        return $response;
    }
}
