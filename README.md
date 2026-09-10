Uncanny-WoW Toolkit




A modern PHP toolkit for building applications around World of Warcraft.

Write Warcraft code, not integration code.

Uncanny-WoW Toolkit aims to make it easier to build PHP applications that use World of Warcraft data without having to deal directly with authentication flows, raw HTTP endpoints, response parsing, or repetitive integration code.

The project is currently in early development.

Quick start

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\Psr18Client;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\UncannyWoWClient;

$psr17Factory = new Psr17Factory();
$httpClient = new Psr18Client();

$wow = UncannyWoWClient::create(
    clientId: 'my-blizzard-client-id',
    clientSecret: 'my-blizzard-client-secret',
    httpClient: $httpClient,
    requestFactory: $psr17Factory,
    streamFactory: $psr17Factory,
    defaultRegion: Region::EU,
);

// Discover a realm and its canonical slug by display name
$matches = $wow->realms()->search(name: 'La Croisade écarlate');
$realm = $matches[0];
echo $realm->slug;                    // "la-croisade-écarlate"

// Or fetch realm details directly by canonical slug
$realm = $wow->realms()->get(slug: 'la-croisade-écarlate');
echo $realm->name;                    // "La Croisade écarlate"
echo $realm->timezone;                // "Europe/Paris"

$character = $wow->characters()->profile(
    realmSlug: $realm->slug,
    name: 'norigosa',
);

echo $character->name;                // "Norigosa"
echo $character->level;               // 80
echo $character->realm->name;         // "La Croisade écarlate"
echo $character->playableClass->name; // "Mage"
echo $character->faction->value;      // "HORDE"

// Fetch item metadata by Item ID
$item = $wow->items()->get(id: 19019);
echo $item->name;                     // "Lame-tonnerre, épée bénie du Cherchevent"
echo $item->quality->value;           // "legendary"
echo $item->level;                    // 29

// Fetch connected realm cluster details by Connected Realm ID
$connectedRealm = $wow->connectedRealms()->get(id: 1127);
echo $connectedRealm->id;             // 1127
foreach ($connectedRealm->realms as $memberRealm) {
    echo $memberRealm->name;          // "La Croisade écarlate", "Culte de la Rive noire", etc.
}

// Fetch connected realm non-commodity auctions snapshot (streamed lazily, single-pass)
$ahSnapshot = $wow->auctionHouse()->auctions(connectedRealmId: 1127);
foreach ($ahSnapshot as $auction) {
    echo $auction->id;
    echo $auction->item->id;         // 19019
    echo $auction->buyoutCopper;     // e.g. 50000000 (all prices strictly in copper)
    echo $auction->timeLeft->value;  // "VERY_LONG"
    break; // Stream yields lazily without loading the entire payload into memory
}

// Fetch region-wide commodity auctions snapshot (streamed lazily, single-pass)
$commoditySnapshot = $wow->auctionHouse()->commodities();
foreach ($commoditySnapshot as $commodity) {
    echo $commodity->id;
    echo $commodity->itemId;          // 190381
    echo $commodity->unitPriceCopper; // 12500 (copper)
    echo $commodity->quantity;
    break;
}

// Note: Auction House snapshots are streamed and strictly single-pass to maintain
// a flat ~6 MB memory footprint even on payloads with 370,000+ listings.
// Attempting to iterate a snapshot a second time will throw a \LogicException.

// Fetch aggregated commodity market data (summarized in one streaming pass)
$commodityMarket = $wow->economy()->commodities();
$summary = $commodityMarket->get(190381);
if ($summary !== null) {
    echo $summary->totalQuantity;         // Total units on regional market
    echo $summary->lowestUnitPriceCopper; // e.g. 12500 copper
    echo $summary->highestUnitPriceCopper;
    foreach ($summary->priceLevels as $level) {
        echo sprintf("%d copper: %d units across %d listings\n", $level->priceCopper, $level->quantity, $level->listingCount);
    }
}

// Fetch aggregated connected realm market data (variant-aware non-commodities)
$realmMarket = $wow->economy()->connectedRealm(connectedRealmId: 1127);
$thunderfurySummaries = $realmMarket->getByItemId(19019);
foreach ($thunderfurySummaries as $itemSummary) {
    echo $itemSummary->lowestBuyoutCopper;
    echo $itemSummary->totalQuantity;
}

// Note: Economy aggregation scales with unique market item cardinality and retained price depth (1..10, default 5).
// Live validation on 381k+ regional commodities (11.9k unique items) peaked at ~100 MB,
// and 41k+ connected realm listings (25.7k unique variants) peaked at ~88 MB under a standard 256M limit.

// Analyze structural opportunity candidates lazily (Milestone 8)
// Computes exact integer-safe price spreads, conservative 5% AH fee deduction, and exact ROI.
$commodityOpps = $wow->opportunities()->commodities();
foreach ($commodityOpps as $opp) {
    echo sprintf(
        "Item: %d | Clear %d units for %s c | Target: %s c | Net Profit: %s c | ROI: %.2f%%\n",
        $opp->itemId,
        $opp->acquisitionQuantity,
        number_format($opp->acquisitionCostCopper),
        number_format($opp->targetUnitPriceCopper),
        number_format($opp->prospectiveProfitCopper),
        $opp->roi->toBasisPoints() / 100,
    );
    break; // Pure generator stream with O(1) memory
}

