<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Provider\Blizzard\Hydrator;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Provider\Blizzard\Hydrator\ConnectedRealmHydrator;

final class ConnectedRealmHydratorTest extends TestCase
{
    private ConnectedRealmHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new ConnectedRealmHydrator();
    }

    public function testHydrateCompleteValidPayload(): void
    {
        $data = [
            'id' => 1127,
            'has_queue' => false,
            'realms' => [
                [
                    'id' => 1127,
                    'slug' => 'confrérie-du-thorium',
                    'name' => 'Confrérie du Thorium',
                    'category' => 'Français',
                    'locale' => 'frFR',
                    'timezone' => 'Europe/Paris',
                ],
                [
                    'id' => 1086,
                    'slug' => 'la-croisade-écarlate',
                    'name' => 'La Croisade écarlate',
                    'category' => 'Français',
                    'locale' => 'frFR',
                    'timezone' => 'Europe/Paris',
                ],
                [
                    'id' => 1337,
                    'slug' => 'culte-de-la-rive-noire',
                    'name' => 'Culte de la Rive noire',
                    'category' => 'Français',
                    'locale' => 'frFR',
                    'timezone' => 'Europe/Paris',
                ],
                [
                    'id' => 647,
                    'slug' => 'les-sentinelles',
                    'name' => 'Les Sentinelles',
                    'category' => 'Français',
                    'locale' => 'frFR',
                    'timezone' => 'Europe/Paris',
                ],
                [
                    'id' => 537,
                    'slug' => 'kirin-tor',
                    'name' => 'Kirin Tor',
                    'category' => 'Français',
                    'locale' => 'frFR',
                    'timezone' => 'Europe/Paris',
                ],
                [
                    'id' => 1626,
                    'slug' => 'les-clairvoyants',
                    'name' => 'Les Clairvoyants',
                    'category' => 'Français',
                    'locale' => 'frFR',
                    'timezone' => 'Europe/Paris',
                ],
                [
                    'id' => 644,
                    'slug' => 'conseil-des-ombres',
                    'name' => 'Conseil des Ombres',
                    'category' => 'Français',
                    'locale' => 'frFR',
                    'timezone' => 'Europe/Paris',
                ],
            ],
        ];

        $connectedRealm = $this->hydrator->hydrate($data);

        self::assertSame(1127, $connectedRealm->id);
        self::assertCount(7, $connectedRealm->realms);

        self::assertSame(1127, $connectedRealm->realms[0]->id);
        self::assertSame('confrérie-du-thorium', $connectedRealm->realms[0]->slug);
        self::assertSame('Confrérie du Thorium', $connectedRealm->realms[0]->name);
        self::assertSame('Français', $connectedRealm->realms[0]->category);
        self::assertSame(Locale::FR_FR, $connectedRealm->realms[0]->locale);
        self::assertSame('Europe/Paris', $connectedRealm->realms[0]->timezone);
        self::assertSame(1127, $connectedRealm->realms[0]->connectedRealmId);

        self::assertSame(1086, $connectedRealm->realms[1]->id);
        self::assertSame('la-croisade-écarlate', $connectedRealm->realms[1]->slug);
        self::assertSame('La Croisade écarlate', $connectedRealm->realms[1]->name);
        self::assertSame(1127, $connectedRealm->realms[1]->connectedRealmId);

        self::assertSame(1337, $connectedRealm->realms[2]->id);
        self::assertSame('culte-de-la-rive-noire', $connectedRealm->realms[2]->slug);
        self::assertSame('Culte de la Rive noire', $connectedRealm->realms[2]->name);
        self::assertSame(1127, $connectedRealm->realms[2]->connectedRealmId);

        self::assertSame(647, $connectedRealm->realms[3]->id);
        self::assertSame('les-sentinelles', $connectedRealm->realms[3]->slug);
        self::assertSame(1127, $connectedRealm->realms[3]->connectedRealmId);

        self::assertSame(537, $connectedRealm->realms[4]->id);
        self::assertSame('kirin-tor', $connectedRealm->realms[4]->slug);
        self::assertSame(1127, $connectedRealm->realms[4]->connectedRealmId);

        self::assertSame(1626, $connectedRealm->realms[5]->id);
        self::assertSame('les-clairvoyants', $connectedRealm->realms[5]->slug);
        self::assertSame(1127, $connectedRealm->realms[5]->connectedRealmId);

        self::assertSame(644, $connectedRealm->realms[6]->id);
        self::assertSame('conseil-des-ombres', $connectedRealm->realms[6]->slug);
        self::assertSame(1127, $connectedRealm->realms[6]->connectedRealmId);
    }

    public function testHydratePreservesExplicitConnectedRealmHrefIfPresent(): void
    {
        $data = [
            'id' => 1127,
            'realms' => [
                [
                    'id' => 1086,
                    'slug' => 'la-croisade-ecarlate',
                    'name' => 'La Croisade écarlate',
                    'connected_realm' => [
                        'href' => 'https://eu.api.blizzard.com/data/wow/connected-realm/1127?namespace=dynamic-eu',
                    ],
                ],
            ],
        ];

        $connectedRealm = $this->hydrator->hydrate($data);

        self::assertSame(1127, $connectedRealm->realms[0]->connectedRealmId);
    }

    public function testHydrateMissingIdThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "id" in connected realm payload.');

        $this->hydrator->hydrate([
            'realms' => [
                [
                    'id' => 1086,
                    'slug' => 'la-croisade-ecarlate',
                    'name' => 'La Croisade écarlate',
                ],
            ],
        ]);
    }

    public function testHydrateInvalidIdThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or invalid "id" in connected realm payload.');

        $this->hydrator->hydrate([
            'id' => 'not-an-int',
            'realms' => [],
        ]);
    }

    public function testHydrateMissingRealmsThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or empty "realms" in connected realm payload.');

        $this->hydrator->hydrate([
            'id' => 1127,
        ]);
    }

    public function testHydrateEmptyRealmsThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Missing or empty "realms" in connected realm payload.');

        $this->hydrator->hydrate([
            'id' => 1127,
            'realms' => [],
        ]);
    }

    public function testHydrateInvalidRealmDataStructureThrowsInvalidResponseException(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Invalid realm data at index 0 in connected realm payload.');

        $this->hydrator->hydrate([
            'id' => 1127,
            'realms' => [
                'not-an-array',
            ],
        ]);
    }
}
