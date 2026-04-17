<?php

declare(strict_types=1);

namespace Test\Task;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vyse\Installer\Data\Config;
use Vyse\Installer\Task\GitProxyTask;

class GitProxyTaskTest extends TestCase
{
    private string $tempRoot;
    private MockObject & IOInterface $ioMock;
    private Filesystem $fs;
    private GitProxyTask $task;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tempRoot = sys_get_temp_dir() . '/vyse_test_' . uniqid();
        mkdir($this->tempRoot);

        $this->ioMock = $this->createMock(IOInterface::class);
        $this->task = new GitProxyTask();
    }

    protected function tearDown(): void
    {
        $this->fs->removeDirectory($this->tempRoot);
    }

    public function testItBypassesExecutionIfConfigHasNoHooks(): void
    {
        $config = new Config();

        $this->ioMock->expects(self::never())->method('write');

        ($this->task)($config, $this->ioMock, $this->tempRoot);

        self::assertDirectoryDoesNotExist($this->tempRoot . '/.git/hooks');
    }

    public function testItWarnsAndBypassesIfGitHooksDirectoryIsMissing(): void
    {
        $config = (new Config())->addHook('pre-commit', '00-style', './vendor/bin/style');

        $this->ioMock->expects(self::once())
            ->method('writeError')
            ->with(self::stringContains('No .git/hooks directory found'))
        ;

        ($this->task)($config, $this->ioMock, $this->tempRoot);

        self::assertDirectoryDoesNotExist($this->tempRoot . '/.git/hooks');
    }

    public function testItInstallsGitHookProxies(): void
    {
        $this->fs->ensureDirectoryExists($this->tempRoot . '/.git/hooks');

        $config = (new Config())
            ->addHook('pre-commit', '00-style', './vendor/bin/style')
            ->addHook('pre-push', '00-test', './vendor/bin/test')
        ;

        $this->ioMock->expects(self::once())
            ->method('write')
            ->with(self::stringContains('Installed 2 Git hook proxies'))
        ;

        ($this->task)($config, $this->ioMock, $this->tempRoot);

        $preCommitPath = $this->tempRoot . '/.git/hooks/pre-commit';
        $prePushPath = $this->tempRoot . '/.git/hooks/pre-push';

        self::assertFileExists($preCommitPath);
        self::assertFileExists($prePushPath);

        self::assertSame('0755', substr(sprintf('%o', fileperms($preCommitPath)), -4));

        $preCommitContent = file_get_contents($preCommitPath);
        assert(is_string($preCommitContent));
        self::assertStringContainsString('exec "./vyse/hooks/pre-commit"', $preCommitContent);
    }
}
