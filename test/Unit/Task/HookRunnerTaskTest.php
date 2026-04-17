<?php

declare(strict_types=1);

namespace Test\Task;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vyse\Installer\Data\Config;
use Vyse\Installer\Task\HookRunnerTask;

class HookRunnerTaskTest extends TestCase
{
    private string $tempRoot;
    private MockObject & IOInterface $ioMock;
    private Filesystem $fs;
    private HookRunnerTask $task;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tempRoot = sys_get_temp_dir() . '/vyse_test_' . uniqid();
        mkdir($this->tempRoot);

        $this->ioMock = $this->createMock(IOInterface::class);
        $this->task = new HookRunnerTask();
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

        self::assertDirectoryDoesNotExist($this->tempRoot . '/vyse/hooks');
    }

    public function testItCompilesHookRunnersAndSortsScriptsAlphabetically(): void
    {
        $config = (new Config())
            ->addHook('pre-commit', '10-stan', './vendor/bin/stan')
            ->addHook('pre-commit', '00-style', './vendor/bin/style')
            ->addHook('pre-push', '00-test', './vendor/bin/test')
        ;

        $this->ioMock->expects(self::once())
            ->method('write')
            ->with(self::stringContains('Compiled 2 hook runners'))
        ;

        ($this->task)($config, $this->ioMock, $this->tempRoot);

        $preCommitPath = $this->tempRoot . '/vyse/hooks/pre-commit';
        $prePushPath = $this->tempRoot . '/vyse/hooks/pre-push';

        self::assertFileExists($preCommitPath);
        self::assertFileExists($prePushPath);

        self::assertSame('0755', substr(sprintf('%o', fileperms($preCommitPath)), -4));

        $preCommitContent = file_get_contents($preCommitPath);
        assert(is_string($preCommitContent));

        self::assertStringContainsString('set -e', $preCommitContent);

        $stylePos = strpos($preCommitContent, '00-style');
        $stanPos = strpos($preCommitContent, '10-stan');

        self::assertTrue($stylePos < $stanPos, 'Scripts were not sorted alphabetically.');
        self::assertStringContainsString('./vendor/bin/style', $preCommitContent);
    }
}
