<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Tests\Functional;

use donatj\MockWebServer\MockWebServer;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TrackDownloadTest extends FunctionalTestCase
{
    private MockWebServer $mockMatomoServer;

    protected array $testExtensionsToLoad = [
        'pagemachine/typo3-matomo-tracking',
    ];

    protected array $pathsToLinkInTestInstance = [
        // See https://pdfa.org/the-smallest-possible-valid-pdf/
        'typo3conf/ext/matomo_tracking/Tests/Functional/Fixtures/Files/smallest-possible-pdf-1.0.pdf' => 'fileadmin/smallest-possible-pdf-1.0.pdf',
    ];

    public function setUp(): void
    {
        parent::setUp();

        $connection = $this->getConnectionPool()->getConnectionByName('Default');

        $connection->insert('pages', [
            'uid' => 1,
            'pid' => 0,
            'title' => 'Example Root Page',
            'slug' => '/',
        ]);
        $connection->insert('pages', [
            'uid' => 2,
            'pid' => 1,
            'title' => 'Example Page',
            'slug' => '/example-page/',
        ]);
        $this->setUpFrontendRootPage(1, [
            'EXT:matomo_tracking/Tests/Functional/Fixtures/TypoScript/download.typoscript',
        ]);

        $siteConfiguration = $this->get(SiteConfiguration::class);

        if ((new Typo3Version())->getMajorVersion() < 13) {
            $siteConfiguration->createNewBasicSite('1', 1, 'http://localhost/');
            $siteConfiguration->write('1', [
                ...$siteConfiguration->load('1'),
                ...[
                    'matomoTrackingSiteId' => '1',
                ],
            ]);
        } else {
            $siteWriter = $this->get(SiteWriter::class);
            $siteWriter->createNewBasicSite('1', 1, 'http://localhost/');
            $siteWriter->write('1', [
                ...$siteConfiguration->load('1'),
                ...[
                    'matomoTrackingSiteId' => '1',
                ],
            ]);
        }

        $this->mockMatomoServer = new MockWebServer();
        $this->mockMatomoServer->start();

        $this->get(ExtensionConfiguration::class)->set('matomo_tracking', [
            'matomoUrl' => $this->mockMatomoServer->getServerRoot(),
            'features' => [
                'downloadTracking' => '1',
            ],
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->mockMatomoServer->stop();
    }

    #[Test]
    public function mapFileLinks(): void
    {
        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $response->getStatusCode());

        $links = $this->getLinksFromResponse($response);

        $fileLink = $links->item(0);

        self::assertNotNull($fileLink);
        self::assertStringStartsWith('/-/matomo/download/fileadmin/smallest-possible-pdf-1.0.pdf', $fileLink->getAttribute('href'));
    }

    #[Test]
    public function skipNonFileLinks(): void
    {
        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $response->getStatusCode());

        $links = $this->getLinksFromResponse($response);

        $pageLink = $links->item(1);

        self::assertNotNull($pageLink);
        self::assertEquals('/example-page/', $pageLink->getAttribute('href'));

        $emailLink = $links->item(2);

        self::assertNotNull($emailLink);
        self::assertEquals('mailto:user@example.org', $emailLink->getAttribute('href'));

        $externalLink = $links->item(3);

        self::assertNotNull($externalLink);
        self::assertEquals('https://example.org', $externalLink->getAttribute('href'));
    }

    #[Test]
    public function skipFileLinksWithDisabledFeature(): void
    {
        $this->get(ExtensionConfiguration::class)->set('matomo_tracking', [
            ...$this->get(ExtensionConfiguration::class)->get('matomo_tracking'),
            'features' => [
                'downloadTracking' => '0',
            ],
        ]);

        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $response->getStatusCode());

        $links = $this->getLinksFromResponse($response);

        $fileLink = $links->item(0);

        self::assertNotNull($fileLink);
        self::assertStringStartsWith('/fileadmin/smallest-possible-pdf-1.0.pdf', $fileLink->getAttribute('href'));
    }

    #[Test]
    public function sendDownloadToMatomo(): void
    {
        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $response->getStatusCode());

        $fileLink = $this->getLinksFromResponse($response)->item(0);

        $this->executeFrontendSubRequest(new InternalRequest('http://localhost' . $fileLink->getAttribute('href')));

        $matomoRequest = $this->mockMatomoServer->getLastRequest();

        self::assertNotNull($matomoRequest);

        $parameters = $matomoRequest->getPost();

        self::assertNotEmpty($parameters);

        self::assertArrayHasKey(array: $parameters, key: 'idsite');
        self::assertSame('1', $parameters['idsite']);
        self::assertArrayHasKey(array: $parameters, key: 'url');
        self::assertSame('http://localhost/fileadmin/smallest-possible-pdf-1.0.pdf', $parameters['url']);
        self::assertArrayHasKey(array: $parameters, key: 'download');
        self::assertSame('http://localhost/fileadmin/smallest-possible-pdf-1.0.pdf', $parameters['download']);
        self::assertArrayHasKey(array: $parameters, key: 'ca');
        self::assertSame('1', $parameters['ca']);
    }

    #[Test]
    public function redirectToFile(): void
    {
        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $response->getStatusCode());

        $fileLink = $this->getLinksFromResponse($response)->item(0);

        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost' . $fileLink->getAttribute('href')));

        self::assertSame(307, $response->getStatusCode());
        self::assertSame('http://localhost/fileadmin/smallest-possible-pdf-1.0.pdf', $response->getHeaderLine('location'));
    }

    #[Test]
    public function redirectToFileWithoutSite(): void
    {
        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $response->getStatusCode());

        $fileLink = $this->getLinksFromResponse($response)->item(0);

        $response = $this->executeFrontendSubRequest(new InternalRequest('http://unknown.localhost' . $fileLink->getAttribute('href')));

        self::assertSame(307, $response->getStatusCode());
        self::assertSame('/fileadmin/smallest-possible-pdf-1.0.pdf', $response->getHeaderLine('location'));
    }

    #[Test]
    public function trackDownloadWithDoNotTrackDisabled(): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))
                ->withPageId(1)
                ->withHeader('DNT', '0')
        );

        self::assertSame(200, $response->getStatusCode());

        $fileLink = $this->getLinksFromResponse($response)->item(0);

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost' . $fileLink->getAttribute('href')))
                ->withHeader('DNT', '0')
        );

        self::assertNotEmpty($this->mockMatomoServer->getLastRequest());
    }

    #[Test]
    public function skipDownloadWithDoNotTrackEnabled(): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))
                ->withPageId(1)
                ->withHeader('DNT', '1')
        );

        self::assertSame(200, $response->getStatusCode());

        $fileLink = $this->getLinksFromResponse($response)->item(0);

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost' . $fileLink->getAttribute('href')))
                ->withHeader('DNT', '1')
        );

        self::assertEmpty($this->mockMatomoServer->getLastRequest());
    }

    #[Test]
    public function skipDownloadWithGlobalPrivacyControlEnabled(): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))
                ->withPageId(1)
                ->withHeader('Sec-GPC', '1')
        );

        self::assertSame(200, $response->getStatusCode());

        $fileLink = $this->getLinksFromResponse($response)->item(0);

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost' . $fileLink->getAttribute('href')))
                ->withHeader('Sec-GPC', '1')
        );

        self::assertEmpty($this->mockMatomoServer->getLastRequest());
    }

    private function getLinksFromResponse(ResponseInterface $response): \DOMNodeList
    {
        $document = new \DOMDocument();
        $document->loadHTML((string)$response->getBody());
        $xpath = new \DOMXPath($document);

        return $xpath->query('//a');
    }
}
