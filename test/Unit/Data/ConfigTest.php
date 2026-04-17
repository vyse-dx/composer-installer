<?php

declare(strict_types=1);

namespace Test\Data;

use PHPUnit\Framework\TestCase;
use Vyse\Installer\Data\Config;

class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config();
    }

    public function testItInitializesEmpty(): void
    {
        self::assertFalse($this->config->hasBinDirs());
        self::assertFalse($this->config->hasHooks());

        self::assertSame([], $this->config->getBinDirs());
        self::assertSame([], $this->config->getHooks());
    }

    public function testItAddsBinDirectoriesFluently(): void
    {
        $result = $this->config->addBinDir('/path/to/bin1')
                               ->addBinDir('/path/to/bin2')
        ;

        self::assertSame($this->config, $result);
        self::assertTrue($this->config->hasBinDirs());
        self::assertSame([
            '/path/to/bin1',
            '/path/to/bin2',
        ], $this->config->getBinDirs());
    }

    public function testItAddsHooksFluentlyAndGroupsByEvent(): void
    {
        $result = $this->config->addHook('pre-commit', '00-style', './vyse/style/check')
                               ->addHook('pre-commit', '01-stan', './vyse/stan/check')
                               ->addHook('pre-push', '00-test', './vyse/test/run')
        ;

        self::assertSame($this->config, $result);
        self::assertTrue($this->config->hasHooks());

        $expectedHooks = [
            'pre-commit' => [
                '00-style' => './vyse/style/check',
                '01-stan' => './vyse/stan/check',
            ],
            'pre-push' => [
                '00-test' => './vyse/test/run',
            ],
        ];

        self::assertSame($expectedHooks, $this->config->getHooks());
    }

    public function testItOverwritesHooksWithTheSameEventAndName(): void
    {
        $this->config->addHook('pre-commit', '00-style', './vyse/style/check')
                     ->addHook('pre-commit', '00-style', './vyse/alternate/check')
        ;

        $expectedHooks = [
            'pre-commit' => [
                '00-style' => './vyse/alternate/check',
            ],
        ];

        self::assertSame($expectedHooks, $this->config->getHooks());
    }
}
