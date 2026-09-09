<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\UncannyWoWClient;

final class UncannyWoWClientEconomyIntegrationTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testEconomyConnectedRealmIntegration(): void
    {
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

        $client = UncannyWoWClient::create(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            defaultRegion: Region::EU,
            httpClient: $httpClient,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
        );

        $marketData = $client->economy()->connectedRealm(1127);

        self::assertSame(1127, $marketData->connectedRealmId);
        self::assertSame(4, $marketData->totalAuctions);
        self::assertSame(27, $marketData->totalQuantity); // 1 + 1 + 20 + 5
        self::assertSame(4, $marketData->uniqueVariantCount());

        $matches = $marketData->getByItemId(19019);
        self::assertCount(1, $matches);
        self::assertSame(50000000, $matches[0]->lowestBuyoutCopper);
    }

    public function testEconomyCommoditiesIntegration(): void
    {
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

        $client = UncannyWoWClient::create(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            defaultRegion: Region::EU,
            httpClient: $httpClient,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
        );

        $marketData = $client->economy()->commodities();

        self::assertSame(Region::EU, $marketData->region);
        self::assertSame(4, $marketData->totalAuctions);
        self::assertSame(1527, $marketData->totalQuantity); // 500 + 1000 + 25 + 2
        self::assertCount(3, $marketData);

        $summary = $marketData->get(190381);
        self::assertNotNull($summary);
        self::assertSame(2, $summary->auctionCount);
        self::assertSame(1500, $summary->totalQuantity);
        self::assertSame(12500, $summary->lowestUnitPriceCopper);
        self::assertSame(12600, $summary->highestUnitPriceCopper);
    }
}
