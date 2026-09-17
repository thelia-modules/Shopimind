<?php

namespace Shopimind\lib;

require_once THELIA_MODULE_DIR . '/Shopimind/vendor-module/autoload.php';

use Shopimind\Model\ShopimindQuery;
use Shopimind\SdkShopimind\Exception\ClientException;
use Thelia\Model\Base\CurrencyQuery;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\ConfigQuery;

/**
 * Connexion de la boutique à ShopiMind (POST shop/connection), commune au formulaire de configuration et à la
 * reconnexion faite après une mise à jour ou une activation du module.
 */
class ShopConnection
{
    /** Dernière connexion acceptée par ShopiMind : url_client, timezone et module_version envoyés (JSON). */
    const LAST_CONNECTION_KEY = 'shopimind_last_connection';

    /** Préfixe des routes du module sur la boutique. */
    const ROUTE_PREFIX = '/shopimind';

    /** Délais de la reconnexion, en secondes : elle peut s'exécuter pendant une requête de la boutique. */
    const CONNECT_TIMEOUT = 3;
    const TIMEOUT = 5;

    /**
     * Version du module envoyée en module_version.
     *
     * @return string
     */
    public static function moduleVersion(): string
    {
        $constants = include THELIA_MODULE_DIR . '/Shopimind/constants.php';

        return (string) $constants['module']['version'];
    }

    /**
     * En-têtes de la connexion : client-version est la version du protocole, exigée à 5.0.0 ou plus par ShopiMind.
     *
     * @param string $apiId
     * @return array
     */
    public static function headers(string $apiId): array
    {
        $constants = include THELIA_MODULE_DIR . '/Shopimind/constants.php';

        return [
            'client-id' => $apiId,
            'client-version' => $constants['module']['client_version'],
            'current-build' => 1,
        ];
    }

    /**
     * Données de connexion. Fuseau horaire vide : celui de PHP.
     *
     * @param string $urlClient
     * @param string $timezone
     * @return array|null null sans devise ou langue par défaut
     */
    public static function buildPayload(string $urlClient, string $timezone = ''): ?array
    {
        $currency = CurrencyQuery::create()->findOneByByDefault(1);
        if (empty($currency)) {
            return null;
        }

        $defaultLang = LangQuery::create()->findOneByByDefault(1);
        if (empty($defaultLang)) {
            return null;
        }

        $langs = [];
        foreach (LangQuery::create()->filterByActive(1)->find() as $lang) {
            $langs[] = $lang->getCode();
        }

        $theliaVersion = ConfigQuery::create()->findByName('thelia_version')->getColumnValues('value');

        return [
            'default_currency' => $currency->getCode(),
            'default_lang' => $defaultLang->getCode(),
            'langs' => $langs,
            'timezone' => '' !== $timezone ? $timezone : date_default_timezone_get(),
            'url_client' => $urlClient,
            'ecommerce_version' => reset($theliaVersion),
            'module_version' => self::moduleVersion(),
        ];
    }

    /**
     * Réponse de ShopiMind acceptant la connexion.
     *
     * @param mixed $response
     * @return bool
     */
    public static function isSuccess($response): bool
    {
        return is_array($response) && isset($response['statusCode']) && 200 === (int) $response['statusCode'];
    }

    /**
     * Dernière connexion acceptée ; champs vides si elle n'a jamais été enregistrée ou est illisible.
     *
     * @return array url_client, timezone, module_version
     */
    public static function lastConnection(): array
    {
        $last = ['url_client' => '', 'timezone' => '', 'module_version' => ''];

        try {
            // Sans le cache de configuration : Thelia ne le vide pas pour une valeur écrite pendant son démarrage.
            $stored = json_decode((string) ConfigQuery::read(self::LAST_CONNECTION_KEY, '', true), true);
        } catch (\Throwable $e) {
            return $last;
        }

        if (!is_array($stored)) {
            return $last;
        }

        foreach (array_keys($last) as $key) {
            if (isset($stored[$key]) && is_string($stored[$key])) {
                $last[$key] = $stored[$key];
            }
        }

        return $last;
    }

    /**
     * Enregistre la connexion acceptée par ShopiMind.
     *
     * @param array $payload données envoyées
     */
    public static function rememberConnection(array $payload): void
    {
        ConfigQuery::write(self::LAST_CONNECTION_KEY, json_encode([
            'url_client' => (string) ($payload['url_client'] ?? ''),
            'timezone' => (string) ($payload['timezone'] ?? ''),
            'module_version' => (string) ($payload['module_version'] ?? ''),
        ], JSON_UNESCAPED_SLASHES));
    }

