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
use UncannyWoW\Provider\Blizzard\Hydrator\ConnectedRealmHydrator;
use UncannyWoW\Provider\Blizzard\Hydrator\RealmHydrator;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardConnectedRealmRepository;

final class BlizzardConnectedRealmRepositoryTest extends TestCase
{
    private Psr17Factory $factory;
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->config = new ClientConfiguration('test-client-id', 'test-client-secret', Region::EU, Locale::FR_FR);
    }

    public function testGetByIdSuccess200(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $connectedRealmFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/connected_realm_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($connectedRealmFixture);

        /** @var list<string> $requestedUrls */
        $requestedUrls = [];
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$requestedUrls, $tokenFixture, $connectedRealmFixture) {
                $requestedUrls[] = (string) $request->getUri();
                if (count($requestedUrls) === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }
                return new Response(200, ['Content-Type' => 'application/json'], $connectedRealmFixture);
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $realmHydrator = new RealmHydrator();
        $hydrator = new ConnectedRealmHydrator($realmHydrator);
        $repository = new BlizzardConnectedRealmRepository($apiClient, $hydrator);

        $connectedRealm = $repository->getById(Region::EU, 1127, Locale::FR_FR);

        self::assertSame(1127, $connectedRealm->id);
        self::assertCount(7, $connectedRealm->realms);

        self::assertSame(1127, $connectedRealm->realms[0]->id);
        self::assertSame('confrérie-du-thorium', $connectedRealm->realms[0]->slug);
        self::assertSame('Confrérie du Thorium', $connectedRealm->realms[0]->name);
        self::assertSame(1127, $connectedRealm->realms[0]->connectedRealmId);

        self::assertSame(1086, $connectedRealm->realms[1]->id);
        self::assertSame('la-croisade-écarlate', $connectedRealm->realms[1]->slug);
        self::assertSame('La Croisade écarlate', $connectedRealm->realms[1]->name);
        self::assertSame(1127, $connectedRealm->realms[1]->connectedRealmId);

        self::assertSame(1337, $connectedRealm->realms[2]->id);
        self::assertSame('culte-de-la-rive-noire', $connectedRealm->realms[2]->slug);
        self::assertSame('Culte de la Rive noire', $connectedRealm->realms[2]->name);
        self::assertSame(1127, $connectedRealm->realms[2]->connectedRealmId);

        self::assertSame(647, $connectedRealm->realms[3]->id);
        self::assertSame('les-sentinelles', $connectedRealm->realms[3]->slug);
        self::assertSame(1127, $connectedRealm->realms[3]->connectedRealmId);

        self::assertSame(537, $connectedRealm->realms[4]->id);
        self::assertSame('kirin-tor', $connectedRealm->realms[4]->slug);
        self::assertSame(1127, $connectedRealm->realms[4]->connectedRealmId);

        self::assertSame(1626, $connectedRealm->realms[5]->id);
        self::assertSame('les-clairvoyants', $connectedRealm->realms[5]->slug);
        self::assertSame(1127, $connectedRealm->realms[5]->connectedRealmId);

        self::assertSame(644, $connectedRealm->realms[6]->id);
        self::assertSame('conseil-des-ombres', $connectedRealm->realms[6]->slug);
        self::assertSame(1127, $connectedRealm->realms[6]->connectedRealmId);

        // Verify request URI: host, dynamic namespace, locale
        self::assertCount(2, $requestedUrls);
        self::assertStringContainsString('/data/wow/connected-realm/1127', $requestedUrls[1]);
        self::assertStringContainsString('namespace=dynamic-eu', $requestedUrls[1]);
        self::assertStringContainsString('locale=fr_FR', $requestedUrls[1]);
    }

    public function testGetById404ThrowsResourceNotFoundException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $notFoundFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/connected_realm_404.json');
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
        $realmHydrator = new RealmHydrator();
        $hydrator = new ConnectedRealmHydrator($realmHydrator);
        $repository = new BlizzardConnectedRealmRepository($apiClient, $hydrator);

        try {
            $repository->getById(Region::EU, 99999999, Locale::FR_FR);
            self::fail('Expected ResourceNotFoundException was not thrown.');
        } catch (ResourceNotFoundException $e) {
            self::assertSame('connected-realm', $e->getResourceType());
            self::assertSame('eu:99999999', $e->getIdentifier());
        }
    }

    public function testGetById429ThrowsRateLimitExceededException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(429, ['Content-Type' => 'application/json'], '{"error":"Too Many Requests"}'),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $realmHydrator = new RealmHydrator();
        $hydrator = new ConnectedRealmHydrator($realmHydrator);
        $repository = new BlizzardConnectedRealmRepository($apiClient, $hydrator);

        $this->expectException(RateLimitExceededException::class);
        $repository->getById(Region::EU, 1127, Locale::FR_FR);
    }

    public function testGetById503ThrowsProviderUnavailableException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(503, ['Content-Type' => 'application/json'], '{"error":"Service Unavailable"}'),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $realmHydrator = new RealmHydrator();
        $hydrator = new ConnectedRealmHydrator($realmHydrator);
        $repository = new BlizzardConnectedRealmRepository($apiClient, $hydrator);

        $this->expectException(ProviderUnavailableException::class);
        $repository->getById(Region::EU, 1127, Locale::FR_FR);
    }

    public function testGetByIdNetworkErrorThrowsNetworkException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $networkException = $this->createStub(ClientExceptionInterface::class);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function () use ($tokenFixture, $networkException) {
                static $calls = 0;
                $calls++;
                if ($calls === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }
                throw $networkException;
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $realmHydrator = new RealmHydrator();
        $hydrator = new ConnectedRealmHydrator($realmHydrator);
        $repository = new BlizzardConnectedRealmRepository($apiClient, $hydrator);

        $this->expectException(NetworkException::class);
        $repository->getById(Region::EU, 1127, Locale::FR_FR);
    }

    public function testGetByIdMalformedJsonThrowsInvalidResponseException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], '{"invalid_json":'),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $realmHydrator = new RealmHydrator();
        $hydrator = new ConnectedRealmHydrator($realmHydrator);
        $repository = new BlizzardConnectedRealmRepository($apiClient, $hydrator);

        $this->expectException(InvalidResponseException::class);
        $repository->getById(Region::EU, 1127, Locale::FR_FR);
    }

    public function testGetByIdWithNonPositiveIdThrowsInvalidArgumentException(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $realmHydrator = new RealmHydrator();
        $hydrator = new ConnectedRealmHydrator($realmHydrator);
        $repository = new BlizzardConnectedRealmRepository($apiClient, $hydrator);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connected realm ID must be a positive integer, got 0.');

        $repository->getById(Region::EU, 0, Locale::FR_FR);
    }
}
