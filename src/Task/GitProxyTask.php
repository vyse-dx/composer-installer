<?php

declare(strict_types=1);

namespace Vyse\Installer\Task;

use Composer\IO\IOInterface;
use Vyse\Installer\Data\Config;
use Vyse\Installer\Interface\TaskInterface;

class GitProxyTask implements TaskInterface
{
    public function __invoke(
        Config $config,
        IOInterface $io,
        string $projectRoot,
    ): void {
        if (!$config->hasHooks()) {
            return;
        }

        // Note: For advanced setups, you might want to query `git config core.hooksPath` here in the future
        $gitHooksDir = $projectRoot . '/.git/hooks';

        if (!is_dir($gitHooksDir)) {
            $io->writeError('<warning>[Vyse] No .git/hooks directory found. Are you in a Git repository? Skipping proxy installation.</warning>');

            return;
        }

        $installedCount = 0;

        // We only create proxies for the events we actually have configured
        foreach (array_keys($config->getHooks()) as $event) {
            $targetPath = $gitHooksDir . '/' . $event;

            // This simply forwards the execution to the runner we built in HookRunnerTask
            $bashProxy = <<<BASH
#!/usr/bin/env bash
# Vyse Proxy Hook
if [ -x "./vyse/hooks/{$event}" ]; then
    exec "./vyse/hooks/{$event}"
fi

BASH;

            file_put_contents($targetPath, $bashProxy);
            chmod($targetPath, 0755);

            $installedCount++;
        }

        if ($installedCount > 0) {
            $io->write("<info>[Vyse] Installed {$installedCount} Git hook proxies into .git/hooks/.</info>");
        }
    }
}
