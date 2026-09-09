<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\AuctionHouse;

final readonly class AuctionItem
{
    /**
     * @param list<int> $bonusLists
     * @param list<AuctionItemModifier> $modifiers
     */
    public function __construct(
        public int $id,
        public ?int $context = null,
        public array $bonusLists = [],
        public array $modifiers = [],
        public ?int $petSpeciesId = null,
        public ?int $petBreedId = null,
        public ?int $petLevel = null,
        public ?int $petQualityId = null,
    ) {}
}
