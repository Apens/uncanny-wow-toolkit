<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use Psr\Cache\CacheItemPoolInterface;
use UncannyWoW\Core\Contract\Repository\ProfessionRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Profession\Profession;
use UncannyWoW\Core\Domain\Model\Profession\SkillTier;

class CachedProfessionRepository implements ProfessionRepositoryInterface
{
    public function __construct(
        private readonly ProfessionRepositoryInterface $innerRepository,
        private readonly ?CacheItemPoolInterface $cachePool = null,
        private readonly int $defaultTtlSeconds = 86400,
    ) {}

    public function getById(Region $region, int $id, Locale $locale): Profession
    {
        if ($this->cachePool === null) {
            return $this->innerRepository->getById($region, $id, $locale);
        }

        $cacheKey = $this->buildProfessionCacheKey($region, $id, $locale);
        $cacheItem = $this->cachePool->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            /** @var Profession $cachedItem */
            $cachedItem = $cacheItem->get();
            return $cachedItem;
        }

        $profession = $this->innerRepository->getById($region, $id, $locale);

        if ($this->defaultTtlSeconds > 0) {
            $cacheItem->set($profession);
            $cacheItem->expiresAfter($this->defaultTtlSeconds);
            $this->cachePool->save($cacheItem);
        }

        return $profession;
    }

    public function getSkillTier(Region $region, int $professionId, int $skillTierId, Locale $locale): SkillTier
    {
        if ($this->cachePool === null) {
            return $this->innerRepository->getSkillTier($region, $professionId, $skillTierId, $locale);
        }

        $cacheKey = $this->buildSkillTierCacheKey($region, $professionId, $skillTierId, $locale);
        $cacheItem = $this->cachePool->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            /** @var SkillTier $cachedItem */
            $cachedItem = $cacheItem->get();
            return $cachedItem;
        }

        $skillTier = $this->innerRepository->getSkillTier($region, $professionId, $skillTierId, $locale);

        if ($this->defaultTtlSeconds > 0) {
            $cacheItem->set($skillTier);
            $cacheItem->expiresAfter($this->defaultTtlSeconds);
            $this->cachePool->save($cacheItem);
        }

        return $skillTier;
    }

    private function buildProfessionCacheKey(Region $region, int $id, Locale $locale): string
    {
        $localeKey = strtolower($locale->value);

        $identity = sprintf('%s:%s:%d', strtolower($region->value), $localeKey, $id);
        $hash = substr(hash('sha256', $identity), 0, 48);

        return sprintf(
            'uw.profession.%s.%s',
            strtolower($region->value),
            $hash,
        );
    }

    private function buildSkillTierCacheKey(Region $region, int $professionId, int $skillTierId, Locale $locale): string
    {
        $localeKey = strtolower($locale->value);

        $identity = sprintf('%s:%s:%d:%d', strtolower($region->value), $localeKey, $professionId, $skillTierId);
        $hash = substr(hash('sha256', $identity), 0, 48);

        return sprintf(
            'uw.skilltier.%s.%s',
            strtolower($region->value),
            $hash,
        );
    }
}
