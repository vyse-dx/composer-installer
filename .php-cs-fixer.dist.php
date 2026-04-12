<?php

use PhpCsFixer\Finder;
use Vyse\Toolchain\PhpCsFixer\Config;

require __DIR__ . '/vendor/autoload.php';

$finder = (new Finder)
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/test',
    ])
;

$config = (new Config)
    ->setCacheFile(__DIR__ . '/.cache/php-cs-fixer')
;
$config->setFinder($finder);

return $config;
