<?php
namespace Shopimind\lib;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Shopimind\Model\Base\ShopimindQuery;
use Shopimind\SdkShopimind\SpmUtils;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ConfigQuery;

class Utils
{
    const LOG_LEVEL_OFF   = 0;
    const LOG_LEVEL_ERROR = 1;
    const LOG_LEVEL_INFO  = 2;
    const LOG_LEVEL_DEBUG = 3;

    /**
     * Formats a floating-point number with two decimal places if the number has a decimal part.
     *
     * @param float $number The number to format.
     */
    public static function formatNumber( float $number ){
        // Round to 2 decimals using PHP's half-up so prices match what
        // Thelia displays on the storefront. Thelia exposes precision via
        // each Currency model (Currency::getDecimalPlaces, default 2),
        // but the module currently doesn't carry the currency context to
        // every formatter — fixed precision keeps it simple and matches
        // two-decimal currencies such as EUR and USD.
        if (floor($number) != $number) {
            return (float) round($number, 2);
        }
        return $number;
    }

    /**
     * Code postal au format accepté par ShopiMind pour les adresses et les abonnés (/^[\w\s\-]+$/) :
     * les caractères hors lettres, chiffres, espaces et tirets sont retirés ; null si rien ne reste.
     *
     * @param mixed $postalCode
     * @return string|null
     */
    public static function sanitizePostalCode( $postalCode )
    {
        $postalCode = trim( (string) preg_replace( '/[^A-Za-z0-9_\s\-]/', '', (string) $postalCode ) );

        return $postalCode !== '' ? $postalCode : null;
    }

