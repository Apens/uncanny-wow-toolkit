<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Repository;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use UncannyWoW\Core\Contract\Repository\CharacterRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Faction;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Character\CharacterId;
use UncannyWoW\Core\Domain\Model\Character\CharacterProfile;
use UncannyWoW\Core\Domain\Model\Character\PlayableClass;
use UncannyWoW\Core\Domain\Model\Character\Realm;
use UncannyWoW\Provider\Blizzard\Repository\CachedCharacterRepository;

final class CachedCharacterRepositoryTest extends TestCase
{
    private CharacterProfile $dummyProfile;

    protected function setUp(): void
    {
        $this->dummyProfile = new CharacterProfile(
            id: new CharacterId(Region::EU, 'la-croisade-ecarlate', 'norigosa'),
            name: 'Norigosa',
            level: 80,
            realm: new Realm(1305, 'la-croisade-ecarlate', 'La Croisade écarlate'),
            playableClass: new PlayableClass(8, 'Mage'),
            faction: Faction::HORDE,
        );
    }

    public function testBypassCacheWhenNoCachePoolSupplied(): void
    {
        $inner = $this->createMock(CharacterRepositoryInterface::class);
        $inner->expects(self::once())
            ->method('findProfile')
            ->with(Region::EU, 'la-croisade-ecarlate', 'norigosa')
            ->willReturn($this->dummyProfile);

        $cachedRepository = new CachedCharacterRepository($inner, null);

        $result = $cachedRepository->findProfile(Region::EU, 'la-croisade-ecarlate', 'norigosa');
        self::assertSame($this->dummyProfile, $result);
    }

    public function testCacheHitReturnsCachedProfile(): void
    {
        $expectedKey = 'uw.cp.eu.' . substr(hash('sha256', 'eu:la-croisade-ecarlate:norigosa'), 0, 48);
        self::assertLessThanOrEqual(64, strlen($expectedKey));
        self::assertMatchesRegularExpression('/^[a-z0-9_.]+$/', $expectedKey);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(true);
        $cacheItem->expects(self::once())->method('get')->willReturn($this->dummyProfile);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItem);

        $inner = $this->createMock(CharacterRepositoryInterface::class);
        $inner->expects(self::never())->method('findProfile');

        $cachedRepository = new CachedCharacterRepository($inner, $cachePool, 900);

        $result = $cachedRepository->findProfile(Region::EU, 'la-croisade-ecarlate', 'norigosa');
        self::assertSame($this->dummyProfile, $result);
    }

    public function testCacheMissFetchesFromInnerAndSavesToCache(): void
    {
        $expectedKey = 'uw.cp.eu.' . substr(hash('sha256', 'eu:la-croisade-ecarlate:norigosa'), 0, 48);
        self::assertLessThanOrEqual(64, strlen($expectedKey));
        self::assertMatchesRegularExpression('/^[a-z0-9_.]+$/', $expectedKey);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(false);
        $cacheItem->expects(self::once())->method('set')->with($this->dummyProfile);
        $cacheItem->expects(self::once())->method('expiresAfter')->with(900);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItem);
        $cachePool->expects(self::once())->method('save')->with($cacheItem);

        $inner = $this->createMock(CharacterRepositoryInterface::class);
        $inner->expects(self::once())
            ->method('findProfile')
            ->with(Region::EU, 'la-croisade-ecarlate', 'norigosa')
            ->willReturn($this->dummyProfile);

        $cachedRepository = new CachedCharacterRepository($inner, $cachePool, 900);

        $result = $cachedRepository->findProfile(Region::EU, 'la-croisade-ecarlate', 'norigosa');
        self::assertSame($this->dummyProfile, $result);
    }

    public function testCacheKeyWithUnicodeCharacterName(): void
    {
        $expectedKey = 'uw.cp.eu.' . substr(hash('sha256', 'eu:kael-thas:nörigosa'), 0, 48);
        self::assertLessThanOrEqual(64, strlen($expectedKey));
        self::assertMatchesRegularExpression('/^[a-z0-9_.]+$/', $expectedKey);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects(self::once())->method('isHit')->willReturn(true);
        $cacheItem->expects(self::once())->method('get')->willReturn($this->dummyProfile);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItem);

        $inner = $this->createStub(CharacterRepositoryInterface::class);
        $cachedRepository = new CachedCharacterRepository($inner, $cachePool, 900);

        $cachedRepository->findProfile(Region::EU, 'Kael-Thas', 'Nörigosa');
    }

    public function testCacheKeyDifferentIdentitiesNeverCollide(): void
    {
        $capturedKeys = [];
        $cacheItem = $this->createStub(CacheItemInterface::class);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::exactly(2))
            ->method('getItem')
            ->willReturnCallback(function (string $key) use (&$capturedKeys, $cacheItem) {
                $capturedKeys[] = $key;
                return $cacheItem;
            });

        $inner = $this->createStub(CharacterRepositoryInterface::class);
        $cachedRepository = new CachedCharacterRepository($inner, $cachePool, 900);

        $cachedRepository->findProfile(Region::EU, 'realm-one', 'char-two');
        $cachedRepository->findProfile(Region::EU, 'realm_one', 'char_two');

        self::assertCount(2, $capturedKeys);
        self::assertNotSame($capturedKeys[0], $capturedKeys[1]);
        self::assertLessThanOrEqual(64, strlen($capturedKeys[0]));
        self::assertLessThanOrEqual(64, strlen($capturedKeys[1]));
        self::assertMatchesRegularExpression('/^[a-z0-9_.]+$/', $capturedKeys[0]);
        self::assertMatchesRegularExpression('/^[a-z0-9_.]+$/', $capturedKeys[1]);
    }
}
