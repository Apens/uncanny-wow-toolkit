<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\ConnectedRealm;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Model\ConnectedRealm\ConnectedRealm;
use UncannyWoW\Core\Domain\Model\Realm\Realm;

final class ConnectedRealmTest extends TestCase
{
    public function testConnectedRealmInstantiationAndProperties(): void
    {
        $realm1 = new Realm(
            id: 1086,
            slug: 'la-croisade-ecarlate',
            name: 'La Croisade écarlate',
            category: 'Français',
            locale: Locale::FR_FR,
            timezone: 'Europe/Paris',
            connectedRealmId: 1127,
        );

        $realm2 = new Realm(
            id: 1087,
            slug: 'culte-de-la-rive-noire',
            name: 'Culte de la Rive noire',
            category: 'Français',
            locale: Locale::FR_FR,
            timezone: 'Europe/Paris',
            connectedRealmId: 1127,
        );

        $connectedRealm = new ConnectedRealm(
            id: 1127,
            realms: [$realm1, $realm2],
        );

        self::assertSame(1127, $connectedRealm->id);
        self::assertCount(2, $connectedRealm->realms);
        self::assertSame($realm1, $connectedRealm->realms[0]);
        self::assertSame($realm2, $connectedRealm->realms[1]);
        self::assertSame(1127, $connectedRealm->realms[0]->connectedRealmId);
        self::assertSame(1127, $connectedRealm->realms[1]->connectedRealmId);
    }
}
