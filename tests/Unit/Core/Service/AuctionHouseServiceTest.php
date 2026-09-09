<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Service;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\AuctionHouseRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\AuctionTimeLeft;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\AuctionHouse\Auction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionHouseSnapshot;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityAuction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityMarketSnapshot;
use UncannyWoW\Core\Service\AuctionHouseService;

final class AuctionHouseServiceTest extends TestCase
{
    private AuctionHouseSnapshot $dummyAuctions;
    private CommodityMarketSnapshot $dummyCommodities;

    protected function setUp(): void
    {
        $auction = new Auction(
            id: 10000001,
            item: new AuctionItem(id: 19019),
            quantity: 1,
            buyoutCopper: 50000000,
            bidCopper: 45000000,
            timeLeft: AuctionTimeLeft::VERY_LONG,
        );
        $this->dummyAuctions = new AuctionHouseSnapshot(
            connectedRealmId: 1127,
            auctions: [$auction],
        );

        $commodity = new CommodityAuction(
            id: 20000001,
            itemId: 190381,
            quantity: 500,
            unitPriceCopper: 12500,
            timeLeft: AuctionTimeLeft::VERY_LONG,
        );
        $this->dummyCommodities = new CommodityMarketSnapshot(
            region: Region::EU,
            auctions: [$commodity],
        );
    }

    public function testAuctionsUsesConfiguredDefaultRegion(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::EU,
        );

        $repository = $this->createMock(AuctionHouseRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getAuctions')
            ->with(Region::EU, 1127)
            ->willReturn($this->dummyAuctions);

        $service = new AuctionHouseService($repository, $config);
        $result = $service->auctions(1127);

        self::assertSame($this->dummyAuctions, $result);
    }

    public function testAuctionsWithExplicitRegionOverride(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::EU,
        );

        $usSnapshot = new AuctionHouseSnapshot(connectedRealmId: 1127, auctions: []);

        $repository = $this->createMock(AuctionHouseRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getAuctions')
            ->with(Region::US, 1127)
            ->willReturn($usSnapshot);

        $service = new AuctionHouseService($repository, $config);
        $result = $service->auctions(1127, region: Region::US);

        self::assertSame($usSnapshot, $result);
    }

    public function testAuctionsWithZeroIdThrowsInvalidArgumentException(): void
    {
        $config = new ClientConfiguration('test-id', 'test-secret');
        $repository = $this->createStub(AuctionHouseRepositoryInterface::class);
        $service = new AuctionHouseService($repository, $config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connected realm ID must be a positive integer, got 0.');

        $service->auctions(0);
    }

    public function testAuctionsWithNegativeIdThrowsInvalidArgumentException(): void
    {
        $config = new ClientConfiguration('test-id', 'test-secret');
        $repository = $this->createStub(AuctionHouseRepositoryInterface::class);
        $service = new AuctionHouseService($repository, $config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connected realm ID must be a positive integer, got -5.');

        $service->auctions(-5);
    }

    public function testCommoditiesUsesConfiguredDefaultRegion(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::EU,
        );

        $repository = $this->createMock(AuctionHouseRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getCommodities')
            ->with(Region::EU)
            ->willReturn($this->dummyCommodities);

        $service = new AuctionHouseService($repository, $config);
        $result = $service->commodities();

        self::assertSame($this->dummyCommodities, $result);
    }

    public function testCommoditiesWithExplicitRegionOverride(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::EU,
        );

        $usCommodities = new CommodityMarketSnapshot(region: Region::US, auctions: []);

        $repository = $this->createMock(AuctionHouseRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getCommodities')
            ->with(Region::US)
            ->willReturn($usCommodities);

        $service = new AuctionHouseService($repository, $config);
        $result = $service->commodities(region: Region::US);

        self::assertSame($usCommodities, $result);
    }
}
