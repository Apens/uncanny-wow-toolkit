<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Service;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\ItemRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\ItemQuality;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Item\Item;
use UncannyWoW\Core\Service\ItemService;

final class ItemServiceTest extends TestCase
{
    private Item $dummyItem;

    protected function setUp(): void
    {
        $this->dummyItem = new Item(
            id: 19019,
            name: 'Lame-tonnerre',
            quality: ItemQuality::LEGENDARY,
            level: 60,
            requiredLevel: 60,
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

        $repository = $this->createMock(ItemRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getById')
            ->with(Region::EU, 19019, Locale::FR_FR)
            ->willReturn($this->dummyItem);

        $service = new ItemService($repository, $config);
        $result = $service->get(19019);

        self::assertSame($this->dummyItem, $result);
    }

    public function testGetUsesConfiguredDefaultEnUsLocale(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::US,
            defaultLocale: Locale::EN_US,
        );

        $repository = $this->createMock(ItemRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getById')
            ->with(Region::US, 19019, Locale::EN_US)
            ->willReturn($this->dummyItem);

        $service = new ItemService($repository, $config);
        $result = $service->get(19019);

        self::assertSame($this->dummyItem, $result);
    }

    public function testGetWithExplicitRegionAndLocaleOverrides(): void
    {
        $config = new ClientConfiguration(
            clientId: 'test-id',
            clientSecret: 'test-secret',
            region: Region::EU,
            defaultLocale: Locale::FR_FR,
        );

        $usItem = new Item(
            id: 19019,
            name: 'Thunderfury',
            quality: ItemQuality::LEGENDARY,
            level: 60,
            requiredLevel: 60,
        );

        $repository = $this->createMock(ItemRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getById')
            ->with(Region::US, 19019, Locale::EN_US)
            ->willReturn($usItem);

        $service = new ItemService($repository, $config);
        $result = $service->get(19019, region: Region::US, locale: Locale::EN_US);

        self::assertSame($usItem, $result);
    }

    public function testGetWithZeroIdThrowsInvalidArgumentException(): void
    {
        $config = new ClientConfiguration('test-id', 'test-secret');
        $repository = $this->createStub(ItemRepositoryInterface::class);
        $service = new ItemService($repository, $config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item ID must be a positive integer, got 0.');

        $service->get(0);
    }

    public function testGetWithNegativeIdThrowsInvalidArgumentException(): void
    {
        $config = new ClientConfiguration('test-id', 'test-secret');
        $repository = $this->createStub(ItemRepositoryInterface::class);
        $service = new ItemService($repository, $config);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item ID must be a positive integer, got -5.');

        $service->get(-5);
    }
}
