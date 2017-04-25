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
use Composer\Installer\PackageEvent;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class ScriptHandler
{
    public static function __callStatic($name, $arguments)
    {
        $event = $arguments[0];
        if ($event instanceof Event) {
            $event
                ->getIO()
                ->writeError('<error>This script is not longer available</error>. Please read <info>https://github.com/sgomez/simplesamlphp-base/blob/master/UPDATE.md</info>.');
        }
    }

    public static function installModuleHook(PackageEvent $event)
    {
        $sspModule = static::getComposerPackage($event);

        if ($sspModule->getType() === 'simplesamlphp-module') {
            $event
                ->getIO()
                ->write(sprintf('  - Copying <info>%s</info> (<comment>%s</comment>) to modules',
                    $sspModule->getName(),
                    $sspModule->getPrettyVersion()
                ));

            static::installModule($event, $sspModule);
        }
    }

    public static function uninstallModuleHook(PackageEvent $event)
    {
        $sspModule = static::getComposerPackage($event);

        if ($sspModule->getType() === 'simplesamlphp-module') {
            $event
                ->getIO()
                ->write(sprintf('  - Deleting <info>%s</info> (<comment>%s</comment>) from modules',
                    $sspModule->getName(),
                    $sspModule->getPrettyVersion()
                ));

            static::uninstallModule($event, $sspModule);
        }

    }

    /**
     * @param PackageEvent $event
     * @return PackageInterface
     */
    protected static function getComposerPackage(PackageEvent $event)
    {
        return $event
            ->getOperation()
            ->getPackage();
    }

    protected static function getInstallPath(PackageEvent $event, PackageInterface $package)
    {
        return $event
            ->getComposer()
            ->getInstallationManager()
            ->getInstallPath($package);
    }

    protected static function getSimpleSamlPhpPackage(PackageEvent $event)
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

    protected static function getSimpleSamlPhpPackageModules(PackageEvent $event)
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
     * @param PackageEvent $event
     * @param PackageInterface $module
     *
     * @see https://github.com/simplesamlphp/composer-module-installer/blob/master/src/SimpleSamlPhp/Composer/ModuleInstaller.php
     */
    protected static function installModule(PackageEvent $event, PackageInterface $module)
    {
        $destDir = self::getModuleDestinationDir($event, $module);

        $fs = new Filesystem();
        $fs->mirror(
            static::getInstallPath($event, $module),
            $destDir
        );
    }

    /**
     * @param PackageEvent $event
     * @param PackageInterface $module
     */
    protected static function uninstallModule(PackageEvent $event, PackageInterface $module)
    {
        $destDir = self::getModuleDestinationDir($event, $module);
        $fs = new Filesystem();
        $fs->remove($destDir);
    }


    /**
     * @param PackageEvent $event
     * @param PackageInterface $module
     * @return string
     */
    protected static function getModuleDestinationDir(PackageEvent $event, PackageInterface $module)
    {
        $ssp = static::getSimpleSamlPhpPackage($event);
        $sspPath = static::getInstallPath($event, $ssp);

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

        return $destDir;
    }
}