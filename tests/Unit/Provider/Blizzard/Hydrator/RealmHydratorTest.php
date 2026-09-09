<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Hydrator;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Provider\Blizzard\Hydrator\RealmHydrator;

final class RealmHydratorTest extends TestCase
{
    private RealmHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new RealmHydrator();
    }

    public function testHydrateValidRealmPayload(): void
    {
        $payload = [
            'id' => 1086,
            'slug' => 'la-croisade-écarlate',
            'name' => 'La Croisade écarlate',
            'category' => 'French',
            'locale' => 'frFR',
            'timezone' => 'Europe/Paris',
            'connected_realm' => [
                'href' => 'https://eu.api.blizzard.com/data/wow/connected-realm/1086?namespace=dynamic-eu',
            ],
        ];

        $realm = $this->hydrator->hydrate($payload);

        self::assertSame(1086, $realm->id);
        self::assertSame('la-croisade-écarlate', $realm->slug);
        self::assertSame('La Croisade écarlate', $realm->name);
        self::assertSame('French', $realm->category);
        self::assertSame(Locale::FR_FR, $realm->locale);
        self::assertSame('Europe/Paris', $realm->timezone);
        self::assertSame(1086, $realm->connectedRealmId);
    }

    public function testHydrateMissingConnectedRealmHref(): void
    {
        $payload = [
            'id' => 1086,
            'slug' => 'la-croisade-écarlate',
            'name' => 'La Croisade écarlate',
        ];

        $realm = $this->hydrator->hydrate($payload);

        self::assertSame(1086, $realm->id);
        self::assertSame('la-croisade-écarlate', $realm->slug);
        self::assertSame('La Croisade écarlate', $realm->name);
        self::assertNull($realm->category);
        self::assertNull($realm->locale);
        self::assertNull($realm->timezone);
        self::assertNull($realm->connectedRealmId);
    }

    public function testHydrateMissingIdThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "id" in realm payload.');

        $this->hydrator->hydrate([
            'slug' => 'hyjal',
            'name' => 'Hyjal',
        ]);
    }

    public function testHydrateMissingSlugThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "slug" in realm payload.');

        $this->hydrator->hydrate([
            'id' => 1086,
            'name' => 'Hyjal',
        ]);
    }

    public function testHydrateEmptySlugThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "slug" in realm payload.');

        $this->hydrator->hydrate([
            'id' => 1086,
            'slug' => '   ',
            'name' => 'Hyjal',
        ]);
    }

    public function testHydrateMissingNameThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "name" in realm payload.');

        $this->hydrator->hydrate([
            'id' => 1086,
            'slug' => 'hyjal',
        ]);
    }

    public function testHydrateEmptyNameThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "name" in realm payload.');

        $this->hydrator->hydrate([
            'id' => 1086,
            'slug' => 'hyjal',
            'name' => '',
        ]);
    }

    public function testHydrateSearchResultWithLocalizedDict(): void
    {
        $data = [
            'id' => 1086,
            'slug' => 'la-croisade-écarlate',
            'name' => [
                'fr_FR' => 'La Croisade écarlate',
                'en_US' => 'Scarlet Crusade',
            ],
            'category' => [
                'fr_FR' => 'French',
                'en_US' => 'French',
            ],
            'locale' => 'frFR',
            'timezone' => 'Europe/Paris',
            'connected_realm' => [
                'href' => 'https://eu.api.blizzard.com/data/wow/connected-realm/1086?namespace=dynamic-eu',
            ],
        ];

        $realmFr = $this->hydrator->hydrateSearchResult($data, Locale::FR_FR);
        self::assertSame('La Croisade écarlate', $realmFr->name);
        self::assertSame('French', $realmFr->category);
        self::assertSame('la-croisade-écarlate', $realmFr->slug);
        self::assertSame(1086, $realmFr->id);
        self::assertSame(1086, $realmFr->connectedRealmId);

        $realmEn = $this->hydrator->hydrateSearchResult($data, Locale::EN_US);
        self::assertSame('Scarlet Crusade', $realmEn->name);
    }

    public function testHydrateSearchResultWithScalarStringName(): void
    {
        $data = [
            'id' => 1086,
            'slug' => 'la-croisade-écarlate',
            'name' => 'La Croisade écarlate',
            'category' => 'French',
        ];

        $realm = $this->hydrator->hydrateSearchResult($data);
        self::assertSame('La Croisade écarlate', $realm->name);
        self::assertSame('French', $realm->category);
    }

    public function testHydrateSearchResultMissingIdThrowsException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "id" in search result item.');

        $this->hydrator->hydrateSearchResult([
            'slug' => 'hyjal',
            'name' => 'Hyjal',
        ]);
    }

    public function testHydrateSearchResultMissingSlugThrowsException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "slug" in search result item.');

        $this->hydrator->hydrateSearchResult([
            'id' => 1086,
            'name' => 'Hyjal',
        ]);
    }

    public function testHydrateSearchResultMissingNameThrowsException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "name" in search result item.');

        $this->hydrator->hydrateSearchResult([
            'id' => 1086,
            'slug' => 'hyjal',
            'name' => [],
        ]);
    }

    public function testHydrateUnknownLocaleThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Unsupported or unknown Blizzard realm locale "xxYY".');

        $this->hydrator->hydrate([
            'id' => 1086,
            'slug' => 'ragnaros',
            'name' => 'Ragnaros',
            'locale' => 'xxYY',
        ]);
    }

    public function testHydrateSupportedLocales(): void
    {
        $cases = [
            'enUS' => Locale::EN_US,
            'enGB' => Locale::EN_GB,
            'frFR' => Locale::FR_FR,
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

        foreach ($cases as $code => $expectedEnum) {
            $realm = $this->hydrator->hydrate([
                'id' => 1,
                'slug' => 'test-realm',
                'name' => 'Test Realm',
                'locale' => $code,
            ]);

            self::assertSame($expectedEnum, $realm->locale);
        }
    }

    public function testHydrateEmptyOrNullLocaleProducesNull(): void
    {
        $realmNull = $this->hydrator->hydrate([
            'id' => 1,
            'slug' => 'test-realm',
            'name' => 'Test Realm',
            'locale' => null,
        ]);
        self::assertNull($realmNull->locale);

        $realmEmpty = $this->hydrator->hydrate([
            'id' => 1,
            'slug' => 'test-realm',
            'name' => 'Test Realm',
            'locale' => '   ',
        ]);
        self::assertNull($realmEmpty->locale);
    }
}
