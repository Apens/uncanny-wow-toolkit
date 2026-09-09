<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Hydrator;

use UncannyWoW\Core\Domain\Enum\AuctionTimeLeft;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Model\AuctionHouse\Auction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionHouseSnapshot;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItemModifier;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityAuction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityMarketSnapshot;

final class AuctionHouseHydrator
{
    /**
     * @param array<string, mixed> $data
     */
    public function hydrateAuctions(array $data, int $connectedRealmId): AuctionHouseSnapshot
    {
        if (!isset($data['auctions']) || !is_array($data['auctions'])) {
            throw new InvalidResponseException('Missing or invalid "auctions" list in connected realm auction house payload.');
        }

        $auctions = [];
        foreach ($data['auctions'] as $index => $auctionData) {
            if (!is_array($auctionData)) {
                throw new InvalidResponseException(sprintf('Invalid auction entry at index %s in auction house payload.', (string) $index));
            }

            /** @var array<string, mixed> $auctionData */
            $auctions[] = $this->hydrateAuction($auctionData, $index);
        }

        return new AuctionHouseSnapshot(
            connectedRealmId: $connectedRealmId,
            auctions: $auctions,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function hydrateCommodities(array $data, Region $region): CommodityMarketSnapshot
    {
        if (!isset($data['auctions']) || !is_array($data['auctions'])) {
            throw new InvalidResponseException('Missing or invalid "auctions" list in commodities auction house payload.');
        }

        $auctions = [];
        foreach ($data['auctions'] as $index => $auctionData) {
            if (!is_array($auctionData)) {
                throw new InvalidResponseException(sprintf('Invalid commodity entry at index %s in commodities payload.', (string) $index));
            }

            /** @var array<string, mixed> $auctionData */
            $auctions[] = $this->hydrateCommodityAuction($auctionData, $index);
        }

        return new CommodityMarketSnapshot(
            region: $region,
            auctions: $auctions,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function hydrateAuction(array $data, int|string $index = 0): Auction
    {
        if (!isset($data['id']) || !is_int($data['id'])) {
            throw new InvalidResponseException(sprintf('Missing or invalid "id" at auction index %s.', (string) $index));
        }

        if (!isset($data['item']) || !is_array($data['item'])) {
            throw new InvalidResponseException(sprintf('Missing or invalid "item" at auction index %s.', (string) $index));
        }

        if (!isset($data['quantity']) || !is_int($data['quantity']) || $data['quantity'] <= 0) {
            throw new InvalidResponseException(sprintf('Missing or invalid "quantity" at auction index %s.', (string) $index));
        }

        /** @var array<string, mixed> $itemData */
        $itemData = $data['item'];
        $item = $this->hydrateAuctionItem($itemData, $index);

        $buyoutCopper = isset($data['buyout']) && is_int($data['buyout']) ? $data['buyout'] : null;
        if ($buyoutCopper !== null && $buyoutCopper < 0) {
            throw new InvalidResponseException(sprintf('Invalid negative "buyout" at auction index %s.', (string) $index));
        }

        $bidCopper = isset($data['bid']) && is_int($data['bid']) ? $data['bid'] : null;
        if ($bidCopper !== null && $bidCopper < 0) {
            throw new InvalidResponseException(sprintf('Invalid negative "bid" at auction index %s.', (string) $index));
        }

        $timeLeft = $this->hydrateTimeLeft($data['time_left'] ?? null, $index);

        return new Auction(
            id: $data['id'],
            item: $item,
            quantity: $data['quantity'],
            buyoutCopper: $buyoutCopper,
            bidCopper: $bidCopper,
            timeLeft: $timeLeft,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function hydrateCommodityAuction(array $data, int|string $index = 0): CommodityAuction
    {
        if (!isset($data['id']) || !is_int($data['id'])) {
            throw new InvalidResponseException(sprintf('Missing or invalid "id" at commodity index %s.', (string) $index));
        }

        if (!isset($data['item']) || !is_array($data['item']) || !isset($data['item']['id']) || !is_int($data['item']['id'])) {
            throw new InvalidResponseException(sprintf('Missing or invalid "item.id" at commodity index %s.', (string) $index));
        }

        if (!isset($data['quantity']) || !is_int($data['quantity']) || $data['quantity'] <= 0) {
            throw new InvalidResponseException(sprintf('Missing or invalid "quantity" at commodity index %s.', (string) $index));
        }

        if (!isset($data['unit_price']) || !is_int($data['unit_price']) || $data['unit_price'] < 0) {
            throw new InvalidResponseException(sprintf('Missing or invalid "unit_price" at commodity index %s.', (string) $index));
        }

        $timeLeft = $this->hydrateTimeLeft($data['time_left'] ?? null, $index);

        return new CommodityAuction(
            id: $data['id'],
            itemId: $data['item']['id'],
            quantity: $data['quantity'],
            unitPriceCopper: $data['unit_price'],
            timeLeft: $timeLeft,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateAuctionItem(array $data, int|string $index): AuctionItem
    {
        if (!isset($data['id']) || !is_int($data['id'])) {
            throw new InvalidResponseException(sprintf('Missing or invalid "item.id" at auction index %s.', (string) $index));
        }

        $context = isset($data['context']) && is_int($data['context']) ? $data['context'] : null;

        $bonusLists = [];
        if (isset($data['bonus_lists']) && is_array($data['bonus_lists'])) {
            foreach ($data['bonus_lists'] as $bIndex => $bonusId) {
                if (!is_int($bonusId)) {
                    throw new InvalidResponseException(sprintf('Invalid bonus list entry at index %s for auction index %s.', (string) $bIndex, (string) $index));
                }
                $bonusLists[] = $bonusId;
            }
        }

        $modifiers = [];
        if (isset($data['modifiers']) && is_array($data['modifiers'])) {
            foreach ($data['modifiers'] as $mIndex => $modData) {
                if (!is_array($modData) || !isset($modData['type'], $modData['value']) || !is_int($modData['type']) || !is_int($modData['value'])) {
                    throw new InvalidResponseException(sprintf('Invalid modifier entry at index %s for auction index %s.', (string) $mIndex, (string) $index));
                }
                $modifiers[] = new AuctionItemModifier(
                    type: $modData['type'],
                    value: $modData['value'],
                );
            }
        }

        return new AuctionItem(
            id: $data['id'],
            context: $context,
            bonusLists: $bonusLists,
            modifiers: $modifiers,
            petSpeciesId: isset($data['pet_species_id']) && is_int($data['pet_species_id']) ? $data['pet_species_id'] : null,
            petBreedId: isset($data['pet_breed_id']) && is_int($data['pet_breed_id']) ? $data['pet_breed_id'] : null,
            petLevel: isset($data['pet_level']) && is_int($data['pet_level']) ? $data['pet_level'] : null,
            petQualityId: isset($data['pet_quality_id']) && is_int($data['pet_quality_id']) ? $data['pet_quality_id'] : null,
        );
    }

    private function hydrateTimeLeft(mixed $value, int|string $index): AuctionTimeLeft
    {
        if (!is_string($value)) {
            throw new InvalidResponseException(sprintf('Missing or invalid "time_left" at auction index %s.', (string) $index));
        }

        return AuctionTimeLeft::tryFrom($value)
            ?? throw new InvalidResponseException(sprintf('Unsupported "time_left" value "%s" at auction index %s.', $value, (string) $index));
    }
}
