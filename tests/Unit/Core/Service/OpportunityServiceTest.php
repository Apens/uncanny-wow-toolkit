<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Service;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\Economy\AuctionItemMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketSummary;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;
use UncannyWoW\Core\Domain\Model\Economy\MarketItemIdentity;
use UncannyWoW\Core\Domain\Model\Economy\NonCommodityPriceLevel;
use UncannyWoW\Core\Domain\Model\Economy\PriceLevel;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionHouseFeePolicy;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionItemOpportunity;
use UncannyWoW\Core\Domain\Model\Opportunity\CommodityOpportunity;
use UncannyWoW\Core\Service\EconomyService;
use UncannyWoW\Core\Service\OpportunityService;

final class OpportunityServiceTest extends TestCase
{
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->config = new ClientConfiguration('test-id', 'test-secret', Region::EU);
    }

    public function testCommoditiesDelegationToEconomyService(): void
    {
        $levels = [
            new PriceLevel(100, 10, 1),
            new PriceLevel(200, 5, 1),
        ];
        $summary = new CommodityMarketSummary(1001, 2, 15, 100, 10, 200, $levels);
        $marketData = new CommodityMarketData(Region::EU, [1001 => $summary], 2, 15);

        $economyService = $this->createMock(EconomyService::class);
        $economyService->expects(self::once())
            ->method('commodities')
            ->with(Region::EU)
            ->willReturn($marketData);

        $opportunityService = new OpportunityService($economyService, $this->config);
        $analysis = $opportunityService->commodities();

        $opps = $analysis->all();
        self::assertCount(1, $opps);
        self::assertInstanceOf(CommodityOpportunity::class, $opps[0]);
        self::assertSame(1001, $opps[0]->itemId);
        self::assertSame(900, $opps[0]->prospectiveProfitCopper);
    }

    public function testConnectedRealmDelegationToEconomyService(): void
    {
        $item = new AuctionItem(60001);
        $identity = MarketItemIdentity::fromAuctionItem($item);
        $levels = [
            new NonCommodityPriceLevel(100, 1, 1, 1),
            new NonCommodityPriceLevel(200, 1, 1, 1),
        ];
        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 2,
            totalQuantity: 2,
            buyoutListingCount: 2,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 100,
            quantityAtLowestBuyout: 1,
            priceLevels: $levels,
        );
        $marketData = new ConnectedRealmMarketData(1127, [$identity->getFingerprint() => $summary], 2, 2);

        $economyService = $this->createMock(EconomyService::class);
        $economyService->expects(self::once())
            ->method('connectedRealm')
            ->with(1127, Region::EU)
            ->willReturn($marketData);

        $opportunityService = new OpportunityService($economyService, $this->config);
        $analysis = $opportunityService->connectedRealm(1127);

        $opps = $analysis->all();
        self::assertCount(1, $opps);
        self::assertInstanceOf(AuctionItemOpportunity::class, $opps[0]);
        self::assertSame(60001, $opps[0]->item->id);
    }

    public function testPureAnalyzeCommoditiesWithoutNetwork(): void
    {
        $levels = [
            new PriceLevel(100, 10, 1),
            new PriceLevel(300, 5, 1),
        ];
        $summary = new CommodityMarketSummary(2001, 2, 15, 100, 10, 300, $levels);
        $marketData = new CommodityMarketData(Region::US, [2001 => $summary], 2, 15);

        // EconomyService mock has NO expected calls
        $economyService = $this->createMock(EconomyService::class);
        $economyService->expects(self::never())->method('commodities');
        $economyService->expects(self::never())->method('connectedRealm');

        $opportunityService = new OpportunityService($economyService, $this->config);
        $analysis = $opportunityService->analyzeCommodities($marketData, new AuctionHouseFeePolicy(1000)); // 10% fee

        // Gross = 10 * 300 = 3000. Fee (10%) = 300. Net = 2700. Cost = 1000. Profit = 1700.
        $opps = $analysis->all();
        self::assertCount(1, $opps);
        self::assertSame(1700, $opps[0]->prospectiveProfitCopper);
        self::assertSame(300, $opps[0]->saleFeeCopper);
    }

    public function testPureAnalyzeConnectedRealmWithoutNetwork(): void
    {
        $item = new AuctionItem(70001);
        $identity = MarketItemIdentity::fromAuctionItem($item);
        $levels = [
            new NonCommodityPriceLevel(100, 1, 1, 1),
            new NonCommodityPriceLevel(500, 1, 1, 1),
        ];
        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 2,
            totalQuantity: 2,
            buyoutListingCount: 2,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 100,
            quantityAtLowestBuyout: 1,
            priceLevels: $levels,
        );
        $marketData = new ConnectedRealmMarketData(1127, [$identity->getFingerprint() => $summary], 2, 2);

        $economyService = $this->createMock(EconomyService::class);
        $economyService->expects(self::never())->method('commodities');
        $economyService->expects(self::never())->method('connectedRealm');

        $opportunityService = new OpportunityService($economyService, $this->config);
        $analysis = $opportunityService->analyzeConnectedRealm($marketData);

        $opps = $analysis->all();
        self::assertCount(1, $opps);
        self::assertSame(70001, $opps[0]->item->id);
    }

    public function testCommoditiesConvenienceFetchesEconomyOnceAndAllowsMultipleIterations(): void
    {
        $levels = [
            new PriceLevel(100, 10, 1),
            new PriceLevel(200, 5, 1),
        ];
        $summary = new CommodityMarketSummary(1001, 2, 15, 100, 10, 200, $levels);
        $marketData = new CommodityMarketData(Region::EU, [1001 => $summary], 2, 15);

        $economyService = $this->createMock(EconomyService::class);
        // Assert EconomyService::commodities is called EXACTLY ONCE
        $economyService->expects(self::once())
            ->method('commodities')
            ->with(Region::EU)
            ->willReturn($marketData);

        $opportunityService = new OpportunityService($economyService, $this->config);

        // 1. Convenience method obtains/materializes M7 MarketData once
        $analysis = $opportunityService->commodities();

        // 2. First iteration analyzes that MarketData
        $firstPass = [];
        foreach ($analysis as $opp) {
            $firstPass[] = $opp->itemId;
        }
        self::assertSame([1001], $firstPass);

        // 3. Second iteration analyzes the SAME MarketData again without calling EconomyService again
        $secondPass = [];
        foreach ($analysis as $opp) {
            $secondPass[] = $opp->itemId;
        }
        self::assertSame([1001], $secondPass);
    }

    public function testConnectedRealmConvenienceFetchesEconomyOnceAndAllowsMultipleIterations(): void
    {
        $item = new AuctionItem(60001);
        $identity = MarketItemIdentity::fromAuctionItem($item);
        $levels = [
            new NonCommodityPriceLevel(100, 1, 1, 1),
            new NonCommodityPriceLevel(200, 1, 1, 1),
        ];
        $summary = new AuctionItemMarketSummary(
            item: $item,
            identity: $identity,
            listingCount: 2,
            totalQuantity: 2,
            buyoutListingCount: 2,
            bidOnlyListingCount: 0,
            lowestBuyoutCopper: 100,
            quantityAtLowestBuyout: 1,
            priceLevels: $levels,
        );
        $marketData = new ConnectedRealmMarketData(1127, [$identity->getFingerprint() => $summary], 2, 2);

        $economyService = $this->createMock(EconomyService::class);
        // Assert EconomyService::connectedRealm is called EXACTLY ONCE
        $economyService->expects(self::once())
            ->method('connectedRealm')
            ->with(1127, Region::EU)
            ->willReturn($marketData);

        $opportunityService = new OpportunityService($economyService, $this->config);

        // 1. Convenience method obtains/materializes M7 MarketData once
        $analysis = $opportunityService->connectedRealm(1127);

        // 2. First iteration analyzes that MarketData
        $firstPass = [];
        foreach ($analysis as $opp) {
            $firstPass[] = $opp->item->id;
        }
        self::assertSame([60001], $firstPass);

        // 3. Second iteration analyzes the SAME MarketData again without calling EconomyService again
        $secondPass = [];
        foreach ($analysis as $opp) {
            $secondPass[] = $opp->item->id;
        }
        self::assertSame([60001], $secondPass);
    }
}
