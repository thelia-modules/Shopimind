<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Composer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;

/**
 * Class TheliaInstaller
 * @package Thelia\Composer
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class TheliaInstaller extends LibraryInstaller
{
    /**
     * Historical Thelia 2 layout: the plugin owns every thelia-* type.
     */
    const THELIA2_LOCATIONS = [
        'thelia-module' => 'local/modules/',
        'thelia-frontoffice-template' => 'templates/frontOffice/',
        'thelia-backoffice-template' => 'templates/backOffice/',
        'thelia-email-template' => 'templates/email/',
        'thelia-pdf-template' => 'templates/pdf/',
        'thelia-local' => 'local/',
        'thelia-core' => ''
    ];

    /**
     * Thelia 3 layout: the root project routes modules through
     * composer/installers' installer-paths (vendor/thelia/modules) and keeps
     * thelia/core in vendor, so the plugin only owns the template types.
     */
    const THELIA3_LOCATIONS = [
        'thelia-frontoffice-template' => 'templates/frontOffice/',
        'thelia-backoffice-template' => 'templates/backOffice/',
        'thelia-email-template' => 'templates/email/',
        'thelia-pdf-template' => 'templates/pdf/',
        'thelia-local' => 'local/',
    ];

    protected $locations;

    public function __construct(IOInterface $io, Composer $composer)
    {
        parent::__construct($io, $composer);

        // A Thelia 3 root declares an installer-paths rule for thelia-module;
        // a Thelia 2 root does not. This keeps a single 1.x release line
        // installing both layouts correctly.
        $this->locations = $this->rootRoutesModulesThroughInstallerPaths($composer)
            ? self::THELIA3_LOCATIONS
            : self::THELIA2_LOCATIONS;
    }

    public function getInstallPath(PackageInterface $package)
    {
        $type = $package->getType();
        if (!isset($this->locations[$package->getType()])) {
            throw new \InvalidArgumentException(sprintf('package type "%s" is not supported', $type));
        }

        $base = $this->locations[$type];

        $prettyName = $package->getPrettyName();
        if (strpos($prettyName, '/') !== false) {
            list($vendor, $name) = explode('/', $prettyName);
        } else {
            $vendor = '';
            $name = $prettyName;
        }

        $extra = $package->getExtra();
        if (!empty($extra['installer-name'])) {
            $name = $extra['installer-name'];
        }

        return $base . $name;
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return array_key_exists($packageType, $this->locations);
    }

    /**
     * @return bool
     */
    private function rootRoutesModulesThroughInstallerPaths(Composer $composer)
    {
        $extra = $composer->getPackage()->getExtra();
        $installerPaths = isset($extra['installer-paths']) ? $extra['installer-paths'] : [];

        foreach ($installerPaths as $matchers) {
            foreach ((array) $matchers as $matcher) {
                if ('type:thelia-module' === $matcher) {
                    return true;
                }
            }
        }

        return false;
    }
}
