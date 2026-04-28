<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Tracking\Download;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Extbase\Security\Exception\InvalidArgumentForHashGenerationException;
use TYPO3\CMS\Extbase\Security\Exception\InvalidHashException;

final readonly class DownloadPathMapper
{
    private const PATH_PREFIX = '/-/matomo/download';
    private const PATH_PREFIX_LENGTH = 18;
    private const HASH_SCOPE = self::class;

    public function __construct(
        private HashService $hashService,
    ) {}

    public function toDownloadPath(string $filePath): string
    {
        $filePathWithHmac = $this->hashService->appendHmac($filePath, self::HASH_SCOPE);

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
            $filePath = $this->hashService->validateAndStripHmac($filePathWithHmac, self::HASH_SCOPE);
        } catch (InvalidHashException|InvalidArgumentForHashGenerationException $e) {
            throw new InvalidDownloadPathException(sprintf('Invalid download path "%s": %s', $downloadPath, $e->getMessage()), 1744785702, $e);
        }

        return $filePath;
    }
}