// Dedicated sorting and non-overlapping strategy deduplication
$bestNonCommodities = $wow->opportunities()
    ->connectedRealm(1127)
    ->distinctByHighestProfit(); // O(U) memory: exactly 1 non-overlapping candidate per variant

// Note: Structural opportunity analysis reflects observed snapshot spreads and prospective resale after AH fee.
// It does not guarantee sales or model historical velocity. Live validation on Connected Realm 1127 (1,924 candidate
// opportunities, 1,569 distinct variants) peaked at ~90 MB; EU commodities (20,955 opportunities, 8,824 distinct items)
// peaked at ~104 MB under a standard 256M limit.

// Fetch recipes and professions metadata (Milestone 9A)
$recipe = $wow->recipes()->get(id: 52686);
echo $recipe->name;               // "Potion of Recklessness"
if ($recipe->hasCraftedItemReference()) {
    echo $recipe->craftedItemId;
}
foreach ($recipe->modifiedCraftingSlots as $slot) {
    echo sprintf("[%d] %s (order: %d)\n", $slot->slotTypeId, $slot->name, $slot->displayOrder);
}

$profession = $wow->professions()->get(id: 171);
echo $profession->name;           // "Alchemy"

$skillTier = $wow->professions()->skillTier(professionId: 171, skillTierId: 2822);
echo $skillTier->name;            // "Khaz Algar Alchemy"
$allRecipeIds = $skillTier->getAllRecipeIds();

// Crafting Profitability Analysis (Milestone 9B)
// Evaluates exact economic outcomes for a caller-supplied CraftPlan.
// Modern Midnight recipes omit crafted output IDs and quantities, routing materials through modified crafting slots.
// CraftPlan is caller-authoritative; the engine never reconstructs missing Blizzard crafting data.
use UncannyWoW\Core\Domain\Math\ExactFraction;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityMarketCost;
use UncannyWoW\Core\Domain\Model\Crafting\CommodityOutputTarget;
use UncannyWoW\Core\Domain\Model\Crafting\CrafterState;
use UncannyWoW\Core\Domain\Model\Crafting\CraftPlan;
use UncannyWoW\Core\Domain\Model\Crafting\CurrentLowestAsk;
use UncannyWoW\Core\Domain\Model\Crafting\SelectedReagent;

$plan = new CraftPlan(
    output: new CommodityOutputTarget(itemId: 212241), // Algari Mana Potion
    baseOutputQuantity: 2,
    reagents: [
        new SelectedReagent('herb', 210796, 5, new CommodityMarketCost()), // Mycobloom
    ],
    salePriceAssumption: new CurrentLowestAsk(),
    baseConcentrationCost: 50,
);

$crafterState = new CrafterState(
    resourcefulnessSavings: ['herb' => ExactFraction::of(1, 2)], // 0.5 units expected saved
    multicraftExtraOutput: ExactFraction::of(3, 10),              // 0.3 bonus units expected
    ingenuityConcentrationRefund: ExactFraction::of(15, 1),       // 15 concentration refund
);

// Offline pure analysis (0 network calls with pre-fetched markets)
// or current market analysis (fetches regional commodity market at most once):
$result = $wow->crafting()->evaluateCurrentMarket($plan, $crafterState);

if ($result->isFullyPriced()) {
    // Base deterministic economics (0 procs)
    echo $result->baseEconomics->baseMaterialCostCopper;
    echo $result->baseEconomics->baseNetProfitCopper;
    echo $result->baseEconomics->baseRoi->toBasisPoints(); // e.g. -1000 bps = -10.00%

    // Expected economics (exact rational arithmetic accounting for crafter stats)
    echo (string) $result->expectedEconomics->expectedNetProfitCopper;
    echo (string) $result->expectedEconomics->profitPerConcentration;
}

// Note: Crafting profitability operates on caller-supplied CraftPlans and effective crafter expectations.
// Live validation on EU regional commodities confirmed exact rational evaluations (e.g. 5 Mycobloom for 2 Algari Mana Potion,
// -10.00% base ROI vs +15.00% expected ROI with procs) running in ~5.2 ms with ~44 MB peak memory under a 256 MB limit.
```

Goals

Provide a clean, object-oriented PHP API for World of Warcraft data.

Hide low-level HTTP and OAuth complexity whenever possible.

Use strict typing and predictable domain objects.

Provide caching support to reduce unnecessary external API calls.

Remain usable outside Symfony while integrating cleanly with it.

Build reusable foundations for websites, dashboards, bots, CLI tools, and other WoW-related applications.

Planned capabilities

The toolkit is intended to grow progressively around reusable modules such as:

Blizzard API integration

Characters

Guilds

Items

Raids and encounters

Dungeons and Mythic+

Auction House and economy data

External market-data providers

Caching and synchronization helpers

Only generic and reusable functionality belongs in the toolkit. Application-specific business logic should stay in the applications that consume it.

Project status

Uncanny-WoW Toolkit is at the beginning of its development.

The first milestone is to establish a reliable foundation for connecting to Blizzard's APIs, handling authentication, retrieving data, and exposing it through a clean PHP interface.

Vision

The broader goals and design principles of the project will be documented in VISION.md.

License

This project is licensed under the MIT License.
