<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/Classes',
        __DIR__ . '/Tests',
    ])
    ->withRootFiles()
    ->withPhpSets()
    ->withDowngradeSets(
        php81: true,
    )
    ->withSets([
        PHPUnitSetList::PHPUNIT_100,
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ])
;