    /**
     * Option « Masquer le tag dans le tunnel de commande ». Désactivée par défaut.
     *
     * @return bool
     */
    public static function hideTagOnCheckout(): bool
    {
        try {
            return (bool) (int) ConfigQuery::read( 'shopimind_hide_on_checkout', 0 );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Option « Contournement du cache » : le client et le panier ne sont pas écrits dans la page mais lus par
     * /shopimind/spmq, pour qu'un cache de page ne serve jamais les données d'un visiteur à un autre. Désactivée
     * par défaut : Thelia 2.5 ne met aucune page en cache (réponses « no-cache, private »), l'option ne sert que
     * derrière un cache d'hébergement (Varnish, CDN).
     *
     * @return bool
     */
    public static function useCacheWorkaround(): bool
    {
        try {
            return (bool) (int) ConfigQuery::read( 'shopimind_cache_workaround', 0 );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Check if the module is currently connected
     *
     * @return boolean
     */
    public static function isConnected(){
        try {
            $config = ShopimindQuery::create()->findOne();

            return !empty( $config ) && (bool) $config->getIsConnected();
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Check if the module use real-time synchronization
     *
     * @return boolean
     */
    public static function useRealTimeSynchronization(){
        try {
            $config = ShopimindQuery::create()->findOne();

            return !empty( $config ) && $config->getRealTimeSynchronization() && $config->getIsConnected();
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Retrieves the authentication credentials.
     *
     * @param array $headers
     * @return \Shopimind\SdkShopimind\Http\Client
     */
    public static function getAuth( $headers = [] ): \Shopimind\SdkShopimind\Http\Client
    {
        $config = ShopimindQuery::create()->findOne();
        $apiPassword = !empty( $config ) ? $config->getApiPassword() : '';
        return self::getApiClient( $apiPassword, $headers );
    }

    /**
     * Client du SDK sur l'URL de l'API en vigueur, ou sur l'URL passée en paramètre (connexion faite à l'enregistrement
     * de la configuration, avec les réglages du formulaire).
     *
     * @param string      $apiKey
     * @param array       $headers
     * @param string|null $coreApiUrl
     * @return \Shopimind\SdkShopimind\Http\Client
     */
    public static function getApiClient( $apiKey, $headers = [], $coreApiUrl = null ): \Shopimind\SdkShopimind\Http\Client
    {
        self::applyCoreApiUrlEnv( $coreApiUrl );
        return SpmUtils::getClient( 'v1', (string) $apiKey, $headers );
    }

    /**
     * Resolve the configured log level. Reads `shopimind_log_level` from the
     * Thelia Config key/value table; falls back to legacy `Shopimind.Log`
     * boolean (true -> Info, false -> Off).
     *
     * @return int one of LOG_LEVEL_* constants
     */
    public static function getLogLevel()
    {
        try {
            $value = ConfigQuery::read('shopimind_log_level', null);
            if ($value === null || $value === '') {
                $config = ShopimindQuery::create()->findOne();
                $legacy = !empty($config) ? (bool) $config->getLog() : false;
                $value = $legacy ? self::LOG_LEVEL_INFO : self::LOG_LEVEL_OFF;
            }
            return max(self::LOG_LEVEL_OFF, min(self::LOG_LEVEL_DEBUG, (int) $value));
        } catch (\Throwable $e) {
            return self::LOG_LEVEL_OFF;
        }
    }

    /**
     * Effective core API URL (SDK calls). Resolution order:
     *   1. Debug-only override `shopimind_core_api_url_override` (only honored when log_level = DEBUG)
     *   2. constants.php api.core_api_url
     *   3. Hardcoded fallback `https://core.shopimind.com`
     *
     * The resolved URL is propagated to the vendored SDK via the env var
     * SHOPIMIND_CORE_API_BASE so the SDK can pick it up without depending on Thelia.
     *
     * @return string
     */
    public static function getCoreApiUrl()
    {
        return self::resolveCoreApiUrl(self::getLogLevel(), self::readConfig('shopimind_core_api_url_override'));
    }

    /**
     * URL de l'API pour un niveau de log et une surcharge donnés : la surcharge n'est retenue qu'au niveau Debug et si
     * elle est valide.
     *
     * @param int   $logLevel
     * @param mixed $override
     * @return string
     */
    public static function resolveCoreApiUrl($logLevel, $override)
    {
        if ((int) $logLevel >= self::LOG_LEVEL_DEBUG) {
            $override = self::sanitizeOverrideUrl($override);
            if (!empty($override)) {
                return $override;
            }
        }
        $constants = include THELIA_MODULE_DIR . '/Shopimind/constants.php';
        $default = isset($constants['api']['core_api_url']) ? $constants['api']['core_api_url'] : 'https://core.shopimind.com';
        return rtrim($default, '/');
    }

    /**
     * Surcharge d'URL du mode Debug nettoyée : '' si elle est vide, null si elle est invalide. Seules les URL http(s)
     * complètes sont acceptées, sans identifiants, paramètres ni ancre : la clé API est envoyée à l'URL de l'API et le
     * script est chargé par les pages de la boutique.
     *
     * @param mixed $url
     * @return string|null
     */
    public static function sanitizeOverrideUrl($url)
    {
        if ($url === null) {
            return '';
        }
        if (!is_scalar($url)) {
            return null;
        }
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        $parts = filter_var($url, FILTER_VALIDATE_URL) !== false ? parse_url($url) : false;
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        ) {
            return null;
        }
        return rtrim($url, '/');
    }

    /**
     * Valeur d'un réglage de la table config de Thelia, '' si elle est absente ou illisible.
     *
     * @param string $name
     * @return string
     */
    public static function readConfig($name)
    {
        try {
            return trim((string) ConfigQuery::read($name, ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Effective script URL (front tracking). Resolution order:
     *   1. Debug-only override `shopimind_script_url_override` (only honored when log_level = DEBUG)
     *   2. constants.php api.script_url
     *
     * @return string
     */
    public static function getScriptUrl()
    {
        if (self::getLogLevel() >= self::LOG_LEVEL_DEBUG) {
            $debugOverride = self::sanitizeOverrideUrl(self::readConfig('shopimind_script_url_override'));
            if (!empty($debugOverride)) {
                return $debugOverride;
            }
        }

        // Legacy DB-level override stored on the shopimind table
        try {
            $config = ShopimindQuery::create()->findOne();
            if (!empty($config) && method_exists($config, 'getScriptUrl')) {
                $custom = $config->getScriptUrl();
                if (!empty($custom)) {
                    return rtrim($custom, '/');
                }
            }
        } catch (\Throwable $e) {
            // fall through to constants
        }

        $constants = include THELIA_MODULE_DIR . '/Shopimind/constants.php';
        $default = isset($constants['api']['script_url']) ? $constants['api']['script_url'] : 'https://v2.app-spm.com';
        return rtrim($default, '/');
    }

    /**
     * Propagate the resolved core-API URL to the vendored SDK via the env
     * variable SHOPIMIND_CORE_API_BASE.
     */
    protected static function applyCoreApiUrlEnv($url = null)
    {
        $url = null !== $url ? $url : self::getCoreApiUrl();
        putenv('SHOPIMIND_CORE_API_BASE=' . $url);
        $_ENV['SHOPIMIND_CORE_API_BASE'] = $url;
    }

    /**
     * Leveled logging API.
     *
     * @param string $flow     RT | Passive | Webhook | Cart | HTTP | Boot
     * @param string $object   object kind (Product, Order, Customer, ...)
     * @param string $message
     * @param array  $context  structured context appended as ctx={json}
     * @param mixed  $objectId
     */
    public static function logError($flow, $object, $message, $context = [], $objectId = null)
    {
        self::writeLog(self::LOG_LEVEL_ERROR, 'ERROR', $flow, $object, $message, $context, $objectId);
    }

    /**
     * Safe wrapper for event listener callbacks. Runs $fn(...$args) inside a
     * try/catch so a fatal in any single listener cannot kill the request.
     */
    public static function safeRun($name, $fn, $args = [])
    {
        try {
            return call_user_func_array($fn, $args);
        } catch (\Throwable $e) {
            self::handleHookException($name, $e);
        }
        return null;
    }

    public static function handleHookException($name, $e)
    {
        $msg = '[hook:' . $name . '] ' . get_class($e) . ': ' . $e->getMessage()
             . ' in ' . $e->getFile() . ':' . $e->getLine();
        try {
            self::logError('Hook', $name, $msg, ['trace' => $e->getTraceAsString()]);
        } catch (\Throwable $ignored) {
        }
        @error_log('ShopiMind ' . $msg);
    }

    public static function logWarn($flow, $object, $message, $context = [], $objectId = null)
    {
        self::writeLog(self::LOG_LEVEL_INFO, 'WARN', $flow, $object, $message, $context, $objectId);
    }

    public static function logInfo($flow, $object, $message, $context = [], $objectId = null)
    {
        self::writeLog(self::LOG_LEVEL_INFO, 'INFO', $flow, $object, $message, $context, $objectId);
    }

    public static function logDebug($flow, $object, $message, $context = [], $objectId = null)
    {
        self::writeLog(self::LOG_LEVEL_DEBUG, 'DEBUG', $flow, $object, $message, $context, $objectId);
    }

    /**
     * Standardized SDK response logger. success -> INFO with sent/rejected;
     * failure -> ERROR with status/message; raw response always at DEBUG.
     */
    public static function logSdkResponse($flow, $object, $objectId, $response, $expectedCount = 0)
    {
        $statusCode = is_array($response) && isset($response['statusCode']) ? (int) $response['statusCode'] : 0;
        $isSuccess = $statusCode >= 200 && $statusCode < 300;

        if ($isSuccess) {
            $sent = $expectedCount;
            $rejected = 0;
            if (is_array($response) && isset($response['body']) && is_array($response['body'])) {
                $sent = isset($response['body']['sent_count']) ? (int) $response['body']['sent_count'] : $expectedCount;
                $rejected = isset($response['body']['rejected_count']) ? (int) $response['body']['rejected_count'] : 0;
            } elseif (is_array($response) && isset($response['sent_count'])) {
                $sent = (int) $response['sent_count'];
                $rejected = isset($response['rejected_count']) ? (int) $response['rejected_count'] : 0;
            }
            self::logInfo($flow, $object, sprintf('SDK ok status=%d sent=%d rejected=%d', $statusCode, $sent, $rejected), [], $objectId);
        } else {
            $msg = is_array($response) && isset($response['message']) ? $response['message'] : 'unknown';
            self::logError($flow, $object, sprintf('SDK fail status=%d msg=%s', $statusCode, is_string($msg) ? $msg : json_encode($msg)), [], $objectId);
        }
        self::logDebug($flow, $object, 'SDK raw response', ['response' => $response], $objectId);
    }

    protected static function writeLog($messageLevel, $label, $flow, $object, $message, $context, $objectId)
    {
        // Un log ne doit jamais interrompre le traitement qui l'écrit.
        try {
            if (self::getLogLevel() < $messageLevel) {
                return;
            }
            $file = self::resolveLogFile();
            if ($file === null) {
                return;
            }
            $line = '[' . date('Y-m-d H:i:s') . '] [' . str_pad((string) $label, 5) . '] [' . $flow . ']';
            if ($object !== '' && $object !== null) {
                $tag = (string) $object;
                if (is_scalar($objectId) && $objectId !== '') {
                    $tag .= ' id=' . $objectId;
                }
                $line .= ' [' . $tag . ']';
            }
            $line .= ' ' . (is_scalar($message) ? $message : json_encode($message));
            if (!empty($context)) {
                $line .= ' | ctx=' . (is_string($context) ? $context : json_encode($context));
            }
            SpmLogs::rotateIfNeeded($file);
            @error_log($line . PHP_EOL, 3, $file);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Exception journalisée en erreur : classe, message, fichier et ligne, pile d'appels en contexte.
     *
     * @param string     $flow
     * @param string     $object
     * @param \Throwable $e
     * @param mixed      $objectId
     */
    public static function logException($flow, $object, \Throwable $e, $objectId = null)
    {
        self::logError($flow, $object, get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine(), ['trace' => $e->getTraceAsString()], $objectId);
    }

    /**
     * Réponse JSON d'erreur d'un appel ShopiMind, l'exception étant journalisée.
     *
     * @param string     $flow
     * @param string     $object
     * @param \Throwable $e
     * @param int        $status
     * @return JsonResponse
     */
    public static function spmErrorResponse($flow, $object, \Throwable $e, $status = 500): JsonResponse
    {
        self::logException($flow, $object, $e);

        return new JsonResponse([
            'success' => false,
            'message' => 'Internal error: ' . self::toUtf8($e->getMessage()),
        ], $status);
    }

    /**
     * Chaîne UTF-8 valide : les séquences invalides sont remplacées, json_encode ne peut plus échouer.
     *
     * @param string $value
     * @return string
     */
    public static function toUtf8($value): string
    {
        $value = (string) $value;
        if ($value === '' || preg_match('//u', $value)) {
            return $value;
        }

        return htmlspecialchars_decode(htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'), ENT_NOQUOTES);
    }

    /**
     * Resolve a writable log file path, trying canonical Thelia locations first
     * then the module's own logs/ dir as a fallback. Result is cached for the
     * request. Returns null if no candidate is writable, in which case logging
     * is silently dropped (must never break the caller).
     *
     * @return string|null
     */
    public static function resolveLogFile()
    {
        static $resolved = false;
        static $path = null;
        if ($resolved) {
            return $path;
        }
        $resolved = true;

        $candidates = array();
        // Dossier des logs de Thelia (var/log en 2.5), puis dossier log des installations plus anciennes.
        if (defined('THELIA_LOG_DIR')) {
            $candidates[] = rtrim(THELIA_LOG_DIR, '/\\');
        }
        if (defined('THELIA_ROOT')) {
            $candidates[] = rtrim(THELIA_ROOT, '/\\') . '/log';
        }
        if (defined('THELIA_MODULE_DIR')) {
            $moduleLogs = rtrim(THELIA_MODULE_DIR, '/\\') . '/Shopimind/logs';
            if (!is_dir($moduleLogs)) {
                @mkdir($moduleLogs, 0755, true);
            }
            $candidates[] = $moduleLogs;
        }

        foreach ($candidates as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                $path = $dir . '/shopimind.log';
                return $path;
            }
        }
        return null;
    }

    /**
     * Corps d'un appel de ShopiMind : application/x-www-form-urlencoded, lu sur le corps brut. Des champs envoyés
     * sous un autre encodage (multipart) ne sont pas lus : la signature porte exactement sur les données traitées.
     *
     * @param Request $request
     * @return array
     */
    public static function getSpmRequestBody( Request $request ): array
    {
        $body = [];
        $content = $request->getContent();
        if ( is_string( $content ) && $content !== '' ) {
            parse_str( $content, $body );
        }

        return $body;
    }

    /**
     * Contrôle d'un appel de ShopiMind, à faire avant tout traitement : module connecté, identifiant client et
     * signature HMAC du corps (validateSpmRequest). En-têtes lus par le HeaderBag de Symfony, insensible à la casse.
     *
     * @param Request $request
     * @param array   $body corps renvoyé par getSpmRequestBody()
     * @return JsonResponse|null réponse 401 à renvoyer telle quelle, null si l'appel est authentifié
     */
    public static function authorizeSpmRequest( Request $request, array $body ): ?JsonResponse
    {
        $validation = self::validateSpmRequest( [
            'Shopimind-Client-Identifiant' => (string) $request->headers->get( 'Shopimind-Client-Identifiant', '' ),
            'Shopimind-Token' => (string) $request->headers->get( 'Shopimind-Token', '' ),
        ], $body );

        if ( null === $validation ) {
            return null;
        }

        // HTTP 401 permet à ShopiMind de classer l'échec en erreur d'authentification du module, comme le faisait
        // déjà /shopimind/synchronize.
        return new JsonResponse( $validation, 401 );
    }

    /**
     * Handles the response from a ShopiMind API request.
     *
     * @param array $response
     * @return void
     */
    public static function handleResponse( array $response ){
        // TODO : implement the response handling
        return;
        $statuscode = isset( $response['statusCode'] ) ? $response['statusCode'] : '';
        
        switch ( $statuscode ) {
            case 401:
                $config = ShopimindQuery::create()->findOne();
                $config->setIsConnected(0);
                $config->save();
                break;
            
            default:
                # code...
                break;
        }
    }

    /**
     * Logs synchronization events.
     *
     */
    public static function log( $object, $action, $response, $objectId = null ){
        // Legacy API kept for back-compat with the 27+ callers in
        // EventListeners and PassiveSynchronization. Routed through the
        // leveled logger at INFO.
        try {
            $msg = $action;
            if (!empty($response)) {
                $msg .= ' ' . (is_string($response) ? $response : json_encode($response));
            }
            self::logInfo('Sync', (string) $object, $msg, [], $objectId);
        } catch (\Throwable $th) {
            // swallow
        }
    }

    /**
     * Logs an error message along with the stack trace.
     *
     * @param string $type
     */
    public static function errorLog( \Throwable $th ){
        $date = date('Y-m-d H:i:s');
        print ('- ['.$date.'] ');
        throw $th;
        print (PHP_EOL);
    }
    
    /**
     * Retrieve module parameters
     *
     */
    public static function getParameters(){
        // Lu pendant la compilation du conteneur : un fichier illisible ne doit pas bloquer le site.
        try {
            $parametersFile = THELIA_MODULE_DIR . '/Shopimind/parameters.yml';
            $parameters = file_exists( $parametersFile ) ? Yaml::parseFile( $parametersFile ) : [];

            return is_array( $parameters ) ? $parameters : [];
        } catch ( \Throwable $e ) {
            return [];
        }
    }

    /**
     * Validates a ShopiMind call: module connection, client identifier and HMAC signature of the
     * form-urlencoded body.
     *
     * @param array $requestHeaders
     * @param array $requestBody
     * @return array|null
     */
    public static function validateSpmRequest( array $requestHeaders, array $requestBody )
    {
        try {
            if ( !self::isConnected() ) {
                return [
                    'success' => false,
                    'message' => 'Module is not connected.',
                ];
            }

            $config = ShopimindQuery::create()->findOne();
            $apiKey = $config->getApiPassword();
            $clientId = $config->getApiId();

            // Extract secret part from API key (after the dot)
            $apiKeyParts = explode( '.', $apiKey );
            $secret = isset( $apiKeyParts[1] ) ? $apiKeyParts[1] : $apiKey;
            // Noms d'en-têtes normalisés en minuscules (HTTP/2, proxys).
            $normalizedHeaders = array_change_key_case( $requestHeaders, CASE_LOWER );
            $requestClientId = isset( $normalizedHeaders['shopimind-client-identifiant'] ) ? (string) $normalizedHeaders['shopimind-client-identifiant'] : '';
            $requestHmac = isset( $normalizedHeaders['shopimind-token'] ) ? (string) $normalizedHeaders['shopimind-token'] : '';

            ksort( $requestBody );

            $dataImploded = self::implodeRecursive( $requestBody );
            $hmac = hash_hmac( 'sha256', $dataImploded, hash( 'sha256', $secret ) );

            // Comparaison en temps constant.
            $hmacMatch = hash_equals( $hmac, $requestHmac );
            $clientIdMatch = hash_equals( (string) $clientId, $requestClientId );
            if ( !$hmacMatch || !$clientIdMatch ) {
                // Journalisé sans la signature ni le secret.
                self::logWarn( 'Webhook', 'validateSpmRequest', 'appel refusé', [
                    'clientId_match' => $clientIdMatch,
                    'hmac_match' => $hmacMatch,
                ] );

                return [
                    'success' => false,
                    'message' => 'Unauthorized.',
                ];
            }
        } catch ( \Throwable $th ) {
            try {
                self::logError( 'Webhook', 'validateSpmRequest', 'exception : ' . $th->getMessage() );
            } catch ( \Throwable $ignored ) {
            }

            return [
                'success' => false,
                'message' => 'Unauthorized.',
            ];
        }

        return null;
    }

    /**
     * Recursively implodes an array into a string.
     *
     * @param array $array
     * @param string $glue
     * @return string
     */
    public static function implodeRecursive( array $array, $glue = ';' )
    {
        $paramsCopy = $array;
        $formattedString = '';

        $recursiveImplode = function( $obj ) use ( &$formattedString, &$recursiveImplode ) {
            $keys = array_keys( $obj );
            foreach ( $keys as $el ) {
                if ( is_array( $obj[$el] ) ) {
                    $recursiveImplode( $obj[$el] );
                } else if ( $formattedString == '' && $obj[$el] !== '0' ) {
                    $formattedString .= $el . ';' . $obj[$el];
                } else if ( $obj[$el] != '0' ) {
                    $formattedString .= ';' . $el . ';' . $obj[$el];
                }
            }
        };

        $keys = array_keys( $paramsCopy );
        sort( $keys );
        $ordered = array();
        foreach ( $keys as $key ) {
            $ordered[$key] = $paramsCopy[$key];
        }

        if ( $paramsCopy !== null ) {
            $paramsCopy = $ordered;
            $recursiveImplode( $paramsCopy );
        }

        $formattedString = str_replace( ' ', '', $formattedString );

        return (string) $formattedString;
    }

    /**
     * Get the source label for sync data.
     *
     * @return string
     */
    public static function getSourceLabel(): string
    {
        $constants = include THELIA_MODULE_DIR . '/Shopimind/constants.php';
        return isset($constants['sync']['source_label']) ? $constants['sync']['source_label'] : 'Web';
    }

    /**
     * Check if the module customer family is active
     *
     * @return boolean
     */
    public static function isCustomerFamilyActive()
    {
        try {
            $module = ModuleQuery::create()->findOneByCode( 'CustomerFamily' );

            return !empty( $module ) && (bool) $module->getActivate();
        } catch ( \Throwable $e ) {
            return false;
        }
    }
}
