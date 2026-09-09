<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Repository;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use UncannyWoW\Core\Contract\Repository\RealmRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Realm\Realm;
use UncannyWoW\Provider\Blizzard\Repository\CachedRealmRepository;

final class CachedRealmRepositoryTest extends TestCase
{
    private Realm $dummyRealm;

    protected function setUp(): void
    {
        $this->dummyRealm = new Realm(
            id: 1086,
            slug: 'la-croisade-écarlate',
            name: 'La Croisade écarlate',
            category: 'French',
            locale: Locale::FR_FR,
            timezone: 'Europe/Paris',
            connectedRealmId: 1086,
        );
    }

    public function testBypassCacheWhenNoCachePoolSuppliedForGetBySlug(): void
    {
        $inner = $this->createMock(RealmRepositoryInterface::class);
        $inner->expects(self::once())
            ->method('getBySlug')
            ->with(Region::EU, 'la-croisade-écarlate', Locale::FR_FR)
            ->willReturn($this->dummyRealm);

        $cachedRepository = new CachedRealmRepository($inner, null);

        $result = $cachedRepository->getBySlug(Region::EU, 'la-croisade-écarlate', Locale::FR_FR);
        self::assertSame($this->dummyRealm, $result);
    }

    public function testBypassCacheWhenNoCachePoolSuppliedForSearchByName(): void
    {
        $inner = $this->createMock(RealmRepositoryInterface::class);
        $inner->expects(self::once())
            ->method('searchByName')
            ->with(Region::EU, 'La Croisade écarlate', Locale::FR_FR)
            ->willReturn([$this->dummyRealm]);

        $cachedRepository = new CachedRealmRepository($inner, null);

        $result = $cachedRepository->searchByName(Region::EU, 'La Croisade écarlate', Locale::FR_FR);
        self::assertSame([$this->dummyRealm], $result);
    }

    public function testGetBySlugCacheHitReturnsCachedRealm(): void
    {
        $expectedKey = 'uw.realm.eu.' . substr(hash('sha256', 'eu:fr_fr:la-croisade-écarlate'), 0, 48);
        self::assertLessThanOrEqual(64, strlen($expectedKey));
        self::assertMatchesRegularExpression('/^[a-z0-9_.]+$/', $expectedKey);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(true);
        $cacheItem->expects(self::once())->method('get')->willReturn($this->dummyRealm);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItem);

        $inner = $this->createMock(RealmRepositoryInterface::class);
        $inner->expects(self::never())->method('getBySlug');

        $cachedRepository = new CachedRealmRepository($inner, $cachePool, 86400);

        $result = $cachedRepository->getBySlug(Region::EU, 'la-croisade-écarlate', Locale::FR_FR);
        self::assertSame($this->dummyRealm, $result);
    }

    public function testGetBySlugCacheMissFetchesAndSaves(): void
    {
        $expectedKey = 'uw.realm.eu.' . substr(hash('sha256', 'eu:fr_fr:la-croisade-écarlate'), 0, 48);
        self::assertLessThanOrEqual(64, strlen($expectedKey));

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(false);
        $cacheItem->expects(self::once())->method('set')->with($this->dummyRealm);
        $cacheItem->expects(self::once())->method('expiresAfter')->with(86400);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItem);
        $cachePool->expects(self::once())->method('save')->with($cacheItem);

        $inner = $this->createMock(RealmRepositoryInterface::class);
        $inner->expects(self::once())
            ->method('getBySlug')
            ->with(Region::EU, 'la-croisade-écarlate', Locale::FR_FR)
            ->willReturn($this->dummyRealm);

        $cachedRepository = new CachedRealmRepository($inner, $cachePool, 86400);

        $result = $cachedRepository->getBySlug(Region::EU, 'la-croisade-écarlate', Locale::FR_FR);
        self::assertSame($this->dummyRealm, $result);
    }

    public function testSearchByNameCacheHitReturnsCachedList(): void
    {
        $expectedKey = 'uw.rs.eu.' . substr(hash('sha256', 'eu:fr_fr:s:la croisade écarlate'), 0, 48);
        self::assertLessThanOrEqual(64, strlen($expectedKey));
        self::assertMatchesRegularExpression('/^[a-z0-9_.]+$/', $expectedKey);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(true);
        $cacheItem->expects(self::once())->method('get')->willReturn([$this->dummyRealm]);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItem);

        $inner = $this->createMock(RealmRepositoryInterface::class);
        $inner->expects(self::never())->method('searchByName');

        $cachedRepository = new CachedRealmRepository($inner, $cachePool, 86400);

        $result = $cachedRepository->searchByName(Region::EU, 'La Croisade écarlate', Locale::FR_FR);
        self::assertSame([$this->dummyRealm], $result);
    }

    public function testSearchByNameCacheMissFetchesAndSaves(): void
    {
        $expectedKey = 'uw.rs.eu.' . substr(hash('sha256', 'eu:fr_fr:s:la croisade écarlate'), 0, 48);
        self::assertLessThanOrEqual(64, strlen($expectedKey));

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(false);
        $cacheItem->expects(self::once())->method('set')->with([$this->dummyRealm]);
        $cacheItem->expects(self::once())->method('expiresAfter')->with(86400);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItem);
        $cachePool->expects(self::once())->method('save')->with($cacheItem);

        $inner = $this->createMock(RealmRepositoryInterface::class);
        $inner->expects(self::once())
            ->method('searchByName')
            ->with(Region::EU, 'La Croisade écarlate', Locale::FR_FR)
            ->willReturn([$this->dummyRealm]);

        $cachedRepository = new CachedRealmRepository($inner, $cachePool, 86400);

        $result = $cachedRepository->searchByName(Region::EU, 'La Croisade écarlate', Locale::FR_FR);
        self::assertSame([$this->dummyRealm], $result);
    }
}
