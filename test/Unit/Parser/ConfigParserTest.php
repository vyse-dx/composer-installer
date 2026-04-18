<?php

declare(strict_types=1);

namespace Test\Parser;

use Composer\Composer;
use Composer\Config as ComposerConfig;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vyse\Installer\Parser\ConfigParser;

class ConfigParserTest extends TestCase
{
    private MockObject & Composer $composerStub;
    private MockObject & InstalledRepositoryInterface $localRepoStub;
    private MockObject & InstallationManager $installManagerStub;
    private ConfigParser $parser;

    protected function setUp(): void
    {
        $this->composerStub = $this->createMock(Composer::class);
        $this->localRepoStub = $this->createMock(InstalledRepositoryInterface::class);
        $this->installManagerStub = $this->createMock(InstallationManager::class);
        $this->parser = new ConfigParser();

        $configStub = $this->createMock(ComposerConfig::class);
        $configStub->method('get')->willReturn('/var/www/html/vendor');
        $this->composerStub->method('getConfig')->willReturn($configStub);

        $repoManagerStub = $this->createMock(RepositoryManager::class);
        $repoManagerStub->method('getLocalRepository')->willReturn($this->localRepoStub);
        $this->composerStub->method('getRepositoryManager')->willReturn($repoManagerStub);

        $this->composerStub->method('getInstallationManager')->willReturn($this->installManagerStub);
    }

    public function testItReturnsEmptyConfigWhenNoPackagesHaveVyseConfig(): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([]);

        $this->localRepoStub->method('getPackages')->willReturn([]);
        $this->composerStub->method('getPackage')->willReturn($rootPackage);

        $config = ($this->parser)($this->composerStub);

        self::assertFalse($config->hasBinDirs());
        self::assertFalse($config->hasHooks());
        self::assertFalse($config->requiresCache());
    }

    public function testItParsesBinDirectoriesFromDependenciesAndRootProject(): void
    {
        $depPackage = $this->createMock(PackageInterface::class);
        $depPackage->method('getExtra')->willReturn([
            'vyse' => ['bin' => 'src/scripts']
        ]);

        $this->localRepoStub->method('getPackages')->willReturn([$depPackage]);

        $this->installManagerStub->method('getInstallPath')
            ->willReturnMap([
                [$depPackage, '/var/www/html/vendor/vyse/toolchain']
            ])
        ;

        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([
            'vyse' => ['bin' => '/root-bin']
        ]);

        $this->composerStub->method('getPackage')->willReturn($rootPackage);

        $config = ($this->parser)($this->composerStub);

        self::assertTrue($config->hasBinDirs());
        self::assertSame([
            '/var/www/html/vendor/vyse/toolchain/src/scripts',
            '/var/www/html/root-bin',
        ], $config->getBinDirs());
    }

    public function testItParsesAndMergesHooksSafely(): void
    {
        $depPackage = $this->createMock(PackageInterface::class);
        $depPackage->method('getExtra')->willReturn([
            'vyse' => [
                'hooks' => [
                    'pre-commit' => [
                        '00-style' => './vendor/bin/style',
                    ]
                ]
            ]
        ]);

        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([
            'vyse' => [
                'hooks' => [
                    'pre-commit' => [
                        '01-stan' => './vendor/bin/stan',
                    ],
                    'pre-push' => [
                        '00-test' => './vendor/bin/test',
                    ]
                ]
            ]
        ]);

        $this->localRepoStub->method('getPackages')->willReturn([$depPackage]);
        $this->composerStub->method('getPackage')->willReturn($rootPackage);

        $config = ($this->parser)($this->composerStub);

        self::assertTrue($config->hasHooks());
        self::assertSame([
            'pre-commit' => [
                '00-style' => './vendor/bin/style',
                '01-stan' => './vendor/bin/stan',
            ],
            'pre-push' => [
                '00-test' => './vendor/bin/test',
            ]
        ], $config->getHooks());
    }

    public function testItParsesCacheRequirement(): void
    {
        $depPackage = $this->createMock(PackageInterface::class);
        $depPackage->method('getExtra')->willReturn([
            'vyse' => ['cache' => true]
        ]);

        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([]);

        $this->localRepoStub->method('getPackages')->willReturn([$depPackage]);
        $this->composerStub->method('getPackage')->willReturn($rootPackage);

        $config = ($this->parser)($this->composerStub);

        self::assertTrue($config->requiresCache());
    }

    public function testItIgnoresMalformedVyseConfigurations(): void
    {
        $badPackage = $this->createMock(PackageInterface::class);
        $badPackage->method('getExtra')->willReturn([
            'vyse' => [
                'cache' => 'true', // invalid type
                'bin' => ['this-should-be-a-string'],
                'hooks' => [
                    'pre-commit' => 'this-should-be-an-array',
                    'pre-push' => [
                        '00-test' => ['this-should-be-a-string-script']
                    ]
                ]
            ]
        ]);

        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([]);

        $this->localRepoStub->method('getPackages')->willReturn([$badPackage]);
        $this->composerStub->method('getPackage')->willReturn($rootPackage);

        $config = ($this->parser)($this->composerStub);

        self::assertFalse($config->hasBinDirs());
        self::assertFalse($config->hasHooks());
        self::assertFalse($config->requiresCache());
    }
}
