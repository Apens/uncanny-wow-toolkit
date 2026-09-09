<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Hydrator;

use UncannyWoW\Core\Domain\Enum\ItemQuality;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Model\Item\InventoryType;
use UncannyWoW\Core\Domain\Model\Item\Item;
use UncannyWoW\Core\Domain\Model\Item\ItemClass;
use UncannyWoW\Core\Domain\Model\Item\ItemSubclass;

final class ItemHydrator
{
    /**
     * Map of Blizzard item quality type codes to ItemQuality enum cases.
     *
     * @var array<string, ItemQuality>
     */
    private const QUALITY_MAP = [
        'POOR' => ItemQuality::POOR,
        'COMMON' => ItemQuality::COMMON,
        'UNCOMMON' => ItemQuality::UNCOMMON,
        'RARE' => ItemQuality::RARE,
        'EPIC' => ItemQuality::EPIC,
        'LEGENDARY' => ItemQuality::LEGENDARY,
        'ARTIFACT' => ItemQuality::ARTIFACT,
        'HEIRLOOM' => ItemQuality::HEIRLOOM,
        'WOW_TOKEN' => ItemQuality::WOW_TOKEN,
    ];

    /**
     * Hydrate a Blizzard item payload into a canonical Item domain model.
     *
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data): Item
    {
        if (!isset($data['id']) || !is_int($data['id'])) {
            throw new InvalidResponseException('Missing or invalid "id" in item payload.');
        }

        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            throw new InvalidResponseException('Missing or invalid "name" in item payload.');
        }

        if (!isset($data['quality']) || !is_array($data['quality']) || !isset($data['quality']['type']) || !is_string($data['quality']['type'])) {
            throw new InvalidResponseException('Missing or invalid "quality" in item payload.');
        }

        $qualityType = trim($data['quality']['type']);
        $quality = self::QUALITY_MAP[$qualityType] ?? null;

        if ($quality === null) {
            throw new InvalidResponseException(sprintf('Unsupported or unknown Blizzard item quality "%s".', $qualityType));
        }

        $level = isset($data['level']) && is_int($data['level']) ? $data['level'] : 0;
        $requiredLevel = isset($data['required_level']) && is_int($data['required_level']) ? $data['required_level'] : 0;

        $itemClass = null;
        if (isset($data['item_class']) && is_array($data['item_class']) && isset($data['item_class']['id']) && is_int($data['item_class']['id']) && isset($data['item_class']['name']) && is_string($data['item_class']['name'])) {
            $itemClass = new ItemClass(
                id: $data['item_class']['id'],
                name: $data['item_class']['name'],
            );
        }

        $itemSubclass = null;
        if (isset($data['item_subclass']) && is_array($data['item_subclass']) && isset($data['item_subclass']['id']) && is_int($data['item_subclass']['id']) && isset($data['item_subclass']['name']) && is_string($data['item_subclass']['name'])) {
            $itemSubclass = new ItemSubclass(
                id: $data['item_subclass']['id'],
                name: $data['item_subclass']['name'],
            );
        }

        $inventoryType = null;
        if (isset($data['inventory_type']) && is_array($data['inventory_type']) && isset($data['inventory_type']['type']) && is_string($data['inventory_type']['type']) && isset($data['inventory_type']['name']) && is_string($data['inventory_type']['name'])) {
            $inventoryType = new InventoryType(
                type: $data['inventory_type']['type'],
                name: $data['inventory_type']['name'],
            );
        }

        $maxCount = isset($data['max_count']) && is_int($data['max_count']) ? $data['max_count'] : 1;
        $isEquippable = isset($data['is_equippable']) && is_bool($data['is_equippable']) ? $data['is_equippable'] : false;

        return new Item(
            id: $data['id'],
            name: $data['name'],
            quality: $quality,
            level: $level,
            requiredLevel: $requiredLevel,
            itemClass: $itemClass,
            itemSubclass: $itemSubclass,
            inventoryType: $inventoryType,
            maxCount: $maxCount,
            isEquippable: $isEquippable,
        );
    }
}
