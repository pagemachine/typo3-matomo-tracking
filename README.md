# TYPO3 Matomo Tracking

Server-side tracking of TYPO3 events (e.g. page views) in [Matomo](https://matomo.org).

## Installation

    composer require pagemachine/typo3-matomo-tracking

## Setup

1. Fill the `matomo_tracking` Extension Configuration:
   * Matomo instance URL (`matomoUrl`), e.g. https://matomo.example.com/
   * Auth Token (`authToken`) for authenticated tracking
2. Set the Matomo site ID (`matomoTrackingSiteId`) in the settings of
   each TYPO3 site

It is recommended to [configure a sane HTTP timeout](https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/Configuration/Typo3ConfVars/HTTP.html#confval-globals-typo3-conf-vars-sys-http-timeout)
like 3 seconds. This ensures pages load quickly in case a Matomo instance is not
responding. Tracking will be skipped in this case.

## Page view tracking

By default page views will be tracked with the final page title and the current URL.
The previous URL (referrer) will also be tracked if possible.

## Download tracking

Clicks on file links can be tracked as downloads. This optional feature must be enabled
explicitly in the Extension Configuration through the _Track downloads_
(`features.downloadTracking`) option.

This will rewrite all file links to an internal handler which tracks the request
and redirects to the actual files. This should be tested well before using it in
production, especially in relation to other TYPO3 packages which adjust file URLs.

## Action/attribute adjustments

Actions are tracked in Matomo with suitable attributes. To adjust or override these,
you can add custom [action factories](https://github.com/pagemachine/matomo-tracking#action-factories).
