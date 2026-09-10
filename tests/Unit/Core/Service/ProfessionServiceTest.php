<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\ProfessionRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Profession\Profession;
use UncannyWoW\Core\Domain\Model\Profession\SkillTier;
use UncannyWoW\Core\Service\ProfessionService;

#[CoversClass(ProfessionService::class)]
final class ProfessionServiceTest extends TestCase
{
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->config = new ClientConfiguration(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            region: Region::EU,
            defaultLocale: Locale::FR_FR,
        );
    }

    public function testGetProfessionUsesDefaults(): void
    {
        $repository = $this->createMock(ProfessionRepositoryInterface::class);
        $service = new ProfessionService($repository, $this->config);
        $prof = new Profession(171, 'Alchemy', 'Brewing', []);

        $repository->expects(self::once())
            ->method('getById')
            ->with(Region::EU, 171, Locale::FR_FR)
            ->willReturn($prof);

        $result = $service->get(171);
        $this->assertSame($prof, $result);
    }

    public function testGetProfessionWithOverrides(): void
    {
        $repository = $this->createMock(ProfessionRepositoryInterface::class);
        $service = new ProfessionService($repository, $this->config);
        $prof = new Profession(171, 'Alchemy', 'Brewing', []);

        $repository->expects(self::once())
            ->method('getById')
            ->with(Region::US, 171, Locale::EN_US)
            ->willReturn($prof);

        $result = $service->get(171, Region::US, Locale::EN_US);
        $this->assertSame($prof, $result);
    }

    public function testGetProfessionInvalidIdThrows(): void
    {
        $stubRepo = $this->createStub(ProfessionRepositoryInterface::class);
        $service = new ProfessionService($stubRepo, $this->config);

        $this->expectException(\InvalidArgumentException::class);
        $service->get(-1);
    }

    public function testSkillTierUsesDefaults(): void
    {
        $repository = $this->createMock(ProfessionRepositoryInterface::class);
        $service = new ProfessionService($repository, $this->config);
        $tier = new SkillTier(2822, 171, 'Khaz Algar Alchemy', 1, 100, []);

        $repository->expects(self::once())
            ->method('getSkillTier')
            ->with(Region::EU, 171, 2822, Locale::FR_FR)
            ->willReturn($tier);

        $result = $service->skillTier(171, 2822);
        $this->assertSame($tier, $result);
    }

    public function testSkillTierWithOverrides(): void
    {
        $repository = $this->createMock(ProfessionRepositoryInterface::class);
        $service = new ProfessionService($repository, $this->config);
        $tier = new SkillTier(2822, 171, 'Khaz Algar Alchemy', 1, 100, []);

        $repository->expects(self::once())
            ->method('getSkillTier')
            ->with(Region::US, 171, 2822, Locale::EN_US)
            ->willReturn($tier);

        $result = $service->skillTier(171, 2822, Region::US, Locale::EN_US);
        $this->assertSame($tier, $result);
    }

    public function testSkillTierInvalidIdsThrow(): void
    {
        $stubRepo = $this->createStub(ProfessionRepositoryInterface::class);
        $service = new ProfessionService($stubRepo, $this->config);

        $this->expectException(\InvalidArgumentException::class);
        $service->skillTier(0, 2822);
    }

    public function testSkillTierInvalidTierIdThrows(): void
    {
        $stubRepo = $this->createStub(ProfessionRepositoryInterface::class);
        $service = new ProfessionService($stubRepo, $this->config);

        $this->expectException(\InvalidArgumentException::class);
        $service->skillTier(171, 0);
    }
}
