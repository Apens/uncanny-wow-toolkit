<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Profession;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\Profession\SkillTier;
use UncannyWoW\Core\Domain\Model\Profession\SkillTierCategory;
use UncannyWoW\Core\Domain\Model\Profession\SkillTierRecipeSummary;

#[CoversClass(SkillTier::class)]
#[CoversClass(SkillTierCategory::class)]
#[CoversClass(SkillTierRecipeSummary::class)]
final class SkillTierTest extends TestCase
{
    public function testSkillTierPropertiesAndGetAllRecipeIds(): void
    {
        $category1 = new SkillTierCategory(
            name: 'Potions',
            recipes: [
                new SkillTierRecipeSummary(101, 'Healing Potion'),
                new SkillTierRecipeSummary(102, 'Mana Potion'),
            ],
        );

        $category2 = new SkillTierCategory(
            name: 'Flasks',
            recipes: [
                new SkillTierRecipeSummary(103, 'Flask of Power'),
                new SkillTierRecipeSummary(101, 'Healing Potion'), // duplicate to test unique
            ],
        );

        $skillTier = new SkillTier(
            id: 2822,
            professionId: 171,
            name: 'Khaz Algar Alchemy',
            minimumSkillLevel: 1,
            maximumSkillLevel: 100,
            categories: [$category1, $category2],
        );

        $this->assertSame(2822, $skillTier->id);
        $this->assertSame(171, $skillTier->professionId);
        $this->assertSame('Khaz Algar Alchemy', $skillTier->name);
        $this->assertSame(1, $skillTier->minimumSkillLevel);
        $this->assertSame(100, $skillTier->maximumSkillLevel);
        $this->assertCount(2, $skillTier->categories);

        $allRecipeIds = $skillTier->getAllRecipeIds();
        $this->assertSame([101, 102, 103], $allRecipeIds);
    }
}
