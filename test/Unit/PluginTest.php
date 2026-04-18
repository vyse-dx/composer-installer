<?php

declare(strict_types=1);

namespace Test;

use Composer\Composer;
use Composer\Config as ComposerConfig;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vyse\Installer\Data\Config;
use Vyse\Installer\Parser\ConfigParser;
use Vyse\Installer\Plugin;
use Vyse\Installer\Task\BinTask;
use Vyse\Installer\Task\CacheTask;
use Vyse\Installer\Task\GitProxyTask;
use Vyse\Installer\Task\HookRunnerTask;

class PluginTest extends TestCase
{
    private MockObject & Composer $composerMock;
    private MockObject & IOInterface $ioMock;
    private MockObject & ComposerConfig $composerConfigMock;
    private MockObject & Event $eventMock;

    private MockObject & ConfigParser $parserMock;
    private MockObject & BinTask $binTaskMock;
    private MockObject & HookRunnerTask $hookRunnerTaskMock;
    private MockObject & GitProxyTask $gitProxyTaskMock;
    private MockObject & CacheTask $cacheTaskMock;

    private Plugin $plugin;

    protected function setUp(): void
    {
        $this->composerMock = $this->createMock(Composer::class);
        $this->ioMock = $this->createMock(IOInterface::class);
        $this->composerConfigMock = $this->createMock(ComposerConfig::class);

        $this->composerMock->method('getConfig')->willReturn($this->composerConfigMock);

        $this->eventMock = $this->createMock(Event::class);
        $this->eventMock->method('getComposer')->willReturn($this->composerMock);
        $this->eventMock->method('getIO')->willReturn($this->ioMock);

        $this->parserMock = $this->createMock(ConfigParser::class);
        $this->binTaskMock = $this->createMock(BinTask::class);
        $this->hookRunnerTaskMock = $this->createMock(HookRunnerTask::class);
        $this->gitProxyTaskMock = $this->createMock(GitProxyTask::class);
        $this->cacheTaskMock = $this->createMock(CacheTask::class);

        $this->plugin = new Plugin(
            $this->parserMock,
            $this->binTaskMock,
            $this->hookRunnerTaskMock,
            $this->gitProxyTaskMock,
            $this->cacheTaskMock,
        );
    }

    public function testItSubscribesToCorrectComposerEvents(): void
    {
        $events = Plugin::getSubscribedEvents();

        self::assertArrayHasKey(ScriptEvents::POST_INSTALL_CMD, $events);
        self::assertArrayHasKey(ScriptEvents::POST_UPDATE_CMD, $events);
        self::assertSame('executePipeline', $events[ScriptEvents::POST_INSTALL_CMD]);
    }

    public function testItAbortsPipelineIfVendorDirCannotBeDetermined(): void
    {
        $this->composerConfigMock->expects(self::once())
            ->method('get')
            ->with('vendor-dir')
            ->willReturn(null)
        ;

        $this->ioMock->expects(self::once())
            ->method('writeError')
            ->with(self::stringContains('Cannot determine vendor-dir'))
        ;

        $this->parserMock->expects(self::never())->method('__invoke');
        $this->binTaskMock->expects(self::never())->method('__invoke');
        $this->hookRunnerTaskMock->expects(self::never())->method('__invoke');
        $this->gitProxyTaskMock->expects(self::never())->method('__invoke');
        $this->cacheTaskMock->expects(self::never())->method('__invoke');

        $this->plugin->executePipeline($this->eventMock);
    }

    public function testItOnlyExecutesBinTaskWhenOnlyBinDirsAreConfigured(): void
    {
        $this->composerConfigMock->expects(self::once())
            ->method('get')
            ->with('vendor-dir')
            ->willReturn('/var/www/vendor')
        ;

        $projectRoot = '/var/www';

        $vyseConfigMock = $this->createMock(Config::class);
        $vyseConfigMock->method('hasBinDirs')->willReturn(true);
        $vyseConfigMock->method('hasHooks')->willReturn(false);
        $vyseConfigMock->method('requiresCache')->willReturn(false);

        $this->parserMock->expects(self::once())
            ->method('__invoke')
            ->with($this->composerMock)
            ->willReturn($vyseConfigMock)
        ;

        $this->binTaskMock->expects(self::once())
            ->method('__invoke')
            ->with($vyseConfigMock, $this->ioMock, $projectRoot)
        ;

        $this->hookRunnerTaskMock->expects(self::never())->method('__invoke');
        $this->gitProxyTaskMock->expects(self::never())->method('__invoke');
        $this->cacheTaskMock->expects(self::never())->method('__invoke');

        $this->plugin->executePipeline($this->eventMock);
    }

