<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Middleware;

use Pagemachine\MatomoTracking\Matomo;
use Pagemachine\MatomoTracking\Tracking\Attributes\Download;
use Pagemachine\MatomoTracking\Tracking\Download\DownloadPathMapper;
use Pagemachine\MatomoTracking\Tracking\Download\InvalidDownloadPathException;
use Pagemachine\MatomoTracking\Tracking\TrackingException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class TrackDownload implements MiddlewareInterface
{
    public function __construct(
        private readonly bool $enabled,
        private readonly DownloadPathMapper $downloadPathMapper,
        private readonly Matomo $matomo,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $downloadPath = $request->getUri()->getPath();

        if (!$this->downloadPathMapper->acceptsDownloadPath($downloadPath)) {
            return $handler->handle($request);
        }

        try {
            $filePath = $this->downloadPathMapper->toFilePath($downloadPath);
        } catch (InvalidDownloadPathException $e) {
            $this->logger->warning($e->getMessage(), [
                'exception' => $e,
            ]);

            return $handler->handle($request);
        }

        $fileUri = (string)$request->getAttribute('site')->getBase()->withPath($filePath);
        $request = $request->withAttribute('matomo.attributes', [
            new Download($fileUri),
        ]);

        try {
            $this->matomo->track($request);
        } catch (TrackingException $e) {
            $this->logger->critical($e->getMessage(), [
                'exception' => $e,
            ]);
        }

        return $this->responseFactory->createResponse(307)
            ->withHeader('location', $fileUri);
    }
}
