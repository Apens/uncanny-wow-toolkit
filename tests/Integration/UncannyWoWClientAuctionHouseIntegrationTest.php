<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Domain\Enum\AuctionTimeLeft;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\UncannyWoWClient;

final class UncannyWoWClientAuctionHouseIntegrationTest extends TestCase
{
    public function testEndToEndConnectedRealmAuctions(): void
    {
        $factory = new Psr17Factory();
        $tokenFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/oauth_token_200.json');
        $auctionsFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/auction_house_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($auctionsFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], $auctionsFixture),
            );

        $wow = UncannyWoWClient::create(
            clientId: 'integration-client-id',
            clientSecret: 'integration-client-secret',
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            defaultRegion: Region::EU,
        );

        $snapshot = $wow->auctionHouse()->auctions(connectedRealmId: 1127);

        self::assertSame(1127, $snapshot->connectedRealmId);
        $auctions = [];
        foreach ($snapshot as $auction) {
            $auctions[] = $auction;
        }

        self::assertCount(4, $auctions);

        self::assertSame(10000001, $auctions[0]->id);
        self::assertSame(19019, $auctions[0]->item->id);
        self::assertSame(0, $auctions[0]->item->context);
        self::assertSame([1487, 6652], $auctions[0]->item->bonusLists);
        self::assertCount(1, $auctions[0]->item->modifiers);
        self::assertSame(28, $auctions[0]->item->modifiers[0]->type);
        self::assertSame(156, $auctions[0]->item->modifiers[0]->value);
        self::assertSame(50000000, $auctions[0]->buyoutCopper);
        self::assertSame(45000000, $auctions[0]->bidCopper);
        self::assertSame(AuctionTimeLeft::VERY_LONG, $auctions[0]->timeLeft);

        self::assertSame(10000002, $auctions[1]->id);
        self::assertSame(210781, $auctions[1]->item->id);
        self::assertSame(256, $auctions[1]->item->petSpeciesId);
        self::assertSame(3, $auctions[1]->item->petBreedId);
        self::assertSame(25, $auctions[1]->item->petLevel);
        self::assertSame(3, $auctions[1]->item->petQualityId);
    }

    public function testEndToEndCommodities(): void
    {
        $factory = new Psr17Factory();
        $tokenFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/oauth_token_200.json');
        $commoditiesFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/commodities_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($commoditiesFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], $commoditiesFixture),
            );

        $wow = UncannyWoWClient::create(
            clientId: 'integration-client-id',
            clientSecret: 'integration-client-secret',
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            defaultRegion: Region::EU,
        );

        $snapshot = $wow->auctionHouse()->commodities();

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
    }

    public function testAuctionHouseMemoization(): void
    {
        $factory = new Psr17Factory();
        $httpClient = $this->createStub(ClientInterface::class);

        $wow = UncannyWoWClient::create(
            clientId: 'test',
            clientSecret: 'test',
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
        );

        $service1 = $wow->auctionHouse();
        $service2 = $wow->auctionHouse();

        self::assertSame($service1, $service2);
    }
}
