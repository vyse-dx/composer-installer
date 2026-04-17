<?php

declare(strict_types=1);

namespace Vyse\Installer\Interface;

use Composer\IO\IOInterface;
use Vyse\Installer\Data\Config;

interface TaskInterface
{
    public function __invoke(
        Config $config,
        IOInterface $io,
        string $projectRoot,
    ): void;
}
