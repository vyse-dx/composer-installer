<?php

declare(strict_types=1);

namespace Vyse\Installer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    public function activate(
        Composer $composer,
        IOInterface $io,
    ): void {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(
        Composer $composer,
        IOInterface $io,
    ): void {
        // No-op for this plugin
    }

    public function uninstall(
        Composer $composer,
        IOInterface $io,
    ): void {
        // No-op for this plugin
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostUpdate',
            ScriptEvents::POST_UPDATE_CMD => 'onPostUpdate',
        ];
    }

    public function onPostUpdate(
        Event $event,
    ): void {
        $fs = new Filesystem();

        // Resolve project root (usually one directory up from vendor/)
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDir);
        $targetDir = $projectRoot . '/vyse';

        // 1. Nuke the existing vyse/ directory to clear orphaned symlinks
        if (is_dir($targetDir)) {
            $fs->removeDirectory($targetDir);
        }

        // 2. Gather all installed packages PLUS the root project package
        $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
        $packages[] = $this->composer->getPackage();

        $installedCount = 0;

        foreach ($packages as $package) {
            $extra = $package->getExtra();

            // Look for the specific vyse-bin key
            if (!isset($extra['vyse-bin'])) {
                continue;
            }

            $sourcePath = $extra['vyse-bin'];

            // Determine absolute source directory based on whether it's a dependency or root
            if ($package === $this->composer->getPackage()) {
                $absSourceDir = $projectRoot . '/' . $sourcePath;
            } else {
                $installPath = $this->composer->getInstallationManager()->getInstallPath($package);
                $absSourceDir = $installPath . '/' . $sourcePath;
            }

            if (!is_dir($absSourceDir)) {
                $this->io->writeError(sprintf(
                    "<warning>Vyse script directory '%s' for package '%s' does not exist.</warning>",
                    $sourcePath,
                    $package->getName(),
                ));

                continue;
            }

            // 3. Merge the directory contents
            $this->mergeDirectory($absSourceDir, $targetDir, $fs);
            $installedCount++;
        }

        if ($installedCount > 0) {
            $this->io->write("<info>Vyse compiled scripts from {$installedCount} packages into vyse/.</info>");
        }
    }

    private function mergeDirectory(
        string $source,
        string $target,
        Filesystem $fs,
    ): void {
        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $subPath = $iterator->getSubPathName();
            $targetPath = $target . '/' . $subPath;

            if ($item->isDir()) {
                // If it's a directory, ensure the real directory exists in the target
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }
            } elseif ($item->isFile()) {
                // Determine the shortest relative path from the target symlink back to the source file
                $relativePath = $fs->findShortestPath($targetPath, $item->getPathname());

                // Handle direct namespace collisions: last package wins
                if (file_exists($targetPath) || is_link($targetPath)) {
                    unlink($targetPath);
                }

                symlink($relativePath, $targetPath);

                // Ensure the underlying source script is executable
                chmod($item->getPathname(), 0755);
            }
        }
    }
}
