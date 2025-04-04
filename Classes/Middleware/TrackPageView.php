<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Middleware;

use Pagemachine\MatomoTracking\Matomo;
use Pagemachine\MatomoTracking\Tracking\Attributes\ActionName;
use Pagemachine\MatomoTracking\Tracking\TrackingException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Page\PageRenderer;

final class TrackPageView implements MiddlewareInterface
{
    public function __construct(
        private readonly Matomo $matomo,
        private readonly PageRenderer $pageRenderer,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        try {
            $this->matomo->track($request->withAttribute('matomo.attributes', [
                new ActionName($this->pageRenderer->getTitle() ?: ''),
            ]));
        } catch (TrackingException $e) {
            $this->logger->critical($e->getMessage(), [
                'exception' => $e,
            ]);
        }

        return $response;
    }
}
