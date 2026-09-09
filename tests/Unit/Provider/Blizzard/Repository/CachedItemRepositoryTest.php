<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Repository;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use UncannyWoW\Core\Contract\Repository\ItemRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\ItemQuality;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Item\Item;
use UncannyWoW\Provider\Blizzard\Repository\CachedItemRepository;

final class CachedItemRepositoryTest extends TestCase
{
    private Item $dummyItem;

    protected function setUp(): void
    {
        $this->dummyItem = new Item(
            id: 19019,
            name: 'Lame-tonnerre, épée bénie du Cherchevent',
            quality: ItemQuality::LEGENDARY,
            level: 60,
            requiredLevel: 60,
        );
    }

    public function testBypassCacheWhenNoCachePoolSupplied(): void
    {
        $inner = $this->createMock(ItemRepositoryInterface::class);
        $inner->expects(self::once())
            ->method('getById')
            ->with(Region::EU, 19019, Locale::FR_FR)
            ->willReturn($this->dummyItem);

        $cachedRepository = new CachedItemRepository($inner, null);

        $result = $cachedRepository->getById(Region::EU, 19019, Locale::FR_FR);
        self::assertSame($this->dummyItem, $result);
    }

    public function testCacheHitReturnsCachedItemWithoutCallingInnerRepository(): void
    {
        $inner = $this->createMock(ItemRepositoryInterface::class);
        $inner->expects(self::never())->method('getById');

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(true);
        $cacheItem->expects(self::once())->method('get')->willReturn($this->dummyItem);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())
            ->method('getItem')
            ->with(self::callback(function (string $key): bool {
                return str_starts_with($key, 'uw.item.eu.')
                    && strlen($key) <= 64
                    && preg_match('/^[A-Za-z0-9_.]+$/', $key) === 1;
            }))
            ->willReturn($cacheItem);

        $cachedRepository = new CachedItemRepository($inner, $cachePool, 86400);

        $result = $cachedRepository->getById(Region::EU, 19019, Locale::FR_FR);
        self::assertSame($this->dummyItem, $result);
    }

    public function testCacheMissPopulatesCachePool(): void
    {
        $inner = $this->createMock(ItemRepositoryInterface::class);
        $inner->expects(self::once())
            ->method('getById')
            ->with(Region::EU, 19019, Locale::FR_FR)
            ->willReturn($this->dummyItem);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(false);
        $cacheItem->expects(self::once())->method('set')->with($this->dummyItem)->willReturnSelf();
        $cacheItem->expects(self::once())->method('expiresAfter')->with(86400)->willReturnSelf();

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())->method('getItem')->willReturn($cacheItem);
        $cachePool->expects(self::once())->method('save')->with($cacheItem)->willReturn(true);

        $cachedRepository = new CachedItemRepository($inner, $cachePool, 86400);

        $result = $cachedRepository->getById(Region::EU, 19019, Locale::FR_FR);
        self::assertSame($this->dummyItem, $result);
    }

    public function testCacheKeyDeterminismAndSafety(): void
    {
        $keys = [];
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::exactly(4))
            ->method('getItem')
            ->willReturnCallback(function (string $key) use (&$keys) {
                $keys[] = $key;
                $item = $this->createStub(CacheItemInterface::class);
                $item->method('isHit')->willReturn(true);
                $item->method('get')->willReturn($this->dummyItem);
                return $item;
            });

        $inner = $this->createStub(ItemRepositoryInterface::class);
        $cachedRepository = new CachedItemRepository($inner, $cachePool);

        // Same inputs produce identical key
        $cachedRepository->getById(Region::EU, 19019, Locale::FR_FR);
        $cachedRepository->getById(Region::EU, 19019, Locale::FR_FR);

        // Different locale produces different key
        $cachedRepository->getById(Region::EU, 19019, Locale::EN_US);

        // Different region produces different key
        $cachedRepository->getById(Region::US, 19019, Locale::FR_FR);

        self::assertSame($keys[0], $keys[1]);
        self::assertNotSame($keys[0], $keys[2]);
        self::assertNotSame($keys[0], $keys[3]);
        self::assertNotSame($keys[2], $keys[3]);

        foreach ($keys as $k) {
            self::assertLessThanOrEqual(64, strlen($k));
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_.]+$/', $k);
        }
    }
}
