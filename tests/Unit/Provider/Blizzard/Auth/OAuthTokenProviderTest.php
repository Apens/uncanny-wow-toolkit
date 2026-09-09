<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Auth;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Exception\AuthenticationException;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Provider\Blizzard\Auth\OAuthTokenProvider;

final class OAuthTokenProviderTest extends TestCase
{
    private Psr17Factory $factory;
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->config = new ClientConfiguration('test-id', 'test-secret');
    }

    public function testSuccessfulTokenAcquisitionAndInMemoryReuse(): void
    {
        $fixtureJson = file_get_contents(__DIR__ . '/../../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($fixtureJson);
        $response = new Response(200, ['Content-Type' => 'application/json'], $fixtureJson);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $provider = new OAuthTokenProvider(
            config: $this->config,
            httpClient: $httpClient,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
        );

        $token1 = $provider->getAccessToken();
        self::assertSame('EUtestmockaccesstoken123456789', $token1);

        // Second call should return in-memory cached token without triggering HTTP client again (expects self::once())
        $token2 = $provider->getAccessToken();
        self::assertSame('EUtestmockaccesstoken123456789', $token2);
    }

    public function testAuthenticationFailureThrowsException(): void
    {
        $response = new Response(401, [], (string) json_encode(['error' => 'invalid_client']));

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $provider = new OAuthTokenProvider(
            config: $this->config,
            httpClient: $httpClient,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Blizzard OAuth request failed with HTTP status 401.');

        $provider->getAccessToken();
    }

    public function testMalformedJsonThrowsInvalidResponseException(): void
    {
        $response = new Response(200, [], 'INVALID_JSON{');

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $provider = new OAuthTokenProvider(
            config: $this->config,
            httpClient: $httpClient,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
        );

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Malformed JSON response received from Blizzard OAuth endpoint.');

        $provider->getAccessToken();
    }

    public function testMissingAccessTokenFieldThrowsInvalidResponseException(): void
    {
        $response = new Response(200, [], (string) json_encode(['expires_in' => 3600]));

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $provider = new OAuthTokenProvider(
            config: $this->config,
            httpClient: $httpClient,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
        );

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('OAuth response missing valid "access_token" field.');

        $provider->getAccessToken();
    }

    public function testOAuthTokenCachedInPsr6Pool(): void
    {
        $fixtureJson = file_get_contents(__DIR__ . '/../../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($fixtureJson);
        $response = new Response(200, ['Content-Type' => 'application/json'], $fixtureJson);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::once())->method('sendRequest')->willReturn($response);

        $expectedKey = 'uw.oauth.' . substr(hash('sha256', 'test-id@https://oauth.battle.net/token'), 0, 48);

        $cacheItem = $this->createMock(\Psr\Cache\CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(false);
        $cacheItem->expects(self::once())->method('set')->with('EUtestmockaccesstoken123456789');
        $cacheItem->expects(self::once())->method('expiresAfter');

        $cachePool = $this->createMock(\Psr\Cache\CacheItemPoolInterface::class);
        $cachePool->expects(self::exactly(2))
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItem);
        $cachePool->expects(self::once())->method('save')->with($cacheItem);

        $provider = new OAuthTokenProvider(
            config: $this->config,
            httpClient: $httpClient,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
            cachePool: $cachePool,
        );

        $token = $provider->getAccessToken();
        self::assertSame('EUtestmockaccesstoken123456789', $token);
        self::assertLessThanOrEqual(64, strlen($expectedKey));
        self::assertStringNotContainsString('test-secret', $expectedKey);
        self::assertStringNotContainsString('EUtestmockaccesstoken', $expectedKey);
        self::assertMatchesRegularExpression('/^[a-z0-9_.]+$/', $expectedKey);
    }

    public function testOAuthTokenCacheKeyDifferentClientsNeverCollide(): void
    {
        $fixtureJson = file_get_contents(__DIR__ . '/../../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($fixtureJson);

        $capturedKeys = [];
        $cacheItem = $this->createStub(\Psr\Cache\CacheItemInterface::class);

        $cachePool = $this->createMock(\Psr\Cache\CacheItemPoolInterface::class);
        $cachePool->expects(self::exactly(4))
            ->method('getItem')
            ->willReturnCallback(function (string $key) use (&$capturedKeys, $cacheItem) {
                if (!in_array($key, $capturedKeys, true)) {
                    $capturedKeys[] = $key;
                }
                return $cacheItem;
            });

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturn(new Response(200, ['Content-Type' => 'application/json'], $fixtureJson));

        $config1 = new ClientConfiguration('client-app-1', 'secret-1');
        $config2 = new ClientConfiguration('client-app-2', 'secret-2');

        $provider1 = new OAuthTokenProvider($config1, $httpClient, $this->factory, $this->factory, $cachePool);
        $provider2 = new OAuthTokenProvider($config2, $httpClient, $this->factory, $this->factory, $cachePool);

        $provider1->getAccessToken();
        $provider2->getAccessToken();

        self::assertCount(2, $capturedKeys);
        self::assertNotSame($capturedKeys[0], $capturedKeys[1]);
        self::assertLessThanOrEqual(64, strlen($capturedKeys[0]));
        self::assertLessThanOrEqual(64, strlen($capturedKeys[1]));
        self::assertMatchesRegularExpression('/^[a-z0-9_.]+$/', $capturedKeys[0]);
        self::assertMatchesRegularExpression('/^[a-z0-9_.]+$/', $capturedKeys[1]);
    }
}
