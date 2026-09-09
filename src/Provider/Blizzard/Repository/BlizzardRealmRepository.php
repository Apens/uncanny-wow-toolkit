<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\RealmRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Model\Realm\Realm;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiEndpointResolver;
use UncannyWoW\Provider\Blizzard\Hydrator\RealmHydrator;

class BlizzardRealmRepository implements RealmRepositoryInterface
{
    public function __construct(
        private readonly BlizzardApiClient $apiClient,
        private readonly RealmHydrator $hydrator,
        private readonly ClientConfiguration $config,
    ) {}

    public function getBySlug(Region $region, string $slug, ?Locale $locale = null): Realm
    {
        $normalizedSlug = mb_strtolower(trim($slug), 'UTF-8');

        if ($normalizedSlug === '') {
            throw new \InvalidArgumentException('Realm slug cannot be empty.');
        }

        if (preg_match('/\s/', $normalizedSlug) === 1) {
            throw new \InvalidArgumentException('Realm slug cannot contain whitespace. A canonical Blizzard realm slug is required (e.g., "la-croisade-écarlate").');
        }

        $path = sprintf('/data/wow/realm/%s', rawurlencode($normalizedSlug));
        $targetLocale = $locale ?? $this->config->defaultLocale;

        $data = $this->apiClient->get(
            region: $region,
            path: $path,
            locale: $targetLocale,
            namespace: BlizzardApiEndpointResolver::resolveDynamicNamespace($region),
            resourceTypeForNotFound: 'realm',
            identifierForNotFound: sprintf('%s:%s', $region->value, $normalizedSlug),
        );

        return $this->hydrator->hydrate($data);
    }

    /**
     * @return list<Realm>
     */
    public function searchByName(Region $region, string $name, ?Locale $locale = null): array
    {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException('Search name cannot be empty.');
        }

        $targetLocale = $locale ?? $this->config->defaultLocale;

        $data = $this->apiClient->get(
            region: $region,
            path: '/data/wow/search/realm',
            queryParams: [
                sprintf('name.%s', $targetLocale->value) => $trimmedName,
                '_page' => '1',
            ],
            locale: $targetLocale,
            namespace: BlizzardApiEndpointResolver::resolveDynamicNamespace($region),
            resourceTypeForNotFound: 'realm',
            identifierForNotFound: sprintf('%s:search:%s', $region->value, $trimmedName),
        );

        if (!isset($data['results']) || !is_array($data['results'])) {
            throw new InvalidResponseException('Expected "results" array in realm search payload.');
        }

        $realms = [];
        foreach ($data['results'] as $result) {
            if (!is_array($result) || !isset($result['data']) || !is_array($result['data'])) {
                throw new InvalidResponseException('Invalid result item structure in realm search payload.');
            }

            $realms[] = $this->hydrator->hydrateSearchResult($result['data'], $targetLocale);
        }

        return $realms;
    }
}
