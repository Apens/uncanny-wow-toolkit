<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Provider\Blizzard\Hydrator\ProfessionHydrator;

#[CoversClass(ProfessionHydrator::class)]
final class ProfessionHydratorTest extends TestCase
{
    private ProfessionHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new ProfessionHydrator();
    }

    public function testHydrateProfession(): void
    {
        $payload = [
            'id' => 171,
            'name' => 'Alchemy',
            'description' => 'The art of brewing potions.',
            'skill_tiers' => [
                [
                    'id' => 2822,
                    'name' => 'Khaz Algar Alchemy',
                ],
                [
                    'id' => 2750,
                    'name' => 'Dragon Isles Alchemy',
                ],
            ],
        ];

        $profession = $this->hydrator->hydrateProfession($payload);

        $this->assertSame(171, $profession->id);
        $this->assertSame('Alchemy', $profession->name);
        $this->assertSame('The art of brewing potions.', $profession->description);
        $this->assertCount(2, $profession->skillTiers);
        $this->assertSame(2822, $profession->skillTiers[0]->id);
        $this->assertSame('Khaz Algar Alchemy', $profession->skillTiers[0]->name);
    }

    public function testHydrateProfessionWithoutIdThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $this->hydrator->hydrateProfession(['name' => 'Alchemy']);
    }

    public function testHydrateProfessionWithoutNameThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $this->hydrator->hydrateProfession(['id' => 171]);
    }

    public function testHydrateSkillTier(): void
    {
        $payload = [
            'id' => 2822,
            'name' => 'Khaz Algar Alchemy',
            'minimum_skill_level' => 1,
            'maximum_skill_level' => 100,
            'categories' => [
                [
                    'name' => 'Potions',
                    'recipes' => [
                        [
                            'id' => 37000,
                            'name' => 'Algari Healing Potion',
                        ],
                    ],
                ],
                [
                    'name' => 'Flasks',
                    'recipes' => [
                        [
                            'id' => 37001,
                            'name' => 'Algari Flask of Power',
                        ],
                    ],
                ],
            ],
        ];

        $skillTier = $this->hydrator->hydrateSkillTier($payload, 171);

        $this->assertSame(2822, $skillTier->id);
        $this->assertSame(171, $skillTier->professionId);
        $this->assertSame('Khaz Algar Alchemy', $skillTier->name);
        $this->assertSame(1, $skillTier->minimumSkillLevel);
        $this->assertSame(100, $skillTier->maximumSkillLevel);
        $this->assertCount(2, $skillTier->categories);
        $this->assertSame('Potions', $skillTier->categories[0]->name);
        $this->assertCount(1, $skillTier->categories[0]->recipes);
        $this->assertSame(37000, $skillTier->categories[0]->recipes[0]->id);
        $this->assertSame([37000, 37001], $skillTier->getAllRecipeIds());
    }

    public function testHydrateSkillTierWithoutIdThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $this->hydrator->hydrateSkillTier(['name' => 'Tier'], 171);
    }

    public function testHydrateSkillTierWithoutNameThrows(): void
    {
        $this->expectException(InvalidResponseException::class);

        $this->hydrator->hydrateSkillTier(['id' => 2822], 171);
    }
}
