<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Hydrator;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\AuctionTimeLeft;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Provider\Blizzard\Hydrator\AuctionHouseHydrator;

final class AuctionHouseHydratorTest extends TestCase
{
    private AuctionHouseHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new AuctionHouseHydrator();
    }

    public function testHydrateAuctionsCompleteValidPayload(): void
    {
        $data = [
            'auctions' => [
                [
                    'id' => 10000001,
                    'item' => [
                        'id' => 19019,
                        'context' => 0,
                        'bonus_lists' => [1487, 6652],
                        'modifiers' => [
                            ['type' => 28, 'value' => 156],
                        ],
                    ],
                    'quantity' => 1,
                    'buyout' => 50000000,
                    'bid' => 45000000,
                    'time_left' => 'VERY_LONG',
                ],
                [
                    'id' => 10000002,
                    'item' => [
                        'id' => 210781,
                        'pet_species_id' => 256,
                        'pet_breed_id' => 3,
                        'pet_level' => 25,
                        'pet_quality_id' => 3,
                    ],
                    'quantity' => 1,
                    'buyout' => 1250000,
                    'bid' => null,
                    'time_left' => 'LONG',
                ],
                [
                    'id' => 10000003,
                    'item' => ['id' => 124105],
                    'quantity' => 20,
                    'bid' => 200000,
                    'time_left' => 'SHORT',
                ],
                [
                    'id' => 10000004,
                    'item' => ['id' => 168487],
                    'quantity' => 5,
                    'buyout' => 150000,
                    'bid' => 100000,
                    'time_left' => 'MEDIUM',
                ],
            ],
        ];

        $snapshot = $this->hydrator->hydrateAuctions($data, 1127);

        self::assertSame(1127, $snapshot->connectedRealmId);
        $auctions = is_array($snapshot->auctions) ? $snapshot->auctions : iterator_to_array($snapshot->auctions);
        self::assertCount(4, $auctions);

        // Auction 1: complex item with modifiers & bonus lists
        $auc1 = $auctions[0];
        self::assertSame(10000001, $auc1->id);
        self::assertSame(19019, $auc1->item->id);
        self::assertSame(0, $auc1->item->context);
        self::assertSame([1487, 6652], $auc1->item->bonusLists);
        self::assertCount(1, $auc1->item->modifiers);
        self::assertSame(28, $auc1->item->modifiers[0]->type);
        self::assertSame(156, $auc1->item->modifiers[0]->value);
        self::assertSame(1, $auc1->quantity);
        self::assertSame(50000000, $auc1->buyoutCopper);
        self::assertSame(45000000, $auc1->bidCopper);
        self::assertSame(AuctionTimeLeft::VERY_LONG, $auc1->timeLeft);

        // Auction 2: pet fields
        $auc2 = $auctions[1];
        self::assertSame(10000002, $auc2->id);
        self::assertSame(210781, $auc2->item->id);
        self::assertSame(256, $auc2->item->petSpeciesId);
        self::assertSame(3, $auc2->item->petBreedId);
        self::assertSame(25, $auc2->item->petLevel);
        self::assertSame(3, $auc2->item->petQualityId);
        self::assertSame(1250000, $auc2->buyoutCopper);
        self::assertNull($auc2->bidCopper);
        self::assertSame(AuctionTimeLeft::LONG, $auc2->timeLeft);

        // Auction 3: bid only
        $auc3 = $auctions[2];
        self::assertSame(10000003, $auc3->id);
        self::assertSame(124105, $auc3->item->id);
        self::assertSame(20, $auc3->quantity);
        self::assertNull($auc3->buyoutCopper);
        self::assertSame(200000, $auc3->bidCopper);
        self::assertSame(AuctionTimeLeft::SHORT, $auc3->timeLeft);

        // Auction 4: medium duration
        $auc4 = $auctions[3];
        self::assertSame(10000004, $auc4->id);
        self::assertSame(168487, $auc4->item->id);
        self::assertSame(AuctionTimeLeft::MEDIUM, $auc4->timeLeft);
    }

    public function testHydrateCommoditiesCompleteValidPayload(): void
    {
        $data = [
            'auctions' => [
                [
                    'id' => 20000001,
                    'item' => ['id' => 190381],
                    'quantity' => 500,
                    'unit_price' => 12500,
                    'time_left' => 'VERY_LONG',
                ],
                [
                    'id' => 20000002,
                    'item' => ['id' => 190381],
                    'quantity' => 1000,
                    'unit_price' => 12600,
                    'time_left' => 'LONG',
                ],
            ],
        ];

        $snapshot = $this->hydrator->hydrateCommodities($data, Region::EU);

        self::assertSame(Region::EU, $snapshot->region);
        $auctions = is_array($snapshot->auctions) ? $snapshot->auctions : iterator_to_array($snapshot->auctions);
        self::assertCount(2, $auctions);

        self::assertSame(20000001, $auctions[0]->id);
        self::assertSame(190381, $auctions[0]->itemId);
        self::assertSame(500, $auctions[0]->quantity);
        self::assertSame(12500, $auctions[0]->unitPriceCopper);
        self::assertSame(AuctionTimeLeft::VERY_LONG, $auctions[0]->timeLeft);

        self::assertSame(20000002, $auctions[1]->id);
        self::assertSame(190381, $auctions[1]->itemId);
        self::assertSame(1000, $auctions[1]->quantity);
        self::assertSame(12600, $auctions[1]->unitPriceCopper);
        self::assertSame(AuctionTimeLeft::LONG, $auctions[1]->timeLeft);
    }

    public function testHydrateAuctionsMissingAuctionsKeyThrowsException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "auctions" list in connected realm auction house payload.');

        $this->hydrator->hydrateAuctions([], 1127);
    }

    public function testHydrateAuctionsInvalidAuctionEntryThrowsException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Invalid auction entry at index 0 in auction house payload.');

        $this->hydrator->hydrateAuctions(['auctions' => ['not-an-array']], 1127);
    }

    public function testHydrateAuctionsMissingIdThrowsException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "id" at auction index 0.');

        $this->hydrator->hydrateAuctions(['auctions' => [['item' => ['id' => 123], 'quantity' => 1]]], 1127);
    }

    public function testHydrateAuctionsMissingItemThrowsException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "item" at auction index 0.');

        $this->hydrator->hydrateAuctions(['auctions' => [['id' => 1, 'quantity' => 1]]], 1127);
    }

    public function testHydrateAuctionsInvalidQuantityThrowsException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "quantity" at auction index 0.');

        $this->hydrator->hydrateAuctions(['auctions' => [['id' => 1, 'item' => ['id' => 123], 'quantity' => 0]]], 1127);
    }

    public function testHydrateAuctionsNegativeBuyoutThrowsException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Invalid negative "buyout" at auction index 0.');

        $this->hydrator->hydrateAuctions(['auctions' => [['id' => 1, 'item' => ['id' => 123], 'quantity' => 1, 'buyout' => -10]]], 1127);
    }

    public function testHydrateAuctionsInvalidTimeLeftThrowsException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Unsupported "time_left" value "FOREVER" at auction index 0.');

        $this->hydrator->hydrateAuctions([
            'auctions' => [
                [
                    'id' => 1,
                    'item' => ['id' => 123],
                    'quantity' => 1,
                    'time_left' => 'FOREVER',
                ],
            ],
        ], 1127);
    }

    public function testHydrateCommoditiesMissingItemIdThrowsException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "item.id" at commodity index 0.');

        $this->hydrator->hydrateCommodities([
            'auctions' => [
                [
                    'id' => 1,
                    'item' => [],
                    'quantity' => 1,
                    'unit_price' => 100,
                    'time_left' => 'SHORT',
                ],
            ],
        ], Region::EU);
    }

    public function testHydrateSingleAuctionRow(): void
    {
        $row = [
            'id' => 10000001,
            'item' => [
                'id' => 19019,
                'context' => 0,
                'bonus_lists' => [1487, 6652],
                'modifiers' => [
                    ['type' => 28, 'value' => 156],
                ],
            ],
            'quantity' => 1,
            'buyout' => 50000000,
            'bid' => 45000000,
            'time_left' => 'VERY_LONG',
        ];

        $auction = $this->hydrator->hydrateAuction($row);

        self::assertSame(10000001, $auction->id);
        self::assertSame(19019, $auction->item->id);
        self::assertSame(50000000, $auction->buyoutCopper);
        self::assertSame(45000000, $auction->bidCopper);
        self::assertSame(AuctionTimeLeft::VERY_LONG, $auction->timeLeft);
    }

    public function testHydrateSingleCommodityRow(): void
    {
        $row = [
            'id' => 20000001,
            'item' => ['id' => 190381],
            'quantity' => 500,
            'unit_price' => 12500,
            'time_left' => 'VERY_LONG',
        ];

        $commodity = $this->hydrator->hydrateCommodityAuction($row);

        self::assertSame(20000001, $commodity->id);
        self::assertSame(190381, $commodity->itemId);
        self::assertSame(500, $commodity->quantity);
        self::assertSame(12500, $commodity->unitPriceCopper);
        self::assertSame(AuctionTimeLeft::VERY_LONG, $commodity->timeLeft);
    }
}
