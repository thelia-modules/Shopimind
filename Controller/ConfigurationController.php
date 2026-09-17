<?php

namespace Shopimind\Controller;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Shopimind\Model\Shopimind;
use Shopimind\Model\ShopimindQuery;
use Shopimind\Form\ShopimindForm;
use Shopimind\lib\ShopConnection;
use Shopimind\lib\Utils;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Tools\URL;
use Symfony\Component\HttpFoundation\Session\Session;
use Shopimind\SdkShopimind\SpmShopConnection;
use Thelia\Model\ConfigQuery;
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
        // Droit de modification sur le module, comme les contrôleurs d'administration des modules de Thelia
        // (HookAnalytics) : l'accès à /admin ne suffit pas, un profil sans droit sur le module est refusé.
        if ( null !== $denied = $this->checkAuth( [ AdminResources::MODULE ], [ 'Shopimind' ], AccessManager::UPDATE ) ) {
            return $denied;
        }

        $response = $this->redirectToConfigurationPage();

        // Jeton CSRF (_token, rendu par form_hidden_fields) et contraintes du formulaire vérifiés par Thelia avant
        // toute écriture : une page tierce visitée par un administrateur connecté ne peut pas enregistrer
        // d'autres identifiants et relier la boutique à un autre compte ShopiMind.
        try {
            $this->validateForm( $this->createForm( ShopimindForm::getName() ), 'POST' );
        } catch ( FormValidationException $e ) {
            ( new Session() )->getFlashBag()->add( 'error', Translator::getInstance()->trans( 'The configuration was not saved: %error', [ '%error' => $e->getMessage() ], 'shopimind' ) );

            return $response;
        }

        // Toute erreur (base de données, serveur ShopiMind, extension PHP absente) revient sur la page avec son message.
        try {
            $this->applyConfiguration( $request );
        } catch ( \Throwable $e ) {
            Utils::logException( 'Admin', 'Configuration', $e );
            ( new Session() )->getFlashBag()->add( 'error', Translator::getInstance()->trans( 'The configuration was not saved: %error', [ '%error' => $e->getMessage() ], 'shopimind' ) );
        }

        return $response;
    }

    protected function applyConfiguration( Request $request ): void
    {
        $data = $request->request->all( 'shopimind_form_shopimind_form' );
        $apiId = $data['api-id'];
        $apiPassword = $data['api-password'];
        $realTimeSynchronization = array_key_exists('real-time-synchronization', $data) ? 1 : 0;
        $nominativeReductions = array_key_exists('nominative-reductions', $data) ? 1 : 0;
        $cumulativeVouchers = array_key_exists('cumulative-vouchers', $data) ? 1 : 0;
        $outOfStockProductDisabling = array_key_exists('out-of-stock-product-disabling', $data) ? 1 : 0;
        $scriptTag = array_key_exists('script-tag', $data) ? 1 : 0;
        $logLevel = isset( $data['log-level'] ) && is_numeric( $data['log-level'] )
            ? max( Utils::LOG_LEVEL_OFF, min( Utils::LOG_LEVEL_DEBUG, (int) $data['log-level'] ) )
            : Utils::LOG_LEVEL_OFF;
        $coreApiUrlOverride = (string) Utils::sanitizeOverrideUrl( $data['core-api-url-override'] ?? '' );
        $scriptUrlOverride = (string) Utils::sanitizeOverrideUrl( $data['script-url-override'] ?? '' );
        $confirmedStatuses = array_key_exists('confirmed-statuses', $data) ? $data['confirmed-statuses'] : null;

        // Persist the gender mapping (customer_title.id => ShopiMind gender
        // 1=Mr / 2=Mme) in Thelia's generic config table — no schema
        // migration. Any title not listed maps to 0 (unknown).
        $mrTitles = (array_key_exists('gender-mr-titles', $data) && is_array($data['gender-mr-titles']))
            ? array_map('intval', $data['gender-mr-titles']) : [];
        $mmeTitles = (array_key_exists('gender-mme-titles', $data) && is_array($data['gender-mme-titles']))
            ? array_map('intval', $data['gender-mme-titles']) : [];
        // A title in both lists would be ambiguous — favor Mme by stripping
        // it from Mr (matches the read order used in CustomersData).
        $mrTitles = array_values(array_diff($mrTitles, $mmeTitles));
        ConfigQuery::write('shopimind_gender_mapping', json_encode([
            '1' => $mrTitles,
            '2' => $mmeTitles,
        ]));

        // Options du tag.
        ConfigQuery::write('shopimind_hide_on_checkout', array_key_exists('hide-on-checkout', $data) ? 1 : 0);
        ConfigQuery::write('shopimind_cache_workaround', array_key_exists('cache-workaround', $data) ? 1 : 0);

        // Section Développeur / Debug.
        ConfigQuery::write('shopimind_log_level', (string) $logLevel);
        ConfigQuery::write('shopimind_core_api_url_override', $coreApiUrlOverride);
        ConfigQuery::write('shopimind_script_url_override', $scriptUrlOverride);

        $headers = ShopConnection::headers( (string) $apiId );

        $session = new Session();
        if ( empty( $confirmedStatuses ) ) {
            // ConfigQuery::read renvoie la valeur par défaut pour « 0 » et Utils::getLogLevel lit alors la colonne log : elle
            // suit le niveau même quand le reste de la configuration n'est pas enregistré.
            $currentConfig = ShopimindQuery::create()->findOne();
            if ( !empty( $currentConfig ) ) {
                $currentConfig->setLog( $logLevel > 0 ? 1 : 0 );
                $currentConfig->save();
            }
            $session->getFlashBag()->add('error', Translator::getInstance()->trans('You must select at least one status.', [], 'shopimind'));
            return;
        }

        // Connexion sur l'URL de l'API choisie dans le formulaire : la surcharge du mode Debug s'applique aussi ici.
        $coreApiUrl = Utils::resolveCoreApiUrl( $logLevel, $coreApiUrlOverride );
        Utils::logInfo( 'Admin', 'Configuration', 'Connection to ' . $coreApiUrl );
        // Serveur injoignable ou extension cURL absente : le module est enregistré déconnecté, avec un message.
        $payload = null;
        $connection = null;
        try {
            $auth = Utils::getApiClient( $apiPassword, $headers, $coreApiUrl );
            // Schéma et hôte déduits par Symfony (HTTPS, port).
            $payload = ShopConnection::buildPayload( $request->getSchemeAndHttpHost() . ShopConnection::ROUTE_PREFIX );
            if ( null !== $payload ) {
                $connection = SpmShopConnection::saveConfiguration( $auth, $payload );
            }
        } catch ( \Throwable $e ) {
            Utils::logException( 'Admin', 'Configuration', $e );
            $connection = null;
        }
        $isConnected = ShopConnection::isSuccess( $connection );
        
        $config = new Shopimind();
        $config->setRealTimeSynchronization($realTimeSynchronization);
        $config->setNominativeReductions($nominativeReductions);
        $config->setCumulativeVouchers($cumulativeVouchers);
        $config->setOutOfStockProductDisabling($outOfStockProductDisabling);
        $config->setScriptTag($scriptTag);
        $config->setLog($logLevel > 0 ? 1 : 0);
        $config->setConfirmedStatuses(json_encode($confirmedStatuses));

        if ( $isConnected ) {
            $config->setApiId($apiId);
            $config->setApiPassword($apiPassword);
            $config->setIsConnected(true);
            $session->getFlashBag()->add('success', Translator::getInstance()->trans('Module connected to ShopiMind.', [], 'shopimind'));
        }else {
            $config->setApiId('');
            $config->setApiPassword('');
            $config->setIsConnected(false);
            $session->getFlashBag()->add('error', Translator::getInstance()->trans('The module could not be connected to ShopiMind. Check the API ID and password, then save again.', [], 'shopimind'));
        }

        // La table est vidée puis réécrite : on reporte l'URL de script posée par ShopiMind (setScriptUrl),
        // sinon chaque enregistrement du formulaire ramenait le tag sur le domaine par défaut.
        $previousConfig = ShopimindQuery::create()->findOne();
        if ( !empty( $previousConfig ) && method_exists( $previousConfig, 'getScriptUrl' ) && method_exists( $config, 'setScriptUrl' ) ) {
            $config->setScriptUrl( $previousConfig->getScriptUrl() );
        }

        ShopimindQuery::clearTable();
        $config->save();

        // URL, fuseau horaire et version acceptés par ShopiMind, renvoyés par la reconnexion après une mise à jour.
        if ( $isConnected ) {
            try {
                ShopConnection::rememberConnection( $payload );
            } catch ( \Throwable $e ) {
                Utils::logException( 'Admin', 'Configuration', $e );
            }
        }
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
}
