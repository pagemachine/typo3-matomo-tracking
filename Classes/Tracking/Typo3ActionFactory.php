<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Tracking;

use Pagemachine\MatomoTracking\Tracking\Attributes\AuthToken;
use Pagemachine\MatomoTracking\Tracking\Attributes\SiteId;
use Pagemachine\MatomoTracking\Tracking\Attributes\Url;
use Pagemachine\MatomoTracking\Tracking\Attributes\VisitorIpAddress;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Create various attributes from TYPO3-specific info
 */
final class Typo3ActionFactory implements ActionFactoryInterface
{
    public function __construct(
        private readonly ActionFactoryInterface $decorated,
        #[\SensitiveParameter]
        private readonly string $authToken,
    ) {}

    public function createAction(): ActionInterface
    {
        $action = $this->decorated->createAction();

        if (!empty($this->authToken)) {
            $action = $action->withAttribute(new AuthToken($this->authToken));
        }

        return $action;
    }

    public function createActionFromRequest(ServerRequestInterface $serverRequest): ActionInterface
    {
        $action = $this->decorated->createActionFromRequest($serverRequest)
            ->withAttribute(new Url((string)$this->getRequestUri($serverRequest)));

        $siteId = $serverRequest->getAttribute('site')?->getConfiguration()['matomoTrackingSiteId'] ?? null;

        if (!empty($siteId)) {
            $action = $action->withAttribute(new SiteId($siteId));
        }

        if (!empty($this->authToken)) {
            $action = $action->withAttribute(new AuthToken($this->authToken));

            $normalizedParams = $serverRequest->getAttribute('normalizedParams');

            if ($normalizedParams !== null) {
                $action = $action->withAttribute(new VisitorIpAddress($normalizedParams->getRemoteAddress()));
            }
        }

        return $action;
    }

    private function getRequestUri(ServerRequestInterface $serverRequest): UriInterface
    {
        return $serverRequest->getAttribute('originalRequest')?->getUri() ?? $serverRequest->getUri();
    }
}
