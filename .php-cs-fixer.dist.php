<?php

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
/** @var \PhpCsFixer\Finder $finder */
$finder = $config->getFinder();
$finder
    ->in(__DIR__)
    ->exclude([
        'web',
    ])
;

return $config;