    /**
     * Module connecté dont la version installée n'a pas encore été déclarée à ShopiMind : la configuration doit
     * être enregistrée de nouveau.
     *
     * @return bool
     */
    public static function needsReconnection(): bool
    {
        try {
            return Utils::isConnected() && self::lastConnection()['module_version'] !== self::moduleVersion();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reconnexion après une mise à jour ou une activation : jamais d'exception, un échec est journalisé.
     *
     * @param string $step update ou activation
     * @return bool
     */
    public static function reconnectSafely(string $step): bool
    {
        try {
            return static::reconnect($step);
        } catch (\Throwable $e) {
            try {
                static::logFailure($step, sprintf('%s: %s in %s:%d', get_class($e), $e->getMessage(), basename($e->getFile()), $e->getLine()));
            } catch (\Throwable $ignored) {
            }

            return false;
        }
    }

    /**
     * Renvoie la dernière connexion acceptée avec la version installée. Identifiants et is_connected ne sont jamais
     * modifiés.
     *
     * @param string $step
     * @return bool
     */
    public static function reconnect(string $step): bool
    {
        $config = ShopimindQuery::create()->findOne();
        $apiId = !empty($config) ? (string) $config->getApiId() : '';
        $apiPassword = !empty($config) ? (string) $config->getApiPassword() : '';

        if ('' === trim($apiId) || '' === trim($apiPassword) || !$config->getIsConnected()) {
            Utils::logInfo('Boot', 'Connection', sprintf('No reconnection to ShopiMind after %s: the module is not connected', $step));

            return false;
        }

        // Seule une connexion déjà acceptée par ShopiMind est renvoyée, avec la même URL de boutique et le même fuseau
        // horaire : une copie de la boutique ne peut pas déclarer une autre URL à sa place.
        $last = self::lastConnection();
        if ('' === $last['url_client'] || '' === $last['timezone']) {
            Utils::logInfo('Boot', 'Connection', sprintf('No reconnection to ShopiMind after %s: no saved connection', $step));

            return false;
        }

        if ($last['module_version'] === self::moduleVersion()) {
            Utils::logInfo('Boot', 'Connection', sprintf('No reconnection to ShopiMind after %s: version %s already declared', $step, $last['module_version']));

            return false;
        }

        $payload = self::buildPayload($last['url_client'], $last['timezone']);
        if (null === $payload) {
            static::logFailure($step, 'no default currency or language');

            return false;
        }

        $response = static::send($apiId, $apiPassword, $payload);
        if (!self::isSuccess($response)) {
            static::logFailure($step, 'ShopiMind response: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            return false;
        }

        self::rememberConnection($payload);
        Utils::logInfo('Boot', 'Connection', sprintf('Module reconnected to ShopiMind after %s: module_version %s, url_client %s', $step, $payload['module_version'], $payload['url_client']));

        return true;
    }

    /**
     * POST shop/connection sur l'URL de l'API en vigueur, avec des délais courts.
     *
     * @param string $apiId
     * @param string $apiPassword
     * @param array  $payload
     * @return mixed réponse JSON décodée
     */
    protected static function send(string $apiId, string $apiPassword, array $payload)
    {
        $client = Utils::getApiClient($apiPassword, self::headers($apiId));

        try {
            $response = $client->post('shop/connection', [
                'json' => $payload,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout' => self::TIMEOUT,
            ]);
        } catch (ClientException $e) {
            // Connexion refusée (clé invalide, données rejetées) : le motif est dans le corps, sinon dans l'exception.
            $decoded = $e->hasResponse() ? json_decode((string) $e->getResponse()->getBody(), true) : null;

            return is_array($decoded) ? $decoded : ['httpStatus' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0, 'message' => $e->getMessage()];
        }

        $decoded = json_decode((string) $response->getBody(), true);

        return is_array($decoded) ? $decoded : ['httpStatus' => $response->getStatusCode(), 'message' => 'invalid JSON body'];
    }

    protected static function logFailure(string $step, string $reason): void
    {
        $message = sprintf('ShopiMind module reconnection after %s failed: %s', $step, $reason);
        @error_log($message);
        Utils::logError('Boot', 'Connection', $message);
    }
}
