<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Profession;

final readonly class SkillTier
{
    /**
     * @param int $id Blizzard skill tier ID.
     * @param int $professionId Parent profession ID.
     * @param string $name Localized skill tier name (e.g. "Khaz Algar Alchemy").
     * @param int $minimumSkillLevel Minimum skill level (typically 1).
     * @param int $maximumSkillLevel Maximum skill level (e.g. 100).
     * @param list<SkillTierCategory> $categories Grouped recipe categories within this tier.
     */
    public function __construct(
        public int $id,
        public int $professionId,
        public string $name,
        public int $minimumSkillLevel,
        public int $maximumSkillLevel,
        public array $categories = [],
    ) {
        if ($this->id <= 0) {
            throw new \InvalidArgumentException(sprintf('Skill tier ID must be positive, got %d.', $this->id));
        }

        if ($this->professionId <= 0) {
            throw new \InvalidArgumentException(sprintf('Profession ID must be positive, got %d.', $this->professionId));
        }

        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Skill tier name must not be empty.');
        }

        if ($this->minimumSkillLevel < 0) {
            throw new \InvalidArgumentException(sprintf('Minimum skill level cannot be negative, got %d.', $this->minimumSkillLevel));
        }

        if ($this->maximumSkillLevel < $this->minimumSkillLevel) {
            throw new \InvalidArgumentException(sprintf(
                'Maximum skill level (%d) cannot be less than minimum skill level (%d).',
                $this->maximumSkillLevel,
                $this->minimumSkillLevel,
            ));
        }
    }

    /**
     * Return all unique recipe IDs across all categories in this skill tier.
     *
     * @return list<int>
     */
    public function getAllRecipeIds(): array
    {
        $ids = [];
        foreach ($this->categories as $category) {
            foreach ($category->recipes as $recipe) {
                $ids[] = $recipe->id;
            }
        }

        return array_values(array_unique($ids));
    }
}
