<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Page;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;

final class PageTitleResolver
{
    public function __construct(
        private readonly PageTitleProviderManager $titleProvider,
    ) {}

    public function resolvePageTitle(ServerRequestInterface $request): string
    {
        if ((new Typo3Version())->getMajorVersion() < 14) {
            $controller = $request->getAttribute('frontend.controller');

            if (!empty($controller->config['config']['pageTitleCache'])) { // @phpstan-ignore class.notFound
                $this->titleProvider->setPageTitleCache($controller->config['config']['pageTitleCache']); // @phpstan-ignore class.notFound
            }
        } else {
            $pageParts = $request->getAttribute('frontend.page.parts'); // @phpstan-ignore phpstanTypo3.requestAttributeValidation
            $this->titleProvider->setPageTitleCache($pageParts->getPageTitle());
        }

        return $this->titleProvider->getTitle($request);
    }
}
