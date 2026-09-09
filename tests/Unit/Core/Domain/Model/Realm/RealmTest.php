<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Realm;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Model\Realm\Realm;

final class RealmTest extends TestCase
{
    public function testRealmInstantiationWithAllFields(): void
    {
        $realm = new Realm(
            id: 1086,
            slug: 'la-croisade-écarlate',
            name: 'La Croisade écarlate',
            category: 'French',
            locale: Locale::FR_FR,
            timezone: 'Europe/Paris',
            connectedRealmId: 1086,
        );

        self::assertSame(1086, $realm->id);
        self::assertSame('la-croisade-écarlate', $realm->slug);
        self::assertSame('La Croisade écarlate', $realm->name);
        self::assertSame('French', $realm->category);
        self::assertSame(Locale::FR_FR, $realm->locale);
        self::assertSame('Europe/Paris', $realm->timezone);
        self::assertSame(1086, $realm->connectedRealmId);
    }

    public function testRealmInstantiationWithMinimalFields(): void
    {
        $realm = new Realm(
            id: 1305,
            slug: 'hyjal',
            name: 'Hyjal',
        );

        self::assertSame(1305, $realm->id);
        self::assertSame('hyjal', $realm->slug);
        self::assertSame('Hyjal', $realm->name);
        self::assertNull($realm->category);
        self::assertNull($realm->locale);
        self::assertNull($realm->timezone);
        self::assertNull($realm->connectedRealmId);
    }
}
