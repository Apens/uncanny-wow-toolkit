<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Service;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\AuctionHouseRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\AuctionHouse\Auction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionHouseSnapshot;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityAuction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityMarketSnapshot;
use UncannyWoW\Core\Service\AuctionHouseService;
use UncannyWoW\Core\Service\EconomyService;

final class EconomyServiceTest extends TestCase
{
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->config = new ClientConfiguration('test-id', 'test-secret', Region::EU);
    }

    public function testCommoditiesDelegation(): void
    {
        $commodity = new CommodityAuction(id: 1, itemId: 190381, quantity: 50, unitPriceCopper: 12000);
        $snapshot = new CommodityMarketSnapshot(Region::EU, [$commodity]);

        $ahRepository = $this->createMock(AuctionHouseRepositoryInterface::class);
        $ahRepository->expects(self::once())
            ->method('getCommodities')
            ->with(Region::EU)
            ->willReturn($snapshot);

        $ahService = new AuctionHouseService($ahRepository, $this->config);
        $economyService = new EconomyService($ahService, $this->config);

        $marketData = $economyService->commodities();

        self::assertSame(Region::EU, $marketData->region);
        self::assertSame(1, $marketData->totalAuctions);
        self::assertSame(50, $marketData->totalQuantity);
        self::assertTrue($marketData->has(190381));
    }

    public function testConnectedRealmDelegation(): void
    {
        $auction = new Auction(id: 1, item: new AuctionItem(id: 19019), quantity: 1, buyoutCopper: 50000000);
        $snapshot = new AuctionHouseSnapshot(1127, [$auction]);

        $ahRepository = $this->createMock(AuctionHouseRepositoryInterface::class);
        $ahRepository->expects(self::once())
            ->method('getAuctions')
            ->with(Region::EU, 1127)
            ->willReturn($snapshot);

        $ahService = new AuctionHouseService($ahRepository, $this->config);
        $economyService = new EconomyService($ahService, $this->config);

        $marketData = $economyService->connectedRealm(1127);

        self::assertSame(1127, $marketData->connectedRealmId);
        self::assertSame(1, $marketData->totalAuctions);
        self::assertSame(1, $marketData->totalQuantity);
    }

    public function testSummarizeSnapshotsDirectly(): void
    {
        $ahStub = $this->createStub(AuctionHouseRepositoryInterface::class);
        $ahService = new AuctionHouseService($ahStub, $this->config);
        $economyService = new EconomyService($ahService, $this->config);
        $commodity = new CommodityAuction(id: 1, itemId: 190381, quantity: 10, unitPriceCopper: 100);
        $cSnapshot = new CommodityMarketSnapshot(Region::EU, [$commodity]);
        $cData = $economyService->summarizeCommodities($cSnapshot);
        self::assertSame(1, $cData->totalAuctions);

        $auction = new Auction(id: 1, item: new AuctionItem(id: 19019), quantity: 1, buyoutCopper: 100);
        $aSnapshot = new AuctionHouseSnapshot(1127, [$auction]);
        $aData = $economyService->summarizeAuctions($aSnapshot);
        self::assertSame(1, $aData->totalAuctions);
    }

    public function testConnectedRealmWithInvalidIdThrowsException(): void
    {
        $ahStub = $this->createStub(AuctionHouseRepositoryInterface::class);
        $ahService = new AuctionHouseService($ahStub, $this->config);
        $economyService = new EconomyService($ahService, $this->config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connected realm ID must be a positive integer, got 0.');

        $economyService->connectedRealm(0);
    }

    public function testConnectedRealmMaxPriceLevelsBoundaries(): void
    {
        $ahStub = $this->createStub(AuctionHouseRepositoryInterface::class);
        $ahService = new AuctionHouseService($ahStub, $this->config);
        $economyService = new EconomyService($ahService, $this->config);

        foreach ([0, -1, 11, \PHP_INT_MAX] as $invalidN) {
            try {
                $economyService->connectedRealm(1127, null, $invalidN);
                self::fail(sprintf('Expected InvalidArgumentException for maxPriceLevels = %d', $invalidN));
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Max price levels must be between 1 and 10', $e->getMessage());
            }
        }
    }

    public function testCommoditiesMaxPriceLevelsBoundaries(): void
    {
        $ahStub = $this->createStub(AuctionHouseRepositoryInterface::class);
        $ahService = new AuctionHouseService($ahStub, $this->config);
        $economyService = new EconomyService($ahService, $this->config);

        foreach ([0, -1, 11, \PHP_INT_MAX] as $invalidN) {
            try {
                $economyService->commodities(null, $invalidN);
                self::fail(sprintf('Expected InvalidArgumentException for maxPriceLevels = %d', $invalidN));
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Max price levels must be between 1 and 10', $e->getMessage());
            }
        }
    }

    public function testSummarizeSnapshotsMaxPriceLevelsBoundaries(): void
    {
        $ahStub = $this->createStub(AuctionHouseRepositoryInterface::class);
        $ahService = new AuctionHouseService($ahStub, $this->config);
        $economyService = new EconomyService($ahService, $this->config);

        $cSnapshot = new CommodityMarketSnapshot(Region::EU, []);
        $aSnapshot = new AuctionHouseSnapshot(1127, []);

        foreach ([0, -1, 11, \PHP_INT_MAX] as $invalidN) {
            try {
                $economyService->summarizeCommodities($cSnapshot, $invalidN);
                self::fail(sprintf('Expected InvalidArgumentException for maxPriceLevels = %d', $invalidN));
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Max price levels must be between 1 and 10', $e->getMessage());
            }

            try {
                $economyService->summarizeAuctions($aSnapshot, $invalidN);
                self::fail(sprintf('Expected InvalidArgumentException for maxPriceLevels = %d', $invalidN));
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Max price levels must be between 1 and 10', $e->getMessage());
            }
        }
    }
}
