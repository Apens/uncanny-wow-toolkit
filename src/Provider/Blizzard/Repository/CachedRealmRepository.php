<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use Psr\Cache\CacheItemPoolInterface;
use UncannyWoW\Core\Contract\Repository\RealmRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Realm\Realm;

class CachedRealmRepository implements RealmRepositoryInterface
{
    public function __construct(
        private readonly RealmRepositoryInterface $innerRepository,
        private readonly ?CacheItemPoolInterface $cachePool = null,
        private readonly int $defaultTtlSeconds = 86400,
    ) {}

    public function getBySlug(Region $region, string $slug, ?Locale $locale = null): Realm
    {
        if ($this->cachePool === null) {
            return $this->innerRepository->getBySlug($region, $slug, $locale);
        }

        $cacheKey = $this->buildSlugCacheKey($region, $slug, $locale);
        $cacheItem = $this->cachePool->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            /** @var Realm $cachedRealm */
            $cachedRealm = $cacheItem->get();
            return $cachedRealm;
        }

        $realm = $this->innerRepository->getBySlug($region, $slug, $locale);

        if ($this->defaultTtlSeconds > 0) {
            $cacheItem->set($realm);
            $cacheItem->expiresAfter($this->defaultTtlSeconds);
            $this->cachePool->save($cacheItem);
        }

        return $realm;
    }

    /**
     * @return list<Realm>
     */
    public function searchByName(Region $region, string $name, ?Locale $locale = null): array
    {
        if ($this->cachePool === null) {
            return $this->innerRepository->searchByName($region, $name, $locale);
        }

        $cacheKey = $this->buildSearchCacheKey($region, $name, $locale);
        $cacheItem = $this->cachePool->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            /** @var list<Realm> $cachedResults */
            $cachedResults = $cacheItem->get();
            return $cachedResults;
        }

        $results = $this->innerRepository->searchByName($region, $name, $locale);

        if ($this->defaultTtlSeconds > 0) {
            $cacheItem->set($results);
            $cacheItem->expiresAfter($this->defaultTtlSeconds);
            $this->cachePool->save($cacheItem);
        }

        return $results;
    }

    private function buildSlugCacheKey(Region $region, string $slug, ?Locale $locale): string
    {
        $normalizedSlug = mb_strtolower(trim($slug), 'UTF-8');
        $localeKey = strtolower(($locale ?? Locale::FR_FR)->value);

        $identity = sprintf('%s:%s:%s', strtolower($region->value), $localeKey, $normalizedSlug);
        $hash = substr(hash('sha256', $identity), 0, 48);

        return sprintf(
            'uw.realm.%s.%s',
            strtolower($region->value),
            $hash,
        );
    }

    private function buildSearchCacheKey(Region $region, string $name, ?Locale $locale): string
    {
        $normalizedName = mb_strtolower(trim($name), 'UTF-8');
        $localeKey = strtolower(($locale ?? Locale::FR_FR)->value);

        $identity = sprintf('%s:%s:s:%s', strtolower($region->value), $localeKey, $normalizedName);
        $hash = substr(hash('sha256', $identity), 0, 48);

        return sprintf(
            'uw.rs.%s.%s',
            strtolower($region->value),
            $hash,
        );
    }
}
