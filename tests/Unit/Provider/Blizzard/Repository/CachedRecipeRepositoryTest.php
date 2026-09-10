<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use UncannyWoW\Core\Contract\Repository\RecipeRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Recipe\Recipe;
use UncannyWoW\Core\Domain\Model\Recipe\RecipeCraftedQuantity;
use UncannyWoW\Provider\Blizzard\Repository\CachedRecipeRepository;

#[CoversClass(CachedRecipeRepository::class)]
final class CachedRecipeRepositoryTest extends TestCase
{
    public function testGetByIdPassesThroughWithoutCache(): void
    {
        $inner = $this->createMock(RecipeRepositoryInterface::class);
        $recipe = new Recipe(100, 'Test Recipe', 200, 'Test Item', RecipeCraftedQuantity::fixed(1), []);

        $inner->expects($this->once())
            ->method('getById')
            ->with(Region::EU, 100, Locale::EN_US)
            ->willReturn($recipe);

        $repo = new CachedRecipeRepository(innerRepository: $inner, cachePool: null);

        $result = $repo->getById(Region::EU, 100, Locale::EN_US);
        $this->assertSame($recipe, $result);
    }

    public function testGetByIdUsesCacheOnHit(): void
    {
        $inner = $this->createMock(RecipeRepositoryInterface::class);
        $inner->expects($this->never())->method('getById');

        $recipe = new Recipe(100, 'Test Recipe', 200, 'Test Item', RecipeCraftedQuantity::fixed(1), []);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('isHit')->willReturn(true);
        $cacheItem->expects($this->once())->method('get')->willReturn($recipe);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);

        $repo = new CachedRecipeRepository(innerRepository: $inner, cachePool: $pool);

        $result = $repo->getById(Region::EU, 100, Locale::EN_US);
        $this->assertSame($recipe, $result);
    }

    public function testGetByIdSavesToCacheOnMiss(): void
    {
        $inner = $this->createMock(RecipeRepositoryInterface::class);
        $recipe = new Recipe(100, 'Test Recipe', 200, 'Test Item', RecipeCraftedQuantity::fixed(1), []);

        $inner->expects($this->once())
            ->method('getById')
            ->with(Region::EU, 100, Locale::EN_US)
            ->willReturn($recipe);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('isHit')->willReturn(false);
        $cacheItem->expects($this->once())->method('set')->with($recipe)->willReturnSelf();
        $cacheItem->expects($this->once())->method('expiresAfter')->with(86400)->willReturnSelf();

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItem')->willReturn($cacheItem);
        $pool->expects($this->once())->method('save')->with($cacheItem)->willReturn(true);

        $repo = new CachedRecipeRepository(innerRepository: $inner, cachePool: $pool, defaultTtlSeconds: 86400);

        $result = $repo->getById(Region::EU, 100, Locale::EN_US);
        $this->assertSame($recipe, $result);
    }
}
