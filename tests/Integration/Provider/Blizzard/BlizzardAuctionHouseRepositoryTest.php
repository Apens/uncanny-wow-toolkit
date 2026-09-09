<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration\Provider\Blizzard;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\AuctionTimeLeft;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Exception\NetworkException;
use UncannyWoW\Core\Domain\Exception\ProviderUnavailableException;
use UncannyWoW\Core\Domain\Exception\RateLimitExceededException;
use UncannyWoW\Core\Domain\Exception\ResourceNotFoundException;
use UncannyWoW\Provider\Blizzard\Auth\OAuthTokenProvider;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Hydrator\AuctionHouseHydrator;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardAuctionHouseRepository;

final class BlizzardAuctionHouseRepositoryTest extends TestCase
{
    private Psr17Factory $factory;
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->config = new ClientConfiguration('test-client-id', 'test-client-secret', Region::EU);
    }

    public function testGetAuctionsSuccess200(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $auctionsFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/auction_house_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($auctionsFixture);

        /** @var list<string> $requestedUrls */
        $requestedUrls = [];
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$requestedUrls, $tokenFixture, $auctionsFixture) {
                $requestedUrls[] = (string) $request->getUri();
                if (count($requestedUrls) === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }
                return new Response(200, ['Content-Type' => 'application/json'], $auctionsFixture);
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new AuctionHouseHydrator();
        $repository = new BlizzardAuctionHouseRepository($apiClient, $hydrator);

        $snapshot = $repository->getAuctions(Region::EU, 1127);

        self::assertSame(1127, $snapshot->connectedRealmId);
        $auctions = [];
        foreach ($snapshot as $auction) {
            $auctions[] = $auction;
        }

        self::assertCount(4, $auctions);
        self::assertSame(10000001, $auctions[0]->id);
        self::assertSame(19019, $auctions[0]->item->id);
        self::assertSame(50000000, $auctions[0]->buyoutCopper);
        self::assertSame(45000000, $auctions[0]->bidCopper);

        // Verify request URI: host, dynamic namespace, path
        self::assertCount(2, $requestedUrls);
        self::assertStringContainsString('/data/wow/connected-realm/1127/auctions', $requestedUrls[1]);
        self::assertStringContainsString('namespace=dynamic-eu', $requestedUrls[1]);
    }

    public function testGetCommoditiesSuccess200(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $commoditiesFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/commodities_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($commoditiesFixture);

        /** @var list<string> $requestedUrls */
        $requestedUrls = [];
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$requestedUrls, $tokenFixture, $commoditiesFixture) {
                $requestedUrls[] = (string) $request->getUri();
                if (count($requestedUrls) === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }
                return new Response(200, ['Content-Type' => 'application/json'], $commoditiesFixture);
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new AuctionHouseHydrator();
        $repository = new BlizzardAuctionHouseRepository($apiClient, $hydrator);

        $snapshot = $repository->getCommodities(Region::EU);

        self::assertSame(Region::EU, $snapshot->region);
        $commodities = [];
        foreach ($snapshot as $commodity) {
            $commodities[] = $commodity;
        }

        self::assertCount(4, $commodities);
        self::assertSame(20000001, $commodities[0]->id);
        self::assertSame(190381, $commodities[0]->itemId);
        self::assertSame(500, $commodities[0]->quantity);
        self::assertSame(12500, $commodities[0]->unitPriceCopper);
        self::assertSame(AuctionTimeLeft::VERY_LONG, $commodities[0]->timeLeft);

        // Verify request URI: host, dynamic namespace, path
        self::assertCount(2, $requestedUrls);
        self::assertStringContainsString('/data/wow/auctions/commodities', $requestedUrls[1]);
        self::assertStringContainsString('namespace=dynamic-eu', $requestedUrls[1]);
    }

    public function testGetAuctions404ThrowsResourceNotFoundException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(404, ['Content-Type' => 'application/json'], '{"code":404,"type":"NOT_FOUND"}'),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new AuctionHouseHydrator();
        $repository = new BlizzardAuctionHouseRepository($apiClient, $hydrator);

        try {
            $repository->getAuctions(Region::EU, 99999999);
            self::fail('Expected ResourceNotFoundException was not thrown.');
        } catch (ResourceNotFoundException $e) {
            self::assertSame('auction-house', $e->getResourceType());
            self::assertSame('eu:99999999', $e->getIdentifier());
        }
    }

    public function testGetCommodities404ThrowsResourceNotFoundException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(404, ['Content-Type' => 'application/json'], '{"code":404,"type":"NOT_FOUND"}'),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new AuctionHouseHydrator();
        $repository = new BlizzardAuctionHouseRepository($apiClient, $hydrator);

        try {
            $repository->getCommodities(Region::EU);
            self::fail('Expected ResourceNotFoundException was not thrown.');
        } catch (ResourceNotFoundException $e) {
            self::assertSame('commodities', $e->getResourceType());
            self::assertSame('eu', $e->getIdentifier());
        }
    }

    public function testGetAuctionsWithNonPositiveIdThrowsInvalidArgumentException(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new AuctionHouseHydrator();
        $repository = new BlizzardAuctionHouseRepository($apiClient, $hydrator);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connected realm ID must be a positive integer, got 0.');

        $repository->getAuctions(Region::EU, 0);
    }

    public function testStreamingAuctionsIsLazyAndBailEarly(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        // Build a mock stream with many simulated items
        $jsonParts = ['{"auctions":['];
        for ($i = 1; $i <= 100; ++$i) {
            $jsonParts[] = sprintf('{"id":%d,"item":{"id":19019},"quantity":1,"buyout":100,"time_left":"SHORT"}', $i);
            if ($i < 100) {
                $jsonParts[] = ',';
            }
        }
        $jsonParts[] = ']}';
        $bigPayload = implode('', $jsonParts);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], $bigPayload),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new AuctionHouseHydrator();
        $repository = new BlizzardAuctionHouseRepository($apiClient, $hydrator);

        $snapshot = $repository->getAuctions(Region::EU, 1127);

        // Consumer reads only 3 auctions and breaks early
        $count = 0;
        foreach ($snapshot as $auction) {
            ++$count;
            if ($count === 3) {
                break;
            }
        }

        self::assertSame(3, $count);
    }

    public function testGetAuctionsCannotBeIteratedTwiceAndDoesNotRefetch(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $auctionsFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/auction_house_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($auctionsFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        // Exactly 2 requests: 1 token + 1 auctions payload
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], $auctionsFixture),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new AuctionHouseHydrator();
        $repository = new BlizzardAuctionHouseRepository($apiClient, $hydrator);

        $snapshot = $repository->getAuctions(Region::EU, 1127);

        $count = 0;
        foreach ($snapshot as $auction) {
            $count++;
        }
        self::assertSame(4, $count);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('This auction snapshot has already been consumed.');

        // Second iteration must fail with LogicException without triggering any HTTP request
        foreach ($snapshot as $auction) {
            // Should not be reached
        }
    }

    public function testGetCommoditiesCannotBeIteratedTwiceAndDoesNotRefetch(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        $commoditiesFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/commodities_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($commoditiesFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        // Exactly 2 requests: 1 token + 1 commodities payload
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], $commoditiesFixture),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new AuctionHouseHydrator();
        $repository = new BlizzardAuctionHouseRepository($apiClient, $hydrator);

        $snapshot = $repository->getCommodities(Region::EU);

        $count = 0;
        foreach ($snapshot as $commodity) {
            $count++;
        }
        self::assertSame(4, $count);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('This auction snapshot has already been consumed.');

        // Second iteration must fail with LogicException without triggering any HTTP request
        foreach ($snapshot as $commodity) {
            // Should not be reached
        }
    }
}
