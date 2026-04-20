<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Page;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;

final class PageTitleResolver
{
    public function __construct(
        private readonly PageTitleProviderManager $titleProvider,
    ) {}

    public function resolvePageTitle(ServerRequestInterface $request): string
    {
        $controller = $request->getAttribute('frontend.controller');

        if (!empty($controller->config['config']['pageTitleCache'])) {
            $this->titleProvider->setPageTitleCache($controller->config['config']['pageTitleCache']);
        }

        return $this->titleProvider->getTitle($request);
    }
}
