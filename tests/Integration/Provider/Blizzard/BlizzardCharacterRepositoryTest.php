<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration\Provider\Blizzard;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\NetworkException;
use UncannyWoW\Core\Domain\Exception\ProviderUnavailableException;
use UncannyWoW\Core\Domain\Exception\RateLimitExceededException;
use UncannyWoW\Core\Domain\Exception\ResourceNotFoundException;
use UncannyWoW\Provider\Blizzard\Auth\OAuthTokenProvider;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Hydrator\CharacterProfileHydrator;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardCharacterRepository;

final class BlizzardCharacterRepositoryTest extends TestCase
{
    private Psr17Factory $factory;
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->config = new ClientConfiguration('test-client-id', 'test-client-secret', Region::EU);
    }

    public function testFindProfileSuccess200(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $profileFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/character_profile_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($profileFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], $profileFixture),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new CharacterProfileHydrator();
        $repository = new BlizzardCharacterRepository($apiClient, $hydrator);

        $profile = $repository->findProfile(Region::EU, 'la-croisade-ecarlate', 'norigosa');

        self::assertSame('Norigosa', $profile->name);
        self::assertSame(80, $profile->level);
        self::assertSame('la-croisade-ecarlate', $profile->id->realmSlug);
        self::assertSame('La Croisade écarlate', $profile->realm->name);
    }

    public function testFindProfile404ThrowsResourceNotFoundException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $notFoundFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/character_profile_404.json');
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
        $hydrator = new CharacterProfileHydrator();
        $repository = new BlizzardCharacterRepository($apiClient, $hydrator);

        try {
            $repository->findProfile(Region::EU, 'la-croisade-ecarlate', 'unknownchar');
            self::fail('Expected ResourceNotFoundException was not thrown.');
        } catch (ResourceNotFoundException $e) {
            self::assertSame('character', $e->getResourceType());
            self::assertSame('eu:la-croisade-ecarlate:unknownchar', $e->getIdentifier());
        }
    }

    public function testFindProfile429ThrowsRateLimitExceededException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(429, ['Retry-After' => '120'], (string) json_encode(['error' => 'quota_exceeded'])),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new CharacterProfileHydrator();
        $repository = new BlizzardCharacterRepository($apiClient, $hydrator);

        try {
            $repository->findProfile(Region::EU, 'la-croisade-ecarlate', 'norigosa');
            self::fail('Expected RateLimitExceededException was not thrown.');
        } catch (RateLimitExceededException $e) {
            self::assertSame(120, $e->getRetryAfterSeconds());
        }
    }

    public function testFindProfile500ThrowsProviderUnavailableException(): void
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
        $hydrator = new CharacterProfileHydrator();
        $repository = new BlizzardCharacterRepository($apiClient, $hydrator);

        $this->expectException(ProviderUnavailableException::class);
        $repository->findProfile(Region::EU, 'la-croisade-ecarlate', 'norigosa');
    }

    public function testNetworkExceptionWrapped(): void
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
        $hydrator = new CharacterProfileHydrator();
        $repository = new BlizzardCharacterRepository($apiClient, $hydrator);

        $this->expectException(NetworkException::class);
        $repository->findProfile(Region::EU, 'la-croisade-ecarlate', 'norigosa');
    }

    public function testRequestUrlPathEncodingForAsciiAndUnicodeNames(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $profileFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/character_profile_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($profileFixture);

        /** @var list<string> $requestedPaths */
        $requestedPaths = [];
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(3))
            ->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$requestedPaths, $tokenFixture, $profileFixture) {
                $requestedPaths[] = $request->getUri()->getPath();
                if (count($requestedPaths) === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }

                return new Response(200, ['Content-Type' => 'application/json'], $profileFixture);
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new CharacterProfileHydrator();
        $repository = new BlizzardCharacterRepository($apiClient, $hydrator);

        $repository->findProfile(Region::EU, 'la-croisade-ecarlate', 'norigosa');
        $repository->findProfile(Region::EU, 'la-croisade-ecarlate', 'Nörigosa');

        self::assertCount(3, $requestedPaths);
        self::assertSame('/token', $requestedPaths[0]);
        self::assertSame('/profile/wow/character/la-croisade-ecarlate/norigosa', $requestedPaths[1]);
        self::assertSame('/profile/wow/character/la-croisade-ecarlate/n%C3%B6rigosa', $requestedPaths[2]);
    }

    public function testRealmSlugWithWhitespaceThrowsInvalidArgumentException(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new CharacterProfileHydrator();
        $repository = new BlizzardCharacterRepository($apiClient, $hydrator);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Realm slug cannot contain whitespace.');

        $repository->findProfile(Region::EU, 'la croisade ecarlate', 'norigosa');
    }
}
