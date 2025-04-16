<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\TYPO313\v0\MigrateExtbaseHashServiceToUseCoreHashServiceRector;

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
    ->withSkip([
        MigrateExtbaseHashServiceToUseCoreHashServiceRector::class,
        RenameClassRector::class => [
            'Classes/Tracking/Download/DownloadPathMapper.php',
        ],
    ])
;
