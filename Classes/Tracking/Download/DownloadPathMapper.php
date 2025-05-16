<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Tracking\Download;

use TYPO3\CMS\Extbase\Security\Cryptography\HashService;
use TYPO3\CMS\Extbase\Security\Exception\InvalidArgumentForHashGenerationException;
use TYPO3\CMS\Extbase\Security\Exception\InvalidHashException;

final class DownloadPathMapper
{
    private const PATH_PREFIX = '/-/matomo/download';
    private const PATH_PREFIX_LENGTH = 18;

    public function __construct(
        private readonly HashService $hashService,
    ) {}

    public function toDownloadPath(string $filePath): string
    {
        $filePathWithHmac = $this->hashService->appendHmac($filePath);

        return sprintf('%s%s', self::PATH_PREFIX, $filePathWithHmac);
    }

    public function acceptsDownloadPath(string $downloadPath): bool
    {
        return str_starts_with($downloadPath, self::PATH_PREFIX);
    }

    /**
     * @throws InvalidDownloadPathException
     */
    public function toFilePath(string $downloadPath): string
    {
        $filePathWithHmac = substr($downloadPath, self::PATH_PREFIX_LENGTH);

        try {
            $filePath = $this->hashService->validateAndStripHmac($filePathWithHmac);
        } catch (InvalidHashException|InvalidArgumentForHashGenerationException $e) {
            throw new InvalidDownloadPathException(sprintf('Invalid download path "%s": %s', $downloadPath, $e->getMessage()), 1744785702, $e);
        }

        return $filePath;
    }
}
