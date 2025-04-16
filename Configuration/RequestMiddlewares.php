<?php

declare(strict_types=1);

use Pagemachine\MatomoTracking\Middleware\TrackDownload;
use Pagemachine\MatomoTracking\Middleware\TrackPageView;

return [
    'frontend' => [
        'pagemachine/typo3-matomo-tracking/track-page-view' => [
            'target' => TrackPageView::class,
            'after' => [
                'typo3/cms-frontend/site',
                'typo3/cms-core/normalized-params-attribute',
            ],
        ],
        'pagemachine/typo3-matomo-tracking/track-download' => [
            'target' => TrackDownload::class,
            'before' => [
                'typo3/cms-frontend/static-route-resolver',
            ],
            'after' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];
