<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\EventListener;

use Pagemachine\MatomoTracking\Tracking\Download\DownloadPathMapper;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent;

final class MapDownloadPath
{
    public function __construct(
        private readonly bool $enabled,
        private readonly DownloadPathMapper $downloadPathMapper,
    ) {}

    public function __invoke(AfterLinkIsGeneratedEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $linkResult = $event->getLinkResult();

        if ($linkResult->getType() !== LinkService::TYPE_FILE) {
            return;
        }

        $downloadPath = $this->downloadPathMapper->toDownloadPath($linkResult->getUrl());

        $event->setLinkResult($linkResult->withAttribute('href', $downloadPath));
    }
}
