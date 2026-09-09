<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration\Provider\Blizzard;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\ItemQuality;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Exception\NetworkException;
use UncannyWoW\Core\Domain\Exception\ProviderUnavailableException;
use UncannyWoW\Core\Domain\Exception\RateLimitExceededException;
use UncannyWoW\Core\Domain\Exception\ResourceNotFoundException;
use UncannyWoW\Provider\Blizzard\Auth\OAuthTokenProvider;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Hydrator\ItemHydrator;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardItemRepository;

final class BlizzardItemRepositoryTest extends TestCase
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
        $itemFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/item_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($itemFixture);

        /** @var list<string> $requestedUrls */
        $requestedUrls = [];
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$requestedUrls, $tokenFixture, $itemFixture) {
                $requestedUrls[] = (string) $request->getUri();
                if (count($requestedUrls) === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }
                return new Response(200, ['Content-Type' => 'application/json'], $itemFixture);
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new ItemHydrator();
        $repository = new BlizzardItemRepository($apiClient, $hydrator);

        $item = $repository->getById(Region::EU, 19019, Locale::FR_FR);

        self::assertSame(19019, $item->id);
        self::assertSame('Lame-tonnerre, épée bénie du Cherchevent', $item->name);
        self::assertSame(ItemQuality::LEGENDARY, $item->quality);
        self::assertSame(29, $item->level);
        self::assertSame(25, $item->requiredLevel);
        self::assertNotNull($item->itemClass);
        self::assertSame(2, $item->itemClass->id);
        self::assertSame('Arme', $item->itemClass->name);
        self::assertNotNull($item->itemSubclass);
        self::assertSame(7, $item->itemSubclass->id);
        self::assertSame('Épée', $item->itemSubclass->name);
        self::assertNotNull($item->inventoryType);
        self::assertSame('WEAPON', $item->inventoryType->type);
        self::assertSame('À une main', $item->inventoryType->name);
        self::assertSame(1, $item->maxCount);
        self::assertTrue($item->isEquippable);

        // Verify request URI: host, static namespace, locale
        self::assertCount(2, $requestedUrls);
        self::assertStringContainsString('/data/wow/item/19019', $requestedUrls[1]);
        self::assertStringContainsString('namespace=static-eu', $requestedUrls[1]);
        self::assertStringContainsString('locale=fr_FR', $requestedUrls[1]);
    }

    public function testGetById404ThrowsResourceNotFoundException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $notFoundFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/item_404.json');
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
        $hydrator = new ItemHydrator();
        $repository = new BlizzardItemRepository($apiClient, $hydrator);

        try {
            $repository->getById(Region::EU, 99999999, Locale::FR_FR);
            self::fail('Expected ResourceNotFoundException was not thrown.');
        } catch (ResourceNotFoundException $e) {
            self::assertSame('item', $e->getResourceType());
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
        $hydrator = new ItemHydrator();
        $repository = new BlizzardItemRepository($apiClient, $hydrator);

        $this->expectException(RateLimitExceededException::class);
        $repository->getById(Region::EU, 19019, Locale::FR_FR);
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
        $hydrator = new ItemHydrator();
        $repository = new BlizzardItemRepository($apiClient, $hydrator);

        $this->expectException(ProviderUnavailableException::class);
        $repository->getById(Region::EU, 19019, Locale::FR_FR);
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
        $hydrator = new ItemHydrator();
        $repository = new BlizzardItemRepository($apiClient, $hydrator);

        $this->expectException(NetworkException::class);
        $repository->getById(Region::EU, 19019, Locale::FR_FR);
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
        $hydrator = new ItemHydrator();
        $repository = new BlizzardItemRepository($apiClient, $hydrator);

        $this->expectException(InvalidResponseException::class);
        $repository->getById(Region::EU, 19019, Locale::FR_FR);
    }

    public function testGetByIdWithNonPositiveIdThrowsInvalidArgumentException(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new ItemHydrator();
        $repository = new BlizzardItemRepository($apiClient, $hydrator);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item ID must be a positive integer, got 0.');

        $repository->getById(Region::EU, 0, Locale::FR_FR);
    }
}
