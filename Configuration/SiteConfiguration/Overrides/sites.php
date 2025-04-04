<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ArrayUtility;

ArrayUtility::mergeRecursiveWithOverrule($GLOBALS['SiteConfiguration']['site']['columns'], [
    'matomoTrackingSiteId' => [
        'label' => 'Matomo Site ID',
        'description' => 'See https://matomo.org/faq/general/faq_19212/',
        'config' => [
            'type' => 'input',
            'size' => 10,
            'eval' => 'trim',
        ],
    ],
]);
$GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] .= implode(
    array: [
        '',
        '--div--;Matomo Tracking',
        'matomoTrackingSiteId',
    ],
    separator: ',',
);