    public function testItOnlyExecutesHookTasksWhenOnlyHooksAreConfigured(): void
    {
        $this->composerConfigMock->expects(self::once())
            ->method('get')
            ->with('vendor-dir')
            ->willReturn('/var/www/vendor')
        ;

        $projectRoot = '/var/www';

        $vyseConfigMock = $this->createMock(Config::class);
        $vyseConfigMock->method('hasBinDirs')->willReturn(false);
        $vyseConfigMock->method('hasHooks')->willReturn(true);
        $vyseConfigMock->method('requiresCache')->willReturn(false);

        $this->parserMock->expects(self::once())
            ->method('__invoke')
            ->with($this->composerMock)
            ->willReturn($vyseConfigMock)
        ;

        $this->binTaskMock->expects(self::never())->method('__invoke');

        $this->hookRunnerTaskMock->expects(self::once())
            ->method('__invoke')
            ->with($vyseConfigMock, $this->ioMock, $projectRoot)
        ;

        $this->gitProxyTaskMock->expects(self::once())
            ->method('__invoke')
            ->with($vyseConfigMock, $this->ioMock, $projectRoot)
        ;

        $this->cacheTaskMock->expects(self::never())->method('__invoke');

        $this->plugin->executePipeline($this->eventMock);
    }

    public function testItOnlyExecutesCacheTaskWhenOnlyCacheIsConfigured(): void
    {
        $this->composerConfigMock->expects(self::once())
            ->method('get')
            ->with('vendor-dir')
            ->willReturn('/var/www/vendor')
        ;

        $projectRoot = '/var/www';

        $vyseConfigMock = $this->createMock(Config::class);
        $vyseConfigMock->method('hasBinDirs')->willReturn(false);
        $vyseConfigMock->method('hasHooks')->willReturn(false);
        $vyseConfigMock->method('requiresCache')->willReturn(true);

        $this->parserMock->expects(self::once())
            ->method('__invoke')
            ->with($this->composerMock)
            ->willReturn($vyseConfigMock)
        ;

        $this->binTaskMock->expects(self::never())->method('__invoke');
        $this->hookRunnerTaskMock->expects(self::never())->method('__invoke');
        $this->gitProxyTaskMock->expects(self::never())->method('__invoke');

        $this->cacheTaskMock->expects(self::once())
            ->method('__invoke')
            ->with($vyseConfigMock, $this->ioMock, $projectRoot)
        ;

        $this->plugin->executePipeline($this->eventMock);
    }

    public function testItExecutesFullPipelineWhenEverythingIsConfigured(): void
    {
        $this->composerConfigMock->expects(self::once())
            ->method('get')
            ->with('vendor-dir')
            ->willReturn('/var/www/vendor')
        ;

        $vyseConfigMock = $this->createMock(Config::class);
        $vyseConfigMock->method('hasBinDirs')->willReturn(true);
        $vyseConfigMock->method('hasHooks')->willReturn(true);
        $vyseConfigMock->method('requiresCache')->willReturn(true);

        $this->parserMock->expects(self::once())
            ->method('__invoke')
            ->willReturn($vyseConfigMock)
        ;

        $this->binTaskMock->expects(self::once())->method('__invoke');
        $this->hookRunnerTaskMock->expects(self::once())->method('__invoke');
        $this->gitProxyTaskMock->expects(self::once())->method('__invoke');
        $this->cacheTaskMock->expects(self::once())->method('__invoke');

        $this->plugin->executePipeline($this->eventMock);
    }
}
