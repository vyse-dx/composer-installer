<?php

declare(strict_types=1);

namespace Vyse\Installer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Vyse\Installer\Parser\ConfigParser;
use Vyse\Installer\Task\BinTask;
use Vyse\Installer\Task\CacheTask;
use Vyse\Installer\Task\GitProxyTask;
use Vyse\Installer\Task\HookRunnerTask;

final readonly class Plugin implements PluginInterface, EventSubscriberInterface
{
    public function __construct(
        private ConfigParser $parser = new ConfigParser,
        private BinTask $binTask = new BinTask,
        private HookRunnerTask $hookRunnerTask = new HookRunnerTask,
        private GitProxyTask $gitProxyTask = new GitProxyTask,
        private CacheTask $cacheTask = new CacheTask,
    ) {
    }

    public function activate(
        Composer $composer,
        IOInterface $io,
    ): void {
    }

    public function deactivate(
        Composer $composer,
        IOInterface $io,
    ): void {
    }

    public function uninstall(
        Composer $composer,
        IOInterface $io,
    ): void {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'executePipeline',
            ScriptEvents::POST_UPDATE_CMD => 'executePipeline',
        ];
    }

    public static function install(
        Event $event,
    ): void {
        (new self())->executePipeline($event);
    }

    public function executePipeline(
        Event $event,
    ): void {
        $composer = $event->getComposer();
        $io = $event->getIO();

        $vendorDir = $composer->getConfig()->get('vendor-dir');

        if (!is_string($vendorDir)) {
            $io->writeError('<warning>[Vyse] Cannot determine vendor-dir. Skipping installation.</warning>');

            return;
        }

        $projectRoot = dirname($vendorDir);

        $config = ($this->parser)($composer);

        if ($config->hasBinDirs()) {
            ($this->binTask)($config, $io, $projectRoot);
        }

        if ($config->hasHooks()) {
            ($this->hookRunnerTask)($config, $io, $projectRoot);
            ($this->gitProxyTask)($config, $io, $projectRoot);
        }

        if ($config->requiresCache()) {
            ($this->cacheTask)($config, $io, $projectRoot);
        }
    }
}
