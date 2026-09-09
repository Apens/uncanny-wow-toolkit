<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Service;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\ConnectedRealmRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\ConnectedRealm\ConnectedRealm;
use UncannyWoW\Core\Domain\Model\Realm\Realm;
use UncannyWoW\Core\Service\ConnectedRealmService;

final class ConnectedRealmServiceTest extends TestCase
{
    private ConnectedRealm $dummyConnectedRealm;

    protected function setUp(): void
    {
        $realm = new Realm(
            id: 1086,
            slug: 'la-croisade-ecarlate',
            name: 'La Croisade écarlate',
            connectedRealmId: 1127,
        );

        $this->dummyConnectedRealm = new ConnectedRealm(
            id: 1127,
            realms: [$realm],
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

        $repository = $this->createMock(ConnectedRealmRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getById')
            ->with(Region::EU, 1127, Locale::FR_FR)
            ->willReturn($this->dummyConnectedRealm);

        $service = new ConnectedRealmService($repository, $config);
        $result = $service->get(1127);

        self::assertSame($this->dummyConnectedRealm, $result);
    }

    public function testGetUsesConfiguredDefaultEnUsLocale(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::US,
            defaultLocale: Locale::EN_US,
        );

        $repository = $this->createMock(ConnectedRealmRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getById')
            ->with(Region::US, 1127, Locale::EN_US)
            ->willReturn($this->dummyConnectedRealm);

        $service = new ConnectedRealmService($repository, $config);
        $result = $service->get(1127);

        self::assertSame($this->dummyConnectedRealm, $result);
    }

    public function testGetWithExplicitRegionAndLocaleOverrides(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::EU,
            defaultLocale: Locale::FR_FR,
        );

        $usConnectedRealm = new ConnectedRealm(
            id: 1127,
            realms: [],
        );

        $repository = $this->createMock(ConnectedRealmRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getById')
            ->with(Region::US, 1127, Locale::EN_US)
            ->willReturn($usConnectedRealm);

        $service = new ConnectedRealmService($repository, $config);
        $result = $service->get(1127, region: Region::US, locale: Locale::EN_US);

        self::assertSame($usConnectedRealm, $result);
    }

    public function testGetWithZeroIdThrowsInvalidArgumentException(): void
    {
        $config = new ClientConfiguration('test-id', 'test-secret');
        $repository = $this->createStub(ConnectedRealmRepositoryInterface::class);
        $service = new ConnectedRealmService($repository, $config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connected realm ID must be a positive integer, got 0.');

        $service->get(0);
    }

    public function testGetWithNegativeIdThrowsInvalidArgumentException(): void
    {
        $config = new ClientConfiguration('test-id', 'test-secret');
        $repository = $this->createStub(ConnectedRealmRepositoryInterface::class);
        $service = new ConnectedRealmService($repository, $config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connected realm ID must be a positive integer, got -5.');

        $service->get(-5);
    }
}
