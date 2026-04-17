<?php

declare(strict_types=1);

namespace Vyse\Installer\Data;

class Config
{
    /** @var array<string> */
    private array $binDirs = [];

    /** @var array<string, array<string, string>> */
    private array $hooks = [];

    public function addBinDir(
        string $dir,
    ): self {
        $this->binDirs[] = $dir;

        return $this;
    }

    public function addHook(
        string $event,
        string $name,
        string $script,
    ): self {
        if (!isset($this->hooks[$event])) {
            $this->hooks[$event] = [];
        }

        $this->hooks[$event][$name] = $script;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getBinDirs(): array
    {
        return $this->binDirs;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getHooks(): array
    {
        return $this->hooks;
    }

    public function hasBinDirs(): bool
    {
        return $this->binDirs !== [];
    }

    public function hasHooks(): bool
    {
        return $this->hooks !== [];
    }
}
