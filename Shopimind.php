<?php

namespace Shopimind;

use Propel\Runtime\Connection\ConnectionInterface;
use Shopimind\lib\ShopConnection;
use Shopimind\lib\Utils;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Install\Database;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

class Shopimind extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'shopimind';


    /**
     * @return bool true to continue module activation, false to prevent it
     */
    public function preActivation(ConnectionInterface $con = null)
    {
        // Idempotent : crée la table à la première installation et, sur une table existante
        // (module v1 remplacé sans désinstallation), ajoute les colonnes manquantes.
        self::runLifecycleStep('activation', function () use ($con) {
            self::ensureSchema($con);
            self::ensureDefaultSettings($con);
        });

        return true;
    }

    /**
     * Module activé : s'il est connecté avec une version précédente (réinstallation qui a conservé les données), la
     * version installée est déclarée à ShopiMind. Un échec est journalisé et n'empêche pas l'activation.
     */
    public function postActivation(ConnectionInterface $con = null): void
    {
        self::reconnectAfter('activation');
    }

    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false): void
    {
        // La désinstallation n'est jamais bloquée : chaque échec est journalisé et Thelia supprime le module.
        try {
            self::dropV1Tables(new Database($con));
        } catch (\Throwable $e) {
            self::logLifecycleError('uninstall', $e);
        }

        // Suppression des données non demandée : configuration conservée pour une réinstallation.
        if (!$deleteModuleData) {
            return;
        }

        try {
            (new Database($con))->insertSql(null, [__DIR__.'/Config/sql/destroy.sql']);
        } catch (\Throwable $e) {
            self::logLifecycleError('uninstall', $e);
            try {
                (new Database($con))->execute('SET FOREIGN_KEY_CHECKS = 1');
            } catch (\Throwable $ignored) {
            }
        }

        try {
            ConfigQuery::create()->filterByName(self::CONFIG_KEYS)->delete($con);
        } catch (\Throwable $e) {
            self::logLifecycleError('uninstall', $e);
        }

        // En dernier : une erreur journalisée plus haut ne recrée pas le fichier.
        self::removeLogFiles();
    }

    /**
     * Defines how services are loaded in your modules
     *
     * @param ServicesConfigurator $servicesConfigurator
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*",
                THELIA_MODULE_DIR . ucfirst(self::getModuleCode()) . "/PassiveSynchronization/Scripts/*",
                THELIA_MODULE_DIR . ucfirst(self::getModuleCode()) . "/constants.php"
            ])
            ->autowire(true)
            ->autoconfigure(true);
    }

    /**
     * Execute sql files in Config/update/ folder named with module version (ex: 1.0.1.sql).
     *
     * @param $currentVersion
     * @param $newVersion
     * @param ConnectionInterface $con
     */
    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        self::runLifecycleStep('update', function () use ($currentVersion, $con) {
            $this->applyUpdate($currentVersion, $con);
        });

        // Version installée déclarée à ShopiMind une fois la mise à jour faite, qu'un échec n'annule pas. Module
        // désactivé : la déclaration est faite à son activation.
        if (self::isModuleActive()) {
            self::reconnectAfter('update');
        }
    }

    protected function applyUpdate($currentVersion, ConnectionInterface $con = null): void
    {
        // Module v1 remplacé sans désinstallation : même remise à niveau du schéma qu'à l'activation.
        self::ensureSchema($con);
        self::ensureDefaultSettings($con);

        $updateDir = __DIR__.DS.'Config'.DS.'update';

        if (! is_dir($updateDir)) {
            return;
        }

        $finder = Finder::create()
            ->name('*.sql')
            ->depth(0)
            ->sortByName()
            ->in($updateDir);

        $database = new Database($con);

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }
    }

    /** Réglages du module dans la table config de Thelia, supprimés quand la désinstallation supprime aussi les données. */
    const CONFIG_KEYS = [
        'shopimind_gender_mapping',
        'shopimind_log_level',
        'shopimind_core_api_url_override',
        'shopimind_script_url_override',
        'shopimind_hide_on_checkout',
        'shopimind_cache_workaround',
        'shopimind_last_connection',
    ];

    /**
     * Aligne la base sur Config/schema.xml, sans perte de données :
     *  - table shopimind absente : création par Config/TheliaMain.sql ;
     *  - table présente sans script_url (module v1 1.0.x) : ajout de la colonne ;
     *  - tables du module v1 (shopimind_sync_status, shopimind_sync_errors) : supprimées, inutilisées par cette version.
     * Rejouable sans effet, à chaque activation comme à chaque mise à jour.
     *
     * @param ConnectionInterface|null $con
     */
    public static function ensureSchema(ConnectionInterface $con = null): void
    {
        $database = new Database($con);

        if (false === $database->execute("SHOW TABLES LIKE 'shopimind'")->fetch()) {
            $database->insertSql(null, [__DIR__.'/Config/TheliaMain.sql']);
        } elseif (false === $database->execute("SHOW COLUMNS FROM `shopimind` LIKE 'script_url'")->fetch()) {
            $database->execute('ALTER TABLE `shopimind` ADD COLUMN `script_url` VARCHAR(500) NULL');
        }

        self::dropV1Tables($database);
    }

    /**
     * Tables du module v1 (1.0.x), inutilisées par cette version.
     *
     * @param Database $database
     */
    protected static function dropV1Tables(Database $database): void
    {
        $database->execute('DROP TABLE IF EXISTS `shopimind_sync_status`');
        $database->execute('DROP TABLE IF EXISTS `shopimind_sync_errors`');
    }

    /**
     * Supprime shopimind.log et son archive aux emplacements de Utils::resolveLogFile. Un échec ne va que dans le
     * journal d'erreurs PHP : l'écrire dans le journal du module le recréerait.
     */
    protected static function removeLogFiles(): void
    {
        $directories = [];
        if (defined('THELIA_LOG_DIR')) {
            $directories[] = rtrim(THELIA_LOG_DIR, '/\\');
        }
        if (defined('THELIA_ROOT')) {
            $directories[] = rtrim(THELIA_ROOT, '/\\').'/log';
        }
        if (defined('THELIA_MODULE_DIR')) {
            $directories[] = rtrim(THELIA_MODULE_DIR, '/\\').'/Shopimind/logs';
        }

        foreach ($directories as $directory) {
            foreach (['shopimind.log', 'shopimind.log.1'] as $name) {
                $file = $directory.'/'.$name;
                try {
                    if (is_file($file) && !@unlink($file)) {
                        @error_log('ShopiMind module uninstall: '.$name.' could not be deleted in '.$directory);
                    }
                } catch (\Throwable $e) {
                    @error_log('ShopiMind module uninstall failed: '.$e->getMessage());
                }
            }
        }
        clearstatcache();
    }

    /**
     * Réglages jamais définis : synchronisation en temps réel et tag activés, logs désactivés. Une première
     * installation crée la ligne de configuration ; sur une ligne existante, seules les valeurs absentes (NULL)
     * sont complétées et les choix du marchand sont conservés.
     *
     * @param ConnectionInterface|null $con
     */
    public static function ensureDefaultSettings(ConnectionInterface $con = null): void
    {
        $database = new Database($con);

        if (false === $database->execute('SELECT 1 FROM `shopimind` LIMIT 1')->fetch()) {
            $database->execute('INSERT INTO `shopimind` (`real_time_synchronization`, `script_tag`, `log`, `is_connected`) VALUES (1, 1, 0, 0)');

            return;
        }

        $database->execute('UPDATE `shopimind` SET `real_time_synchronization` = 1 WHERE `real_time_synchronization` IS NULL');
        $database->execute('UPDATE `shopimind` SET `script_tag` = 1 WHERE `script_tag` IS NULL');
        $database->execute('UPDATE `shopimind` SET `log` = 0 WHERE `log` IS NULL');
    }

    /**
     * Module actif en base : Thelia met aussi à jour un module désactivé.
     *
     * @return bool
     */
    protected static function isModuleActive(): bool
    {
        try {
            $module = ModuleQuery::create()->findOneByCode('Shopimind');

            return null !== $module && (int) self::IS_ACTIVATED === (int) $module->getActivate();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reconnexion après une activation ou une mise à jour : jamais d'exception, Thelia n'intercepte que les \Exception.
     *
     * @param string $step
     */
    protected static function reconnectAfter(string $step): void
    {
        try {
            ShopConnection::reconnectSafely($step);
        } catch (\Throwable $e) {
            self::logLifecycleError($step.' reconnection', $e);
        }
    }

    /**
     * Étape d'activation ou de mise à jour : toute erreur, y compris une \Error PHP, est journalisée puis relancée
     * en \RuntimeException, que Thelia intercepte pour afficher le message ; les modifications de tables déjà faites sont
     * conservées.
     *
     * @param string   $step
     * @param callable $callback
     */
    protected static function runLifecycleStep(string $step, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            self::logLifecycleError($step, $e);

            throw new \RuntimeException(sprintf('ShopiMind module %s failed: %s', $step, $e->getMessage()), 0, $e);
        }
    }

    protected static function logLifecycleError(string $step, \Throwable $e): void
    {
        $message = sprintf('ShopiMind module %s failed: %s: %s in %s:%d', $step, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
        @error_log($message);
        try {
            Utils::logError('Boot', 'Shopimind', $message);
        } catch (\Throwable $ignored) {
        }
    }
}
