<?php

declare(strict_types=1);

namespace Test\Integration;

use Composer\Composer;
use Composer\Config as ComposerConfig;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Util\Filesystem;
use Vyse\Installer\Plugin;
use Vyse\Toolchain\PhpUnit\TestCase\IntegrationTestCase;

class PluginIntegrationTest extends IntegrationTestCase
{
    private string $tempRoot;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tempRoot = sys_get_temp_dir() . '/vyse_integration_' . uniqid();

        // Setup a fake project structure
        mkdir($this->tempRoot);
        mkdir($this->tempRoot . '/vendor', 0777, true);
        mkdir($this->tempRoot . '/.git/hooks', 0777, true);

        // Setup a fake dependency toolchain package
        $toolchainBinDir = $this->tempRoot . '/vendor/vyse/toolchain/bin';
        mkdir($toolchainBinDir, 0777, true);
        file_put_contents($toolchainBinDir . '/style', 'echo "running style checks"');
    }

    protected function tearDown(): void
    {
        $this->fs->removeDirectory($this->tempRoot);
    }

    public function testFullExecutionPipelineWithRealTasksAndParser(): void
    {
        // 1. Stub the Composer Environment
        $ioMock = $this->createMock(IOInterface::class);
        $composerMock = $this->createMock(Composer::class);

        $configMock = $this->createMock(ComposerConfig::class);
        $configMock->expects(self::exactly(2))
            ->method('get')
            ->with('vendor-dir')
            ->willReturn($this->tempRoot . '/vendor')
        ;
        $composerMock->method('getConfig')->willReturn($configMock);

        // Fake dependency package supplying the binary directory
        $depPackage = $this->createMock(PackageInterface::class);
        $depPackage->method('getExtra')->willReturn([
            'vyse' => ['bin' => 'bin'] // Maps to /vendor/vyse/toolchain/bin
        ]);

        // Fake root project supplying the hook configuration
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([
            'vyse' => [
                'hooks' => [
                    'pre-commit' => [
                        '00-style' => './vyse/style'
                    ]
                ]
            ]
        ]);
        $composerMock->method('getPackage')->willReturn($rootPackage);

        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('getPackages')->willReturn([$depPackage]);

        $repoManager = $this->createMock(RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);
        $composerMock->method('getRepositoryManager')->willReturn($repoManager);

        $installManager = $this->createMock(InstallationManager::class);
        $installManager->method('getInstallPath')->willReturnMap([
            [$depPackage, $this->tempRoot . '/vendor/vyse/toolchain']
        ]);
        $composerMock->method('getInstallationManager')->willReturn($installManager);

        $eventMock = $this->createMock(Event::class);
        $eventMock->method('getComposer')->willReturn($composerMock);
        $eventMock->method('getIO')->willReturn($ioMock);

        // 2. Auto-wire the REAL Plugin (and all underlying real Tasks/Parser)
        $plugin = $this->make(Plugin::class);

        // 3. Execute the pipeline
        $plugin->executePipeline($eventMock);

        // 4. Assert Real File System Changes

        // A. BinTask Success: Script was symlinked to the project root
        $targetLink = $this->tempRoot . '/vyse/style';
        self::assertTrue(is_link($targetLink), 'Style script should be symlinked into the root vyse directory.');
        self::assertFileExists($targetLink, 'The symlink is broken and does not resolve to the target.');

        $linkTarget = readlink($targetLink);
        assert(is_string($linkTarget));
        self::assertStringContainsString('vendor/vyse/toolchain/bin/style', str_replace('\\', '/', $linkTarget));

        // B. HookRunnerTask Success: Runner script was compiled
        $runnerPath = $this->tempRoot . '/vyse/hooks/pre-commit';
        self::assertFileExists($runnerPath, 'Runner script should be compiled.');

        $runnerContent = file_get_contents($runnerPath);
        assert(is_string($runnerContent));
        self::assertStringContainsString('./vyse/style', $runnerContent);
        self::assertSame('0755', substr(sprintf('%o', fileperms($runnerPath)), -4));

        // C. GitProxyTask Success: Git hook was installed
        $proxyPath = $this->tempRoot . '/.git/hooks/pre-commit';
        self::assertFileExists($proxyPath, 'Proxy script should be installed in .git/hooks.');

        $proxyContent = file_get_contents($proxyPath);
        assert(is_string($proxyContent));
        self::assertStringContainsString('exec "./vyse/hooks/pre-commit"', $proxyContent);
        self::assertSame('0755', substr(sprintf('%o', fileperms($proxyPath)), -4));
    }
}
