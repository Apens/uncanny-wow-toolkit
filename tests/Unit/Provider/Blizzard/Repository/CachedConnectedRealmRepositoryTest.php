<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Repository;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use UncannyWoW\Core\Contract\Repository\ConnectedRealmRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\ConnectedRealm\ConnectedRealm;
use UncannyWoW\Core\Domain\Model\Realm\Realm;
use UncannyWoW\Provider\Blizzard\Repository\CachedConnectedRealmRepository;

final class CachedConnectedRealmRepositoryTest extends TestCase
{
    private ConnectedRealm $dummyConnectedRealm;

    protected function setUp(): void
    {
        $realm = new Realm(
            id: 1086,
            slug: 'la-croisade-ecarlate',
            name: 'La Croisade écarlate',
            connectedRealmId: 1127,
        );

        $this->dummyConnectedRealm = new ConnectedRealm(
            id: 1127,
            realms: [$realm],
        );
    }

    public function testBypassCacheWhenNoCachePoolSupplied(): void
    {
        $inner = $this->createMock(ConnectedRealmRepositoryInterface::class);
        $inner->expects(self::once())
            ->method('getById')
            ->with(Region::EU, 1127, Locale::FR_FR)
            ->willReturn($this->dummyConnectedRealm);

        $cachedRepository = new CachedConnectedRealmRepository($inner, null);

        $result = $cachedRepository->getById(Region::EU, 1127, Locale::FR_FR);
        self::assertSame($this->dummyConnectedRealm, $result);
    }

    public function testCacheHitReturnsCachedItemWithoutCallingInnerRepository(): void
    {
        $inner = $this->createMock(ConnectedRealmRepositoryInterface::class);
        $inner->expects(self::never())->method('getById');

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(true);
        $cacheItem->expects(self::once())->method('get')->willReturn($this->dummyConnectedRealm);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())
            ->method('getItem')
            ->with(self::callback(function (string $key): bool {
                return str_starts_with($key, 'uw.cr.eu.')
                    && strlen($key) <= 64
                    && preg_match('/^[A-Za-z0-9_.]+$/', $key) === 1;
            }))
            ->willReturn($cacheItem);

        $cachedRepository = new CachedConnectedRealmRepository($inner, $cachePool, 86400);

        $result = $cachedRepository->getById(Region::EU, 1127, Locale::FR_FR);
        self::assertSame($this->dummyConnectedRealm, $result);
    }

    public function testCacheMissPopulatesCachePool(): void
    {
        $inner = $this->createMock(ConnectedRealmRepositoryInterface::class);
        $inner->expects(self::once())
            ->method('getById')
            ->with(Region::EU, 1127, Locale::FR_FR)
            ->willReturn($this->dummyConnectedRealm);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(false);
        $cacheItem->expects(self::once())->method('set')->with($this->dummyConnectedRealm)->willReturnSelf();
        $cacheItem->expects(self::once())->method('expiresAfter')->with(86400)->willReturnSelf();

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())->method('getItem')->willReturn($cacheItem);
        $cachePool->expects(self::once())->method('save')->with($cacheItem)->willReturn(true);

        $cachedRepository = new CachedConnectedRealmRepository($inner, $cachePool, 86400);

        $result = $cachedRepository->getById(Region::EU, 1127, Locale::FR_FR);
        self::assertSame($this->dummyConnectedRealm, $result);
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
                $item->method('get')->willReturn($this->dummyConnectedRealm);
                return $item;
            });

        $inner = $this->createStub(ConnectedRealmRepositoryInterface::class);
        $cachedRepository = new CachedConnectedRealmRepository($inner, $cachePool);

        // Same inputs produce identical key
        $cachedRepository->getById(Region::EU, 1127, Locale::FR_FR);
        $cachedRepository->getById(Region::EU, 1127, Locale::FR_FR);

        // Different locale produces different key
        $cachedRepository->getById(Region::EU, 1127, Locale::EN_US);

        // Different region produces different key
        $cachedRepository->getById(Region::US, 1127, Locale::FR_FR);

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
