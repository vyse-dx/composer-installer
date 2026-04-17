<?php

declare(strict_types=1);

namespace Vyse\Installer\Task;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Vyse\Installer\Data\Config;
use Vyse\Installer\Interface\TaskInterface;

class BinTask implements TaskInterface
{
    public function __invoke(
        Config $config,
        IOInterface $io,
        string $projectRoot,
    ): void {
        if (!$config->hasBinDirs()) {
            return;
        }

        $fs = new Filesystem();
        $targetDir = $projectRoot . '/vyse';

        if (is_dir($targetDir)) {
            $fs->removeDirectory($targetDir);
        }

        $installedCount = 0;

        foreach ($config->getBinDirs() as $source) {
            if (!is_dir($source)) {
                $io->writeError("<warning>[Vyse] Script directory '{$source}' does not exist. Skipping.</warning>");

                continue;
            }

            $this->mergeDirectory($source, $targetDir, $fs);
            $installedCount++;
        }

        if ($installedCount > 0) {
            $io->write("<info>[Vyse] Installed scripts from {$installedCount} packages into ./vyse/.</info>");
        }
    }

    private function mergeDirectory(
        string $source,
        string $target,
        Filesystem $fs,
    ): void {
        $fs->ensureDirectoryExists($target);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            assert($item instanceof SplFileInfo);

            $subPath = $iterator->getSubPathname();
            $targetPath = $target . '/' . $subPath;

            if ($item->isDir()) {
                $fs->ensureDirectoryExists($targetPath);
            } elseif ($item->isFile()) {
                // Reverted back to $targetPath. Composer handles the dirname() internally.
                $relativePath = $fs->findShortestPath($targetPath, $item->getPathname());

                if (file_exists($targetPath) || is_link($targetPath)) {
                    unlink($targetPath);
                }

                symlink($relativePath, $targetPath);

                chmod($item->getPathname(), 0755);
            }
        }
    }
}
