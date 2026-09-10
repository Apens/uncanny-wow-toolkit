<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Hydrator;

use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Model\Profession\Profession;
use UncannyWoW\Core\Domain\Model\Profession\ProfessionSkillTierSummary;
use UncannyWoW\Core\Domain\Model\Profession\SkillTier;
use UncannyWoW\Core\Domain\Model\Profession\SkillTierCategory;
use UncannyWoW\Core\Domain\Model\Profession\SkillTierRecipeSummary;

final class ProfessionHydrator
{
    /**
     * Hydrate a Blizzard profession payload into a Profession domain model.
     *
     * @param array<string, mixed> $data
     */
    public function hydrateProfession(array $data): Profession
    {
        if (!isset($data['id']) || !is_int($data['id'])) {
            throw new InvalidResponseException('Missing or invalid "id" in profession payload.');
        }

        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            throw new InvalidResponseException('Missing or invalid "name" in profession payload.');
        }

        $description = isset($data['description']) && is_string($data['description']) && trim($data['description']) !== ''
            ? $data['description']
            : null;

        $skillTiers = [];
        if (isset($data['skill_tiers'])) {
            if (!is_array($data['skill_tiers'])) {
                throw new InvalidResponseException('Invalid "skill_tiers" in profession payload: expected array.');
            }

            foreach ($data['skill_tiers'] as $index => $tierData) {
                if (!is_array($tierData) || !isset($tierData['id']) || !is_int($tierData['id'])) {
                    throw new InvalidResponseException(sprintf('Missing or invalid "id" at skill_tiers index %d.', $index));
                }

                $tierName = isset($tierData['name']) && is_string($tierData['name']) ? $tierData['name'] : '';

                $skillTiers[] = new ProfessionSkillTierSummary(
                    id: $tierData['id'],
                    name: $tierName,
                );
            }
        }

        return new Profession(
            id: $data['id'],
            name: $data['name'],
            description: $description,
            skillTiers: $skillTiers,
        );
    }

    /**
     * Hydrate a Blizzard skill tier payload into a SkillTier domain model.
     *
     * @param array<string, mixed> $data
     * @param int $professionId Known parent profession ID.
     */
    public function hydrateSkillTier(array $data, int $professionId): SkillTier
    {
        if (!isset($data['id']) || !is_int($data['id'])) {
            throw new InvalidResponseException('Missing or invalid "id" in skill tier payload.');
        }

        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            throw new InvalidResponseException('Missing or invalid "name" in skill tier payload.');
        }

        $minSkill = isset($data['minimum_skill_level']) && is_int($data['minimum_skill_level'])
            ? $data['minimum_skill_level']
            : 1;

        $maxSkill = isset($data['maximum_skill_level']) && is_int($data['maximum_skill_level'])
            ? $data['maximum_skill_level']
            : $minSkill;

        $categories = [];
        if (isset($data['categories'])) {
            if (!is_array($data['categories'])) {
                throw new InvalidResponseException('Invalid "categories" in skill tier payload: expected array.');
            }

            foreach ($data['categories'] as $catIndex => $categoryData) {
                if (!is_array($categoryData) || !isset($categoryData['name']) || !is_string($categoryData['name'])) {
                    throw new InvalidResponseException(sprintf('Missing or invalid "name" at categories index %d.', $catIndex));
                }

                $recipes = [];
                if (isset($categoryData['recipes']) && is_array($categoryData['recipes'])) {
                    foreach ($categoryData['recipes'] as $recIndex => $recipeData) {
                        if (!is_array($recipeData) || !isset($recipeData['id']) || !is_int($recipeData['id'])) {
                            throw new InvalidResponseException(sprintf('Missing or invalid recipe "id" at category %d, recipe %d.', $catIndex, $recIndex));
                        }

                        $recipeName = isset($recipeData['name']) && is_string($recipeData['name'])
                            ? $recipeData['name']
                            : '';

                        $recipes[] = new SkillTierRecipeSummary(
                            id: $recipeData['id'],
                            name: $recipeName,
                        );
                    }
                }

                $categories[] = new SkillTierCategory(
                    name: $categoryData['name'],
                    recipes: $recipes,
                );
            }
        }

        return new SkillTier(
            id: $data['id'],
            professionId: $professionId,
            name: $data['name'],
            minimumSkillLevel: $minSkill,
            maximumSkillLevel: $maxSkill,
            categories: $categories,
        );
    }
}
