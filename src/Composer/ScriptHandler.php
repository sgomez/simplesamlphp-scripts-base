<?php
/*
 * This file is part of the simplesamlphp-scripts-base.
 *
 * (c) Sergio Gómez <sergio@uco.es>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Sgomez\SimpleSamlPhp\Composer;

use Composer\Package\Loader\InvalidPackageException;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class ScriptHandler
{
    public static function installModules(Event $event)
    {
        $ssp = static::getSimpleSamlPhpPackage($event);
        $sspPath = static::getInstallPath($event, $ssp);
        $sspModules = static::getSimpleSamlPhpPackageModules($event);

        /** @var PackageInterface $sspModule */
        foreach ($sspModules as $sspModule) {
            $event
                ->getIO()
                ->write(sprintf('  - Installing <info>%s</info> (<comment>%s</comment>)',
                    $sspModule->getName(),
                    $sspModule->getPrettyVersion()
                ));

            static::installModule($event, $sspModule, $sspPath);
        }
    }

    protected static function getInstallPath(Event $event, PackageInterface $package)
    {
        return $event
            ->getComposer()
            ->getInstallationManager()
            ->getInstallPath($package);
    }

    protected static function getSimpleSamlPhpPackage(Event $event)
    {
        $package = $event
            ->getComposer()
            ->getRepositoryManager()
            ->getLocalRepository()
            ->findPackage('simplesamlphp/simplesamlphp', '*');

        if (!$package) {
            throw new InvalidPackageException(['Error: simpleSAMLphp package not found in composer repository'], [], []);
        }

        return $package;
    }

    protected static function getSimpleSamlPhpPackageModules(Event $event)
    {
        $packages = $event
            ->getComposer()
            ->getRepositoryManager()
            ->getLocalRepository()
            ->getPackages();

        return array_filter($packages, function(PackageInterface $package) {
            return $package->getType() === 'simplesamlphp-module';
        });
    }

    /**
     * @param PackageInterface $module
     * @param $sspPath
     *
     * @see https://github.com/simplesamlphp/composer-module-installer/blob/master/src/SimpleSamlPhp/Composer/ModuleInstaller.php
     */
    protected static function installModule(Event $event, PackageInterface $module, $sspPath)
    {
        $name = $module->getPrettyName();
        if (!preg_match('@^.*/simplesamlphp-module-(.+)$@', $name, $matches)) {
            throw new \InvalidArgumentException(
                'Unable to install module '.$name.', package name must be on the form "VENDOR/simplesamlphp-module-MODULENAME".'
            );
        }
        $moduleDir = $matches[1];

        if (!preg_match('@^[a-z0-9_.-]*$@', $moduleDir)) {
            throw new \InvalidArgumentException(
                'Unable to install module '.$name.', module name must only contain characters from a-z, 0-9, "_", "." and "-".'
            );
        }
        if ($moduleDir[0] === '.') {
            throw new \InvalidArgumentException(
                'Unable to install module '.$name.', module name cannot start with ".".'
            );
        }

        /* Composer packages are supposed to only contain lowercase letters, but historically many modules have had names in mixed case.
         * We must provide a way to handle those. Here we allow the module directory to be overridden with a mixed case name.
         */
        $options = $module->getExtra();

        if (isset($options['ssp-mixedcase-module-name'])) {
            $mixedCaseModuleName = $options['ssp-mixedcase-module-name'];
            if (!is_string($mixedCaseModuleName)) {
                throw new \InvalidArgumentException(
                    'Unable to install module '.$name.', "ssp-mixedcase-module-name" must be a string.'
                );
            }
            if (mb_strtolower($mixedCaseModuleName, 'utf-8') !== $moduleDir) {
                throw new \InvalidArgumentException(
                    'Unable to install module '.$name.', "ssp-mixedcase-module-name" must match the package name except that it can contain uppercase letters.'
                );
            }
            $moduleDir = $mixedCaseModuleName;
        }

        $destDir = $sspPath.'/modules/'.$moduleDir;

        $fs = new Filesystem();
        $fs->mirror(
            static::getInstallPath($event, $module),
            $destDir
        );
    }
}