<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Page;

use Pagemachine\MatomoTracking\Html\DocumentTitleExtractor;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Page\PageRenderer;

final class PageTitleResolver
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly DocumentTitleExtractor $documentTitleExtractor,
    ) {}

    public function resolvePageTitle(ServerRequestInterface $request, ResponseInterface $response): string
    {
        $controller = $request->getAttribute('frontend.controller');
        $pageTitle = '';

        if ($controller->isGeneratePage()) {
            $pageTitle = $this->pageRenderer->getTitle() ?: '';
        } else {
            $pageTitle = $this->documentTitleExtractor->extractFromSource((string)$response->getBody());
        }

        return $pageTitle;
    }
}
