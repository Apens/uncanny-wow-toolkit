<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Hydrator;

use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Model\Recipe\Recipe;
use UncannyWoW\Core\Domain\Model\Recipe\RecipeCraftedQuantity;
use UncannyWoW\Core\Domain\Model\Recipe\RecipeModifiedCraftingSlot;
use UncannyWoW\Core\Domain\Model\Recipe\RecipeReagent;

final class RecipeHydrator
{
    /**
     * Hydrate a Blizzard recipe payload into a canonical Recipe domain model.
     *
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data): Recipe
    {
        if (!isset($data['id']) || !is_int($data['id'])) {
            throw new InvalidResponseException('Missing or invalid "id" in recipe payload.');
        }

        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            throw new InvalidResponseException('Missing or invalid "name" in recipe payload.');
        }

        $craftedItemId = null;
        $craftedItemName = null;
        if (isset($data['crafted_item'])) {
            if (!is_array($data['crafted_item'])) {
                throw new InvalidResponseException('Invalid "crafted_item" in recipe payload: expected object.');
            }

            if (!isset($data['crafted_item']['id']) || !is_int($data['crafted_item']['id'])) {
                throw new InvalidResponseException('Missing or invalid "id" in recipe crafted_item payload.');
            }

            $craftedItemId = $data['crafted_item']['id'];
            $craftedItemName = isset($data['crafted_item']['name']) && is_string($data['crafted_item']['name'])
                ? $data['crafted_item']['name']
                : null;
        }

        $craftedQuantity = $this->hydrateCraftedQuantity($data);

        $reagents = [];
        if (isset($data['reagents'])) {
            if (!is_array($data['reagents'])) {
                throw new InvalidResponseException('Invalid "reagents" in recipe payload: expected array.');
            }

            foreach ($data['reagents'] as $index => $reagentData) {
                if (!is_array($reagentData)) {
                    throw new InvalidResponseException(sprintf('Invalid reagent entry at index %d in recipe payload.', $index));
                }

                if (!isset($reagentData['reagent']) || !is_array($reagentData['reagent']) || !isset($reagentData['reagent']['id']) || !is_int($reagentData['reagent']['id'])) {
                    throw new InvalidResponseException(sprintf('Missing or invalid "reagent.id" at index %d in recipe payload.', $index));
                }

                $reagentName = isset($reagentData['reagent']['name']) && is_string($reagentData['reagent']['name'])
                    ? $reagentData['reagent']['name']
                    : '';

                if (!isset($reagentData['quantity']) || !is_int($reagentData['quantity']) || $reagentData['quantity'] <= 0) {
                    throw new InvalidResponseException(sprintf('Missing or invalid positive "quantity" at index %d in recipe payload.', $index));
                }

                $reagents[] = new RecipeReagent(
                    itemId: $reagentData['reagent']['id'],
                    name: $reagentName,
                    quantity: $reagentData['quantity'],
                );
            }
        }

        $modifiedCraftingSlots = $this->hydrateModifiedCraftingSlots($data);

        $description = isset($data['description']) && is_string($data['description']) && trim($data['description']) !== ''
            ? $data['description']
            : null;

        return new Recipe(
            id: $data['id'],
            name: $data['name'],
            craftedItemId: $craftedItemId,
            craftedItemName: $craftedItemName,
            craftedQuantity: $craftedQuantity,
            reagents: $reagents,
            modifiedCraftingSlots: $modifiedCraftingSlots,
            description: $description,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return list<RecipeModifiedCraftingSlot>
     */
    private function hydrateModifiedCraftingSlots(array $data): array
    {
        if (!array_key_exists('modified_crafting_slots', $data)) {
            return [];
        }

        if (!is_array($data['modified_crafting_slots'])) {
            throw new InvalidResponseException('Invalid "modified_crafting_slots" in recipe payload: expected array.');
        }

        $slots = [];
        foreach ($data['modified_crafting_slots'] as $index => $slotData) {
            if (!is_array($slotData)) {
                throw new InvalidResponseException(sprintf('Invalid modified crafting slot at index %d: expected object.', $index));
            }

            if (
                !isset($slotData['slot_type'])
                || !is_array($slotData['slot_type'])
                || !isset($slotData['slot_type']['id'])
                || !is_int($slotData['slot_type']['id'])
                || $slotData['slot_type']['id'] <= 0
            ) {
                throw new InvalidResponseException(sprintf('Missing or invalid positive "slot_type.id" at index %d in modified crafting slots.', $index));
            }

            $slotName = isset($slotData['slot_type']['name']) && is_string($slotData['slot_type']['name'])
                ? $slotData['slot_type']['name']
                : '';

            if (
                !array_key_exists('display_order', $slotData)
                || !is_int($slotData['display_order'])
                || $slotData['display_order'] < 0
            ) {
                throw new InvalidResponseException(sprintf('Missing or invalid non-negative "display_order" at index %d in modified crafting slots.', $index));
            }

            $slots[] = new RecipeModifiedCraftingSlot(
                slotTypeId: $slotData['slot_type']['id'],
                name: $slotName,
                displayOrder: $slotData['display_order'],
            );
        }

        return $slots;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateCraftedQuantity(array $data): RecipeCraftedQuantity
    {
        // CASE 1: crafted_quantity key is absent or explicitly null -> RecipeCraftedQuantity::unknown()
        if (!array_key_exists('crafted_quantity', $data) || $data['crafted_quantity'] === null) {
            return RecipeCraftedQuantity::unknown();
        }

        $rawQuantity = $data['crafted_quantity'];
        if (!is_array($rawQuantity)) {
            throw new InvalidResponseException('Invalid "crafted_quantity" in recipe payload: expected object or null.');
        }

        // CASE 2: crafted_quantity.value exists as valid positive integer
        if (isset($rawQuantity['value'])) {
            if (!is_int($rawQuantity['value']) || $rawQuantity['value'] <= 0) {
                throw new InvalidResponseException('Invalid "crafted_quantity.value": expected positive integer.');
            }

            return RecipeCraftedQuantity::fixed($rawQuantity['value']);
        }

        // CASE 3: crafted_quantity.minimum AND crafted_quantity.maximum exist as valid positive integers
        if (isset($rawQuantity['minimum']) || isset($rawQuantity['maximum'])) {
            if (
                !isset($rawQuantity['minimum'])
                || !isset($rawQuantity['maximum'])
                || !is_int($rawQuantity['minimum'])
                || !is_int($rawQuantity['maximum'])
                || $rawQuantity['minimum'] <= 0
                || $rawQuantity['maximum'] < $rawQuantity['minimum']
            ) {
                throw new InvalidResponseException('Invalid "crafted_quantity" range: expected positive integers with minimum <= maximum.');
            }

            return $rawQuantity['minimum'] === $rawQuantity['maximum']
                ? RecipeCraftedQuantity::fixed($rawQuantity['minimum'])
                : RecipeCraftedQuantity::range($rawQuantity['minimum'], $rawQuantity['maximum']);
        }

        // CASE 4: Present but empty, unsupported, or contradictory structure -> throw HydrationException
        throw new InvalidResponseException('Malformed "crafted_quantity" in recipe payload: neither "value" nor "minimum"/"maximum" was provided.');
    }
}
