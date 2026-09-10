<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use UncannyWoW\Core\Contract\Repository\ProfessionRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Profession\Profession;
use UncannyWoW\Core\Domain\Model\Profession\SkillTier;
use UncannyWoW\Provider\Blizzard\Repository\CachedProfessionRepository;

#[CoversClass(CachedProfessionRepository::class)]
final class CachedProfessionRepositoryTest extends TestCase
{
    public function testGetByIdPassesThroughWithoutCache(): void
    {
        $inner = $this->createMock(ProfessionRepositoryInterface::class);
        $prof = new Profession(171, 'Alchemy', 'Brewing', []);

        $inner->expects($this->once())
            ->method('getById')
            ->with(Region::EU, 171, Locale::EN_US)
            ->willReturn($prof);

        $repo = new CachedProfessionRepository(innerRepository: $inner, cachePool: null);

        $result = $repo->getById(Region::EU, 171, Locale::EN_US);
        $this->assertSame($prof, $result);
    }

    public function testGetByIdUsesCacheOnHit(): void
    {
        $inner = $this->createMock(ProfessionRepositoryInterface::class);
        $inner->expects($this->never())->method('getById');

        $prof = new Profession(171, 'Alchemy', 'Brewing', []);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('isHit')->willReturn(true);
        $cacheItem->expects($this->once())->method('get')->willReturn($prof);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItem')->willReturn($cacheItem);

        $repo = new CachedProfessionRepository(innerRepository: $inner, cachePool: $pool);

        $result = $repo->getById(Region::EU, 171, Locale::EN_US);
        $this->assertSame($prof, $result);
    }

    public function testGetSkillTierUsesCacheOnHit(): void
    {
        $inner = $this->createMock(ProfessionRepositoryInterface::class);
        $inner->expects($this->never())->method('getSkillTier');

        $tier = new SkillTier(2822, 171, 'Khaz Algar Alchemy', 1, 100, []);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('isHit')->willReturn(true);
        $cacheItem->expects($this->once())->method('get')->willReturn($tier);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItem')->willReturn($cacheItem);

        $repo = new CachedProfessionRepository(innerRepository: $inner, cachePool: $pool);

        $result = $repo->getSkillTier(Region::EU, 171, 2822, Locale::EN_US);
        $this->assertSame($tier, $result);
    }

    public function testGetSkillTierSavesToCacheOnMiss(): void
    {
        $inner = $this->createMock(ProfessionRepositoryInterface::class);
        $tier = new SkillTier(2822, 171, 'Khaz Algar Alchemy', 1, 100, []);

        $inner->expects($this->once())
            ->method('getSkillTier')
            ->with(Region::EU, 171, 2822, Locale::EN_US)
            ->willReturn($tier);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('isHit')->willReturn(false);
        $cacheItem->expects($this->once())->method('set')->with($tier)->willReturnSelf();
        $cacheItem->expects($this->once())->method('expiresAfter')->with(86400)->willReturnSelf();

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItem')->willReturn($cacheItem);
        $pool->expects($this->once())->method('save')->with($cacheItem)->willReturn(true);

        $repo = new CachedProfessionRepository(innerRepository: $inner, cachePool: $pool, defaultTtlSeconds: 86400);

        $result = $repo->getSkillTier(Region::EU, 171, 2822, Locale::EN_US);
        $this->assertSame($tier, $result);
    }
}
