<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Profession;

final readonly class SkillTierCategory
{
    /**
     * @param string $name Localized category name (e.g. "Potions & Phials").
     * @param list<SkillTierRecipeSummary> $recipes List of recipes in this category.
     */
    public function __construct(
        public string $name,
        public array $recipes = [],
    ) {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Skill tier category name must not be empty.');
        }
    }
}
