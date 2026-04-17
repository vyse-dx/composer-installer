<?php

declare(strict_types=1);

namespace Vyse\Installer\Parser;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Vyse\Installer\Data\Config;

class ConfigParser
{
    /**
     * Scans all installed packages and the root project for Vyse configurations.
     */
    public function __invoke(
        Composer $composer,
    ): Config {
        $config = new Config();

        $vendorDir = $composer->getConfig()->get('vendor-dir');
        assert(is_string($vendorDir));

        $projectRoot = dirname($vendorDir);

        $packages = $composer->getRepositoryManager()->getLocalRepository()->getPackages();
        $packages[] = $composer->getPackage();

        foreach ($packages as $package) {
            $extra = $package->getExtra();

            if (!isset($extra['vyse']) || !is_array($extra['vyse'])) {
                continue;
            }

            $vyseConfig = $extra['vyse'];

            if (isset($vyseConfig['bin'])) {
                $binDirs = is_array($vyseConfig['bin']) ? $vyseConfig['bin'] : [$vyseConfig['bin']];
                $baseDir = $this->resolvePackageDir($package, $projectRoot, $composer);

                if ($baseDir !== null) {
                    foreach ($binDirs as $binDir) {
                        if (is_string($binDir)) {
                            $config->addBinDir($baseDir . '/' . ltrim($binDir, '/'));
                        }
                    }
                }
            }

            if (isset($vyseConfig['hooks']) && is_array($vyseConfig['hooks'])) {
                foreach ($vyseConfig['hooks'] as $event => $hooks) {
                    if (!is_array($hooks)) {
                        continue;
                    }

                    foreach ($hooks as $name => $script) {
                        if (is_string($name) && is_string($script)) {
                            $config->addHook($event, $name, $script);
                        }
                    }
                }
            }
        }

        return $config;
    }

    /**
     * Determines the absolute path to the package's root directory.
     */
    private function resolvePackageDir(
        PackageInterface $package,
        string $projectRoot,
        Composer $composer,
    ): ?string {
        if ($package === $composer->getPackage()) {
            return $projectRoot;
        }

        return $composer->getInstallationManager()->getInstallPath($package);
    }
}
