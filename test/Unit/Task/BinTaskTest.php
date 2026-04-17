<?php

declare(strict_types=1);

namespace Test\Task;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vyse\Installer\Data\Config;
use Vyse\Installer\Task\BinTask;

class BinTaskTest extends TestCase
{
    private string $tempRoot;
    private MockObject & IOInterface $ioMock;
    private Filesystem $fs;
    private BinTask $task;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tempRoot = sys_get_temp_dir() . '/vyse_test_' . uniqid();
        mkdir($this->tempRoot);

        $this->ioMock = $this->createMock(IOInterface::class);
        $this->task = new BinTask();
    }

    protected function tearDown(): void
    {
        $this->fs->removeDirectory($this->tempRoot);
    }

    public function testItBypassesInstallationIfConfigHasNoBinDirs(): void
    {
        $config = new Config();

        $this->ioMock->expects(self::never())->method('write');

        ($this->task)($config, $this->ioMock, $this->tempRoot);

        self::assertDirectoryDoesNotExist($this->tempRoot . '/vyse');
    }

    public function testItSymlinksBinDirectories(): void
    {
        $sourceDir = $this->tempRoot . '/vendor/vyse/toolchain/bin';
        $this->fs->ensureDirectoryExists($sourceDir);
        $sourceFile = $sourceDir . '/test-script';
        file_put_contents($sourceFile, 'echo "hello"');

        $config = (new Config())->addBinDir($sourceDir);

        $this->ioMock->expects(self::once())
            ->method('write')
            ->with(self::stringContains('Installed scripts from 1 packages'))
        ;

        ($this->task)($config, $this->ioMock, $this->tempRoot);

        $targetLink = $this->tempRoot . '/vyse/test-script';

        self::assertTrue(is_link($targetLink), 'Target should be a symlink.');
        self::assertFileExists($targetLink, 'The symlink is broken and does not resolve to the target.');

        $linkTarget = readlink($targetLink);
        assert(is_string($linkTarget));
        self::assertStringContainsString('vendor/vyse/toolchain/bin/test-script', str_replace('\\', '/', $linkTarget));

        self::assertSame('0755', substr(sprintf('%o', fileperms($sourceFile)), -4));
    }

    public function testItWarnsWhenSourceDirectoryDoesNotExist(): void
    {
        $config = (new Config())->addBinDir('/fake/path/that/does/not/exist');

        $this->ioMock->expects(self::once())
            ->method('writeError')
            ->with(self::stringContains('does not exist. Skipping.'))
        ;

        ($this->task)($config, $this->ioMock, $this->tempRoot);
    }
}
