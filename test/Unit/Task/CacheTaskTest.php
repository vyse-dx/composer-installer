<?php

declare(strict_types=1);

namespace Test\Task;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vyse\Installer\Data\Config;
use Vyse\Installer\Task\CacheTask;

class CacheTaskTest extends TestCase
{
    private MockObject & IOInterface $ioStub;
    private CacheTask $task;
    private string $tempDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->ioStub = $this->createMock(IOInterface::class);
        $this->task = new CacheTask();
        $this->fs = new Filesystem();

        $this->tempDir = sys_get_temp_dir() . '/vyse_cache_task_test_' . uniqid('', true);
        $this->fs->ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->fs->removeDirectory($this->tempDir);
    }

    public function testItDoesNothingIfCacheIsNotRequired(): void
    {
        $config = new Config();

        $this->ioStub->expects(self::never())->method('write');

        ($this->task)($config, $this->ioStub, $this->tempDir);

        self::assertDirectoryDoesNotExist($this->tempDir . '/.cache');
    }

    public function testItCreatesCacheDirectoryAndGitignoreIfRequired(): void
    {
        $config = (new Config())->enableCache();

        $this->ioStub->expects(self::once())
            ->method('write')
            ->with('<info>[Vyse] Initialized .cache directory.</info>')
        ;

        ($this->task)($config, $this->ioStub, $this->tempDir);

        $cacheDir = $this->tempDir . '/.cache';
        $gitIgnore = $cacheDir . '/.gitignore';

        self::assertDirectoryExists($cacheDir);
        self::assertFileExists($gitIgnore);
        self::assertSame("*\n!.gitignore\n", file_get_contents($gitIgnore));
    }

    public function testItDoesNotOverwriteGitignoreIfAlreadyCorrect(): void
    {
        $config = (new Config())->enableCache();
        $cacheDir = $this->tempDir . '/.cache';
        $gitIgnore = $cacheDir . '/.gitignore';

        $this->fs->ensureDirectoryExists($cacheDir);
        file_put_contents($gitIgnore, "*\n!.gitignore\n");

        touch($gitIgnore, time() - 3600);
        $mtime = filemtime($gitIgnore);

        ($this->task)($config, $this->ioStub, $this->tempDir);

        self::assertSame($mtime, filemtime($gitIgnore), 'The file should not have been modified');
    }

    public function testItUpdatesGitignoreIfContentIsIncorrect(): void
    {
        $config = (new Config())->enableCache();
        $cacheDir = $this->tempDir . '/.cache';
        $gitIgnore = $cacheDir . '/.gitignore';

        $this->fs->ensureDirectoryExists($cacheDir);
        file_put_contents($gitIgnore, "wrong content");

        ($this->task)($config, $this->ioStub, $this->tempDir);

        self::assertSame("*\n!.gitignore\n", file_get_contents($gitIgnore));
    }
}
