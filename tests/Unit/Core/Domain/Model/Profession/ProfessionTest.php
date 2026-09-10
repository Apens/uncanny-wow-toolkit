<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Profession;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\Profession\Profession;
use UncannyWoW\Core\Domain\Model\Profession\ProfessionSkillTierSummary;

#[CoversClass(Profession::class)]
#[CoversClass(ProfessionSkillTierSummary::class)]
final class ProfessionTest extends TestCase
{
    public function testProfessionProperties(): void
    {
        $tiers = [
            new ProfessionSkillTierSummary(2822, 'Khaz Algar Alchemy'),
            new ProfessionSkillTierSummary(2750, 'Dragon Isles Alchemy'),
        ];

        $profession = new Profession(
            id: 171,
            name: 'Alchemy',
            description: 'The art of brewing potions and elixirs.',
            skillTiers: $tiers,
        );

        $this->assertSame(171, $profession->id);
        $this->assertSame('Alchemy', $profession->name);
        $this->assertSame('The art of brewing potions and elixirs.', $profession->description);
        $this->assertCount(2, $profession->skillTiers);
        $this->assertSame(2822, $profession->skillTiers[0]->id);
        $this->assertSame('Khaz Algar Alchemy', $profession->skillTiers[0]->name);
    }
}
