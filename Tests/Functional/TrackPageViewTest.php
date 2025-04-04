<?php

declare(strict_types=1);

namespace Pagemachine\MatomoTracking\Tests\Functional;

use donatj\MockWebServer\MockWebServer;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TrackPageViewTest extends FunctionalTestCase
{
    private MockWebServer|null $mockMatomoServer = null;

    protected array $testExtensionsToLoad = [
        'pagemachine/typo3-matomo-tracking',
    ];

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'reverseProxyIP' => '2.3.4.5',
            'reverseProxyHeaderMultiValue' => 'last',
        ],
    ];

    public function setUp(): void
    {
        if ((new Typo3Version())->getMajorVersion() === 11) {
            $this->configurationToUseInTestInstance['SYS']['features']['subrequestPageErrors'] = true;
        }

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

        if ((new Typo3Version())->getMajorVersion() < 13) {
            $siteConfiguration = $this->get(SiteConfiguration::class);
            $siteConfiguration->createNewBasicSite('1', 1, 'http://localhost/');
        } else {
            $siteWriter = $this->get(SiteWriter::class);
            $siteWriter->createNewBasicSite('1', 1, 'http://localhost/');
        }

        $this->mockMatomoServer = new MockWebServer();
        $this->mockMatomoServer->start();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->mockMatomoServer->stop();
    }

    #[Test]
    public function sendPageViewToMatomo(): void
    {
        $this->configureTracking();
        $this->configureMatomoSiteId();

        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $response->getStatusCode());

        $matomoRequest = $this->mockMatomoServer->getLastRequest();

        self::assertNotNull($matomoRequest);

        $parameters = $matomoRequest->getPost();

        self::assertNotEmpty($parameters);

        self::assertArrayHasKey(array: $parameters, key: 'idsite');
        self::assertSame('1', $parameters['idsite']);
        self::assertArrayHasKey(array: $parameters, key: 'url');
        self::assertSame('http://localhost/?id=1', $parameters['url']);
        self::assertArrayHasKey(array: $parameters, key: 'action_name');
        self::assertSame('Example Root Page', $parameters['action_name']);
    }

    #[Test]
    public function sendPageViewWithAuthTokenToMatomo(): void
    {
        $this->configureTracking(authToken: 'ceff8d439e32d680caa830c59337f7e5');
        $this->configureMatomoSiteId();

        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $response->getStatusCode());

        $matomoRequest = $this->mockMatomoServer->getLastRequest();

        self::assertNotNull($matomoRequest);

        self::assertEmpty($matomoRequest->getGet());

        $parameters = $matomoRequest->getPost();

        self::assertNotEmpty($parameters);

        self::assertArrayHasKey(array: $parameters, key: 'token_auth');
        self::assertSame('ceff8d439e32d680caa830c59337f7e5', $parameters['token_auth']);
    }

    #[Test]
    #[TestWith([
        [
            'REMOTE_ADDR' => '1.2.3.4',
        ],
    ])]
    #[TestWith([
        [
            'REMOTE_ADDR' => '2.3.4.5',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ],
    ])]
    #[RequiresMethod(InternalRequest::class, 'withServerParams')]
    public function sendPageViewWithVisitorIpAddressToMatomo(array $serverParams): void
    {
        $this->configureTracking(authToken: 'ceff8d439e32d680caa830c59337f7e5');
        $this->configureMatomoSiteId();

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))
                ->withPageId(1)
                ->withServerParams($serverParams)
        );

        self::assertSame(200, $response->getStatusCode());

        $matomoRequest = $this->mockMatomoServer->getLastRequest();

        self::assertNotNull($matomoRequest);

        self::assertEmpty($matomoRequest->getGet());

        $parameters = $matomoRequest->getPost();

        self::assertNotEmpty($parameters);

        self::assertArrayHasKey(array: $parameters, key: 'token_auth');
        self::assertSame('ceff8d439e32d680caa830c59337f7e5', $parameters['token_auth']);
        self::assertArrayHasKey(array: $parameters, key: 'cip');
        self::assertSame('1.2.3.4', $parameters['cip']);
    }

    #[Test]
    #[TestWith([
        [
            'REMOTE_ADDR' => '1.2.3.4',
        ],
    ])]
    #[TestWith([
        [
            'REMOTE_ADDR' => '2.3.4.5',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ],
    ])]
    #[RequiresMethod(InternalRequest::class, 'withServerParams')]
    public function sendPageViewWithoutVisitorIpAddressToMatomo(array $serverParams): void
    {
        $this->configureTracking();
        $this->configureMatomoSiteId();

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))
                ->withPageId(1)
                ->withServerParams($serverParams)
        );

        self::assertSame(200, $response->getStatusCode());

        $matomoRequest = $this->mockMatomoServer->getLastRequest();

        self::assertNotNull($matomoRequest);

        self::assertEmpty($matomoRequest->getGet());

        $parameters = $matomoRequest->getPost();

        self::assertNotEmpty($parameters);

        self::assertArrayNotHasKey(array: $parameters, key: 'token_auth');
        self::assertArrayNotHasKey(array: $parameters, key: 'cip');
    }

    #[Test]
    public function sendOriginalRequestUriOnErrorToMatomo(): void
    {
        $this->configureTracking();
        $this->configureMatomoSiteId();
        $siteConfiguration = $this->get(SiteConfiguration::class);

        $this->getConnectionPool()->getConnectionByName('Default')->insert('pages', [
            'uid' => 2,
            'pid' => 1,
            'title' => 'Example 404 Page',
            'slug' => '/404/',
        ]);

        if ((new Typo3Version())->getMajorVersion() < 13) {
            $siteConfiguration->write('1', [
                ...$siteConfiguration->load('1'),
                ...[
                    'errorHandling' => [
                        [
                            'errorCode' => 404,
                            'errorHandler' => 'Page',
                            'errorContentSource' => 't3://page?uid=2',
                        ],
                    ],
                ],
            ]);
        } else {
            $siteWriter = $this->get(SiteWriter::class);
            $siteWriter->write('1', [
                ...$siteConfiguration->load('1'),
                ...[
                    'errorHandling' => [
                        [
                            'errorCode' => 404,
                            'errorHandler' => 'Page',
                            'errorContentSource' => 't3://page?uid=2',
                        ],
                    ],
                ],
            ]);
        }

        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/test'));

        self::assertSame(404, $response->getStatusCode());

        $matomoRequest = $this->mockMatomoServer->getLastRequest();

        self::assertNotNull($matomoRequest);

        self::assertEmpty($matomoRequest->getGet());

        $parameters = $matomoRequest->getPost();

        self::assertNotEmpty($parameters);

        self::assertArrayHasKey(array: $parameters, key: 'url');
        self::assertSame('http://localhost/test', $parameters['url']);
    }

    #[Test]
    public function skipsWithoutExtensionConfiguration(): void
    {
        $this->get(ExtensionConfiguration::class)->set('matomo_tracking', null);

        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function skipsWithoutMatomoUrl(): void
    {
        $this->get(ExtensionConfiguration::class)->set('matomo_tracking', [
            'matomoUrl' => '',
        ]);

        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function skipsWithoutMatomoSiteId(): void
    {
        $this->configureTracking();

        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/'))->withPageId(1));

        self::assertSame(200, $response->getStatusCode());
    }

    private function configureTracking(string $authToken = ''): void
    {
        $trackingConfiguration = [
            'matomoUrl' => $this->mockMatomoServer->getServerRoot(),
        ];

        if (!empty($authToken)) {
            $trackingConfiguration['authToken'] = $authToken;
        }

        $this->get(ExtensionConfiguration::class)->set('matomo_tracking', $trackingConfiguration);
    }

    private function configureMatomoSiteId(): void
    {
        $siteConfiguration = $this->get(SiteConfiguration::class);

        if ((new Typo3Version())->getMajorVersion() < 13) {
            $siteConfiguration->write('1', [
                ...$siteConfiguration->load('1'),
                ...[
                    'matomoTrackingSiteId' => '1',
                ],
            ]);
        } else {
            $siteWriter = $this->get(SiteWriter::class);
            $siteWriter->write('1', [
                ...$siteConfiguration->load('1'),
                ...[
                    'matomoTrackingSiteId' => '1',
                ],
            ]);
        }
    }
}
