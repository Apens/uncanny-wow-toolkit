<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Economy;

use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItemModifier;

/**
 * Immutable semantic identity for non-commodity market items.
 *
 * Guarantees that distinct item variants (bonus lists, modifiers, pet attributes)
 * are not collapsed into identical market summaries.
 */
final readonly class MarketItemIdentity
{
    private string $fingerprint;

    /**
     * @param list<int> $bonusLists
     * @param list<AuctionItemModifier> $modifiers
     */
    public function __construct(
        public int $itemId,
        public ?int $context = null,
        public array $bonusLists = [],
        public array $modifiers = [],
        public ?int $petSpeciesId = null,
        public ?int $petBreedId = null,
        public ?int $petLevel = null,
        public ?int $petQualityId = null,
    ) {
        $this->fingerprint = $this->generateFingerprint();
    }

    public static function fromAuctionItem(AuctionItem $item): self
    {
        return new self(
            itemId: $item->id,
            context: $item->context,
            bonusLists: $item->bonusLists,
            modifiers: $item->modifiers,
            petSpeciesId: $item->petSpeciesId,
            petBreedId: $item->petBreedId,
            petLevel: $item->petLevel,
            petQualityId: $item->petQualityId,
        );
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function equals(self $other): bool
    {
        return $this->fingerprint === $other->fingerprint;
    }

    private function generateFingerprint(): string
    {
        $mods = [];
        foreach ($this->modifiers as $mod) {
            $mods[] = [$mod->type, $mod->value];
        }

        $payload = [
            'id' => $this->itemId,
            'ctx' => $this->context,
            'bl' => $this->bonusLists,
            'mod' => $mods,
            'pet' => [
                's' => $this->petSpeciesId,
                'b' => $this->petBreedId,
                'l' => $this->petLevel,
                'q' => $this->petQualityId,
            ],
        ];

        return (string) json_encode($payload, \JSON_THROW_ON_ERROR);
    }
}
