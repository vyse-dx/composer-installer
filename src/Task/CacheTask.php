<?php

declare(strict_types=1);

namespace Vyse\Installer\Task;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Vyse\Installer\Data\Config;
use Vyse\Installer\Interface\TaskInterface;

class CacheTask implements TaskInterface
{
    public function __invoke(
        Config $config,
        IOInterface $io,
        string $projectRoot,
    ): void {
        if (!$config->requiresCache()) {
            return;
        }

        $fs = new Filesystem();
        $cacheDir = $projectRoot . '/.cache';

        $fs->ensureDirectoryExists($cacheDir);

        $gitIgnorePath = $cacheDir . '/.gitignore';
        $gitIgnoreContent = "*\n!.gitignore\n";

        if (!file_exists($gitIgnorePath) || file_get_contents($gitIgnorePath) !== $gitIgnoreContent) {
            file_put_contents($gitIgnorePath, $gitIgnoreContent);
        }

        $io->write('<info>[Vyse] Initialized .cache directory.</info>');
    }
}
