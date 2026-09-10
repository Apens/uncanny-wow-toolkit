<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Analyzer\CraftingProfitabilityAnalyzer;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityMarketCost;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityOutputTarget;
use UncannyWoW\Core\Domain\Model\Crafting\CrafterState;
use UncannyWoW\Core\Domain\Model\Crafting\CraftPlan;
use UncannyWoW\Core\Domain\Model\Crafting\CurrentLowestAsk;
use UncannyWoW\Core\Domain\Model\Crafting\CustomUnitSalePrice;
use UncannyWoW\Core\Domain\Model\Crafting\FixedUnitCost;
use UncannyWoW\Core\Domain\Model\Crafting\NonCommodityOutputTarget;
use UncannyWoW\Core\Domain\Model\Crafting\OutputPriceStatus;
use UncannyWoW\Core\Domain\Model\Crafting\SelectedReagent;
use UncannyWoW\Core\Domain\Model\Economy\CommodityMarketData;
use UncannyWoW\Core\Domain\Model\Economy\ConnectedRealmMarketData;
use UncannyWoW\Core\Domain\Model\Economy\MarketItemIdentity;
use UncannyWoW\Core\Service\CraftingProfitabilityService;
use UncannyWoW\Core\Service\EconomyService;

#[CoversClass(CraftingProfitabilityService::class)]
final class CraftingProfitabilityServiceTest extends TestCase
{
    public function testZeroEconomyCallsWhenPlanIsFullyCustom(): void
    {
        $economyService = $this->createMock(EconomyService::class);
        $economyService->expects($this->never())->method('commodities');
        $economyService->expects($this->never())->method('connectedRealm');

        $service = new CraftingProfitabilityService($economyService);

        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb', 190315, 5, new FixedUnitCost(100)),
            ],
            salePriceAssumption: new CustomUnitSalePrice(1000),
        );

        $result = $service->evaluateCurrentMarket($plan, CrafterState::none());
        $this->assertTrue($result->isFullyPriced());
    }

    public function testCommodityReagentOnlyCallsCommoditiesExactlyOnce(): void
    {
        $mockMarket = new CommodityMarketData(Region::EU, [], 0, 0);

        $economyService = $this->createMock(EconomyService::class);
        $economyService->expects($this->once())
            ->method('commodities')
            ->with($this->isNull(), 10)
            ->willReturn($mockMarket);
        $economyService->expects($this->never())->method('connectedRealm');

        $service = new CraftingProfitabilityService($economyService);

        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb1', 190315, 5, new CommodityMarketCost()),
                new SelectedReagent('herb2', 190316, 2, new CommodityMarketCost()),
            ],
            salePriceAssumption: new CustomUnitSalePrice(1000),
        );

        $service->evaluateCurrentMarket($plan, CrafterState::none());
    }

    public function testCommodityReagentAndCommodityOutputCallCommoditiesExactlyOnce(): void
    {
        $mockMarket = new CommodityMarketData(Region::EU, [], 0, 0);

        $economyService = $this->createMock(EconomyService::class);
        $economyService->expects($this->once())
            ->method('commodities')
            ->with(Region::EU, 10)
            ->willReturn($mockMarket);
        $economyService->expects($this->never())->method('connectedRealm');

        $service = new CraftingProfitabilityService($economyService);

        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('herb', 190316, 5, new CommodityMarketCost()),
            ],
            salePriceAssumption: new CurrentLowestAsk(),
        );

        $service->evaluateCurrentMarket($plan, CrafterState::none(), region: Region::EU);
    }

    public function testNonCommodityCurrentLowestAskCallsConnectedRealmWhenRealmIdSupplied(): void
    {
        $identity = new MarketItemIdentity(210000);
        $mockConnectedMarket = new ConnectedRealmMarketData(1084, [], 0, 0);

        $economyService = $this->createMock(EconomyService::class);
        $economyService->expects($this->never())->method('commodities');
        $economyService->expects($this->once())
            ->method('connectedRealm')
            ->with(1084, $this->isNull(), 10)
            ->willReturn($mockConnectedMarket);

        $service = new CraftingProfitabilityService($economyService);

        $plan = new CraftPlan(
            output: new NonCommodityOutputTarget($identity),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('ore', 190316, 5, new FixedUnitCost(100)),
            ],
            salePriceAssumption: new CurrentLowestAsk(),
        );

        $result = $service->evaluateCurrentMarket($plan, CrafterState::none(), connectedRealmId: 1084);
        $this->assertFalse($result->isFullyPriced());
        // Market is empty -> MarketUnavailable
        $this->assertSame(OutputPriceStatus::MarketUnavailable, $result->outputResolution->status);
    }

    public function testNonCommodityCurrentLowestAskWithoutRealmIdDoesNotCallConnectedRealm(): void
    {
        $identity = new MarketItemIdentity(210000);

        $economyService = $this->createMock(EconomyService::class);
        $economyService->expects($this->never())->method('commodities');
        $economyService->expects($this->never())->method('connectedRealm');

        $service = new CraftingProfitabilityService($economyService);

        $plan = new CraftPlan(
            output: new NonCommodityOutputTarget($identity),
            baseOutputQuantity: 1,
            reagents: [
                new SelectedReagent('ore', 190316, 5, new FixedUnitCost(100)),
            ],
            salePriceAssumption: new CurrentLowestAsk(),
        );

        $result = $service->evaluateCurrentMarket($plan, CrafterState::none(), connectedRealmId: null);
        $this->assertFalse($result->isFullyPriced());
        $this->assertSame(OutputPriceStatus::MarketDataRequired, $result->outputResolution->status);
    }

    public function testPureEvaluatePassThrough(): void
    {
        $economyService = $this->createStub(EconomyService::class);
        $service = new CraftingProfitabilityService($economyService);

        $plan = new CraftPlan(
            output: new CommodityOutputTarget(190315),
            baseOutputQuantity: 1,
            reagents: [],
            salePriceAssumption: new CustomUnitSalePrice(200),
        );

        $result = $service->evaluate($plan, CrafterState::none());
        $this->assertTrue($result->isFullyPriced());
    }
}
