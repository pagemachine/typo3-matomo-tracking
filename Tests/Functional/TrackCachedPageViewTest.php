<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Tests\Functional;

use donatj\MockWebServer\MockWebServer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TrackCachedPageViewTest extends FunctionalTestCase
{
    private MockWebServer $mockMatomoServer;

    protected array $testExtensionsToLoad = [
        'pagemachine/typo3-matomo-tracking',
    ];

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'pages' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
    ];

    public function setUp(): void
    {
        parent::setUp();

        $connection = $this->getConnectionPool()->getConnectionByName('Default');

        $connection->insert('pages', [
            'uid' => 1,
            'pid' => 0,
            'title' => 'Example Root Page',
        ]);
        $this->setUpFrontendRootPage(1, [
            'EXT:matomo_tracking/Tests/Functional/Fixtures/TypoScript/page.typoscript',
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
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->mockMatomoServer->stop();
    }

    #[Test]
    public function sendPageViewToMatomo(): void
    {
        $firstResponse = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $firstResponse->getStatusCode());

        $firstMatomoRequest = $this->mockMatomoServer->getLastRequest();

        self::assertNotNull($firstMatomoRequest);

        $firstParameters = $firstMatomoRequest->getPost();

        self::assertNotEmpty($firstParameters);

        self::assertArrayHasKey(array: $firstParameters, key: 'idsite');
        self::assertSame('1', $firstParameters['idsite']);
        self::assertArrayHasKey(array: $firstParameters, key: 'url');
        self::assertSame('http://localhost/?id=1', $firstParameters['url']);
        self::assertArrayHasKey(array: $firstParameters, key: 'action_name');
        self::assertSame('Example Root Page', $firstParameters['action_name'], 'Empty generated page name');

        $secondResponse = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $secondResponse->getStatusCode());

        $secondMatomoRequest = $this->mockMatomoServer->getLastRequest();

        self::assertNotNull($secondMatomoRequest);
        self::assertNotSame($firstMatomoRequest, $secondMatomoRequest);

        $secondParameters = $secondMatomoRequest->getPost();

        self::assertNotEmpty($secondParameters);
        self::assertArrayHasKey(array: $secondParameters, key: 'idsite');
        self::assertSame('1', $secondParameters['idsite']);
        self::assertArrayHasKey(array: $secondParameters, key: 'url');
        self::assertSame('http://localhost/?id=1', $secondParameters['url']);
        self::assertArrayHasKey(array: $secondParameters, key: 'action_name');
        self::assertSame('Example Root Page', $secondParameters['action_name'], 'Empty cached page name');
    }
}
