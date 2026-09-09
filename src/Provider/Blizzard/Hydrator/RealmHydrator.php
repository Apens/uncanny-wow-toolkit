<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Hydrator;

use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Model\Realm\Realm;

class RealmHydrator
{
    /**
     * @var array<string, Locale>
     */
    private const BLIZZARD_LOCALE_MAP = [
        'frFR' => Locale::FR_FR,
        'enUS' => Locale::EN_US,
        'enGB' => Locale::EN_GB,
        'deDE' => Locale::DE_DE,
        'esES' => Locale::ES_ES,
        'esMX' => Locale::ES_MX,
        'itIT' => Locale::IT_IT,
        'ruRU' => Locale::RU_RU,
        'ptBR' => Locale::PT_BR,
        'koKR' => Locale::KO_KR,
        'zhTW' => Locale::ZH_TW,
        'zhCN' => Locale::ZH_CN,
    ];

    /**
     * Hydrate a standard Blizzard realm payload.
     *
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data, ?int $fallbackConnectedRealmId = null): Realm
    {
        if (!isset($data['id']) || !is_int($data['id'])) {
            throw new InvalidResponseException('Missing or invalid "id" in realm payload.');
        }

        if (!isset($data['slug']) || !is_string($data['slug']) || trim($data['slug']) === '') {
            throw new InvalidResponseException('Missing or invalid "slug" in realm payload.');
        }

        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            throw new InvalidResponseException('Missing or invalid "name" in realm payload.');
        }

        $category = isset($data['category']) && is_string($data['category']) ? $data['category'] : null;
        $rawLocale = isset($data['locale']) && is_string($data['locale']) ? $data['locale'] : null;
        $locale = $this->normalizeLocale($rawLocale);
        $timezone = isset($data['timezone']) && is_string($data['timezone']) ? $data['timezone'] : null;

        $connectedRealmId = $fallbackConnectedRealmId;
        if (isset($data['connected_realm']) && is_array($data['connected_realm']) && isset($data['connected_realm']['href']) && is_string($data['connected_realm']['href'])) {
            if (preg_match('#/connected-realm/(\d+)#', $data['connected_realm']['href'], $matches) === 1) {
                $connectedRealmId = (int) $matches[1];
            }
        }

        return new Realm(
            id: $data['id'],
            slug: $data['slug'],
            name: $data['name'],
            category: $category,
            locale: $locale,
            timezone: $timezone,
            connectedRealmId: $connectedRealmId,
        );
    }

    /**
     * Hydrate a realm item from a Blizzard Search API results payload.
     *
     * @param array<array-key, mixed> $data
     */
    public function hydrateSearchResult(array $data, ?Locale $preferredLocale = null): Realm
    {
        if (!isset($data['id']) || !is_int($data['id'])) {
            throw new InvalidResponseException('Missing or invalid "id" in search result item.');
        }

        if (!isset($data['slug']) || !is_string($data['slug']) || trim($data['slug']) === '') {
            throw new InvalidResponseException('Missing or invalid "slug" in search result item.');
        }

        $name = null;
        if (isset($data['name'])) {
            if (is_string($data['name']) && trim($data['name']) !== '') {
                $name = $data['name'];
            } elseif (is_array($data['name'])) {
                if ($preferredLocale !== null && isset($data['name'][$preferredLocale->value]) && is_string($data['name'][$preferredLocale->value])) {
                    $name = $data['name'][$preferredLocale->value];
                } else {
                    foreach ($data['name'] as $localizedName) {
                        if (is_string($localizedName) && trim($localizedName) !== '') {
                            $name = $localizedName;
                            break;
                        }
                    }
                }
            }
        }

        if ($name === null || trim($name) === '') {
            throw new InvalidResponseException('Missing or invalid "name" in search result item.');
        }

        $category = null;
        if (isset($data['category'])) {
            if (is_string($data['category'])) {
                $category = $data['category'];
            } elseif (is_array($data['category'])) {
                if ($preferredLocale !== null && isset($data['category'][$preferredLocale->value]) && is_string($data['category'][$preferredLocale->value])) {
                    $category = $data['category'][$preferredLocale->value];
                } else {
                    foreach ($data['category'] as $localizedCategory) {
                        if (is_string($localizedCategory)) {
                            $category = $localizedCategory;
                            break;
                        }
                    }
                }
            }
        }

        $rawLocale = isset($data['locale']) && is_string($data['locale']) ? $data['locale'] : null;
        $locale = $this->normalizeLocale($rawLocale);
        $timezone = isset($data['timezone']) && is_string($data['timezone']) ? $data['timezone'] : null;

        $connectedRealmId = null;
        if (isset($data['connected_realm']) && is_array($data['connected_realm']) && isset($data['connected_realm']['href']) && is_string($data['connected_realm']['href'])) {
            if (preg_match('#/connected-realm/(\d+)#', $data['connected_realm']['href'], $matches) === 1) {
                $connectedRealmId = (int) $matches[1];
            }
        }

        return new Realm(
            id: $data['id'],
            slug: $data['slug'],
            name: $name,
            category: $category,
            locale: $locale,
            timezone: $timezone,
            connectedRealmId: $connectedRealmId,
        );
    }

    private function normalizeLocale(?string $rawLocale): ?Locale
    {
        if ($rawLocale === null || trim($rawLocale) === '') {
            return null;
        }

        $trimmed = trim($rawLocale);

        if (isset(self::BLIZZARD_LOCALE_MAP[$trimmed])) {
            return self::BLIZZARD_LOCALE_MAP[$trimmed];
        }

        $standardLocale = Locale::tryFrom($trimmed);
        if ($standardLocale !== null) {
            return $standardLocale;
        }

        throw new InvalidResponseException(sprintf('Unsupported or unknown Blizzard realm locale "%s".', $rawLocale));
    }
}
