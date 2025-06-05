<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Html;

/**
 * Extracts the <title> element content from an HTML document
 */
final class DocumentTitleExtractor
{
    public function extractFromSource(string $source): string
    {
        $document = new \DOMDocument();
        $document->loadHTML($source, \LIBXML_NOERROR);
        $titleElement = $document->getElementsByTagName('title')->item(0);

        return $titleElement->textContent ?? '';
    }
}
