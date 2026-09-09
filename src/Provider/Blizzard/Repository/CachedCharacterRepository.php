<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use Psr\Cache\CacheItemPoolInterface;
use UncannyWoW\Core\Contract\Repository\CharacterRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Character\CharacterProfile;

class CachedCharacterRepository implements CharacterRepositoryInterface
{
    public function __construct(
        private readonly CharacterRepositoryInterface $innerRepository,
        private readonly ?CacheItemPoolInterface $cachePool = null,
        private readonly int $defaultTtlSeconds = 900,
    ) {}

    public function findProfile(Region $region, string $realmSlug, string $name): CharacterProfile
    {
        if ($this->cachePool === null) {
            return $this->innerRepository->findProfile($region, $realmSlug, $name);
        }

        $cacheKey = $this->buildCacheKey($region, $realmSlug, $name);
        $cacheItem = $this->cachePool->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            /** @var CharacterProfile $cachedProfile */
            $cachedProfile = $cacheItem->get();
            return $cachedProfile;
        }

        $profile = $this->innerRepository->findProfile($region, $realmSlug, $name);

        if ($this->defaultTtlSeconds > 0) {
            $cacheItem->set($profile);
            $cacheItem->expiresAfter($this->defaultTtlSeconds);
            $this->cachePool->save($cacheItem);
        }

        return $profile;
    }

    private function buildCacheKey(Region $region, string $realmSlug, string $name): string
    {
        $normalizedRealm = mb_strtolower(trim($realmSlug), 'UTF-8');
        $normalizedName = mb_strtolower(trim($name), 'UTF-8');

        $identity = sprintf('%s:%s:%s', strtolower($region->value), $normalizedRealm, $normalizedName);
        $hash = substr(hash('sha256', $identity), 0, 48);

        return sprintf(
            'uw.cp.%s.%s',
            strtolower($region->value),
            $hash,
        );
    }
}
