<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration\Provider\Blizzard;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Exception\NetworkException;
use UncannyWoW\Core\Domain\Exception\ProviderUnavailableException;
use UncannyWoW\Core\Domain\Exception\RateLimitExceededException;
use UncannyWoW\Core\Domain\Exception\ResourceNotFoundException;
use UncannyWoW\Provider\Blizzard\Auth\OAuthTokenProvider;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Hydrator\RealmHydrator;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardRealmRepository;

final class BlizzardRealmRepositoryTest extends TestCase
{
    private Psr17Factory $factory;
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->config = new ClientConfiguration('test-client-id', 'test-client-secret', Region::EU, Locale::FR_FR);
    }

    public function testGetBySlugSuccess200(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $realmFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/realm_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($realmFixture);

        /** @var list<string> $requestedUrls */
        $requestedUrls = [];
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$requestedUrls, $tokenFixture, $realmFixture) {
                $requestedUrls[] = (string) $request->getUri();
                if (count($requestedUrls) === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }
                return new Response(200, ['Content-Type' => 'application/json'], $realmFixture);
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RealmHydrator();
        $repository = new BlizzardRealmRepository($apiClient, $hydrator, $this->config);

        $realm = $repository->getBySlug(Region::EU, 'la-croisade-écarlate');

        self::assertSame(1086, $realm->id);
        self::assertSame('la-croisade-écarlate', $realm->slug);
        self::assertSame('La Croisade écarlate', $realm->name);
        self::assertSame('French', $realm->category);
        self::assertSame(Locale::FR_FR, $realm->locale);
        self::assertSame('Europe/Paris', $realm->timezone);
        self::assertSame(1086, $realm->connectedRealmId);

        // Verify request URI: host, path encoding, dynamic namespace, locale
        self::assertCount(2, $requestedUrls);
        self::assertStringContainsString('/data/wow/realm/la-croisade-%C3%A9carlate', $requestedUrls[1]);
        self::assertStringContainsString('namespace=dynamic-eu', $requestedUrls[1]);
        self::assertStringContainsString('locale=fr_FR', $requestedUrls[1]);
    }

    public function testGetBySlug404ThrowsResourceNotFoundException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $notFoundFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/realm_404.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($notFoundFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(404, ['Content-Type' => 'application/json'], $notFoundFixture),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RealmHydrator();
        $repository = new BlizzardRealmRepository($apiClient, $hydrator, $this->config);

        try {
            $repository->getBySlug(Region::EU, 'unknown-realm');
            self::fail('Expected ResourceNotFoundException was not thrown.');
        } catch (ResourceNotFoundException $e) {
            self::assertSame('realm', $e->getResourceType());
            self::assertSame('eu:unknown-realm', $e->getIdentifier());
        }
    }

    public function testGetBySlug429ThrowsRateLimitExceededException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(429, ['Retry-After' => '60'], '{"error": "quota_exceeded"}'),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RealmHydrator();
        $repository = new BlizzardRealmRepository($apiClient, $hydrator, $this->config);

        try {
            $repository->getBySlug(Region::EU, 'hyjal');
            self::fail('Expected RateLimitExceededException was not thrown.');
        } catch (RateLimitExceededException $e) {
            self::assertSame(60, $e->getRetryAfterSeconds());
        }
    }

    public function testGetBySlug500ThrowsProviderUnavailableException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(503, [], 'Service Unavailable'),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RealmHydrator();
        $repository = new BlizzardRealmRepository($apiClient, $hydrator, $this->config);

        $this->expectException(ProviderUnavailableException::class);
        $repository->getBySlug(Region::EU, 'hyjal');
    }

    public function testGetBySlugNetworkExceptionWrapped(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);
        $clientException = $this->createStub(ClientExceptionInterface::class);

        $callCount = 0;
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function () use (&$callCount, $tokenFixture, $clientException) {
                $callCount++;
                if ($callCount === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }
                throw $clientException;
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RealmHydrator();
        $repository = new BlizzardRealmRepository($apiClient, $hydrator, $this->config);

        $this->expectException(NetworkException::class);
        $repository->getBySlug(Region::EU, 'hyjal');
    }

    public function testEmptySlugThrowsInvalidArgumentException(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RealmHydrator();
        $repository = new BlizzardRealmRepository($apiClient, $hydrator, $this->config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Realm slug cannot be empty.');

        $repository->getBySlug(Region::EU, '   ');
    }

    public function testSlugWithWhitespaceThrowsInvalidArgumentException(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RealmHydrator();
        $repository = new BlizzardRealmRepository($apiClient, $hydrator, $this->config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Realm slug cannot contain whitespace.');

        $repository->getBySlug(Region::EU, 'la croisade ecarlate');
    }

    public function testSearchByNameSuccess200(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $searchFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/realm_search_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($searchFixture);

        /** @var list<string> $requestedUrls */
        $requestedUrls = [];
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$requestedUrls, $tokenFixture, $searchFixture) {
                $requestedUrls[] = (string) $request->getUri();
                if (count($requestedUrls) === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }
                return new Response(200, ['Content-Type' => 'application/json'], $searchFixture);
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RealmHydrator();
        $repository = new BlizzardRealmRepository($apiClient, $hydrator, $this->config);

        $results = $repository->searchByName(Region::EU, 'La Croisade écarlate');

        self::assertCount(1, $results);
        self::assertSame(1086, $results[0]->id);
        self::assertSame('la-croisade-écarlate', $results[0]->slug);
        self::assertSame('La Croisade écarlate', $results[0]->name);
        self::assertSame('French', $results[0]->category);

        self::assertCount(2, $requestedUrls);
        self::assertStringContainsString('/data/wow/search/realm', $requestedUrls[1]);
        self::assertStringContainsString('name.fr_FR=' . urlencode('La Croisade écarlate'), $requestedUrls[1]);
        self::assertStringContainsString('namespace=dynamic-eu', $requestedUrls[1]);
    }

    public function testSearchByNameEmptyResults(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $emptySearchFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/realm_search_empty_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($emptySearchFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], $emptySearchFixture),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RealmHydrator();
        $repository = new BlizzardRealmRepository($apiClient, $hydrator, $this->config);

        $results = $repository->searchByName(Region::EU, 'NonExistentRealm');
        self::assertSame([], $results);
    }

    public function testSearchByNameEmptyInputThrowsInvalidArgumentException(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RealmHydrator();
        $repository = new BlizzardRealmRepository($apiClient, $hydrator, $this->config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search name cannot be empty.');

        $repository->searchByName(Region::EU, '   ');
    }

    public function testSearchByNameMissingResultsKeyThrowsInvalidResponseException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], '{"page": 1}'),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RealmHydrator();
        $repository = new BlizzardRealmRepository($apiClient, $hydrator, $this->config);

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Expected "results" array in realm search payload.');

        $repository->searchByName(Region::EU, 'Test');
    }
}
