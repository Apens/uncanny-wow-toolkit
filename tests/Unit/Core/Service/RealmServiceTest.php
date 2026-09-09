<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Service;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\RealmRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Realm\Realm;
use UncannyWoW\Core\Service\RealmService;

final class RealmServiceTest extends TestCase
{
    private Realm $dummyRealm;

    protected function setUp(): void
    {
        $this->dummyRealm = new Realm(
            id: 1086,
            slug: 'la-croisade-écarlate',
            name: 'La Croisade écarlate',
            category: 'French',
            locale: Locale::FR_FR,
            timezone: 'Europe/Paris',
            connectedRealmId: 1086,
        );
    }

    public function testGetUsesConfiguredDefaults(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::EU,
            defaultLocale: Locale::FR_FR,
        );

        $repository = $this->createMock(RealmRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getBySlug')
            ->with(Region::EU, 'la-croisade-écarlate', Locale::FR_FR)
            ->willReturn($this->dummyRealm);

        $service = new RealmService($repository, $config);

        $result = $service->get('la-croisade-écarlate');
        self::assertSame($this->dummyRealm, $result);
    }

    public function testGetWithOverrides(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::EU,
            defaultLocale: Locale::FR_FR,
        );

        $usRealm = new Realm(57, 'illidan', 'Illidan');

        $repository = $this->createMock(RealmRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getBySlug')
            ->with(Region::US, 'illidan', Locale::EN_US)
            ->willReturn($usRealm);

        $service = new RealmService($repository, $config);

        $result = $service->get(
            slug: 'illidan',
            region: Region::US,
            locale: Locale::EN_US,
        );

        self::assertSame($usRealm, $result);
    }

    public function testSearchUsesConfiguredDefaults(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::EU,
            defaultLocale: Locale::FR_FR,
        );

        $repository = $this->createMock(RealmRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('searchByName')
            ->with(Region::EU, 'La Croisade écarlate', Locale::FR_FR)
            ->willReturn([$this->dummyRealm]);

        $service = new RealmService($repository, $config);

        $results = $service->search('La Croisade écarlate');
        self::assertSame([$this->dummyRealm], $results);
    }

    public function testSearchWithOverrides(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::EU,
            defaultLocale: Locale::FR_FR,
        );

        $usRealm = new Realm(57, 'illidan', 'Illidan');

        $repository = $this->createMock(RealmRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('searchByName')
            ->with(Region::US, 'Illidan', Locale::EN_US)
            ->willReturn([$usRealm]);

        $service = new RealmService($repository, $config);

        $results = $service->search(
            name: 'Illidan',
            region: Region::US,
            locale: Locale::EN_US,
        );

        self::assertSame([$usRealm], $results);
    }
}
