<?php

declare(strict_types=1);

require_once(__DIR__ . '/../vendor/autoload.php');

use Vyse\Toolchain\PhpUnit\Bypass\PhpUnitMutator;

PhpUnitMutator::enable();
