<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Domain\Enum\ItemQuality;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\UncannyWoWClient;

final class UncannyWoWClientItemIntegrationTest extends TestCase
{
    public function testEndToEndItemGet(): void
    {
        $factory = new Psr17Factory();
        $tokenFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/oauth_token_200.json');
        $itemFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/item_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($itemFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], $itemFixture),
            );

        $wow = UncannyWoWClient::create(
            clientId: 'integration-client-id',
            clientSecret: 'integration-client-secret',
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            defaultRegion: Region::EU,
            defaultLocale: Locale::FR_FR,
        );

        $item = $wow->items()->get(id: 19019);

        self::assertSame(19019, $item->id);
        self::assertSame('Lame-tonnerre, épée bénie du Cherchevent', $item->name);
        self::assertSame(ItemQuality::LEGENDARY, $item->quality);
        self::assertSame(29, $item->level);
        self::assertSame(25, $item->requiredLevel);
        self::assertNotNull($item->itemClass);
        self::assertSame(2, $item->itemClass->id);
        self::assertSame('Arme', $item->itemClass->name);
        self::assertNotNull($item->itemSubclass);
        self::assertSame(7, $item->itemSubclass->id);
        self::assertSame('Épée', $item->itemSubclass->name);
        self::assertNotNull($item->inventoryType);
        self::assertSame('WEAPON', $item->inventoryType->type);
        self::assertSame('À une main', $item->inventoryType->name);
        self::assertSame(1, $item->maxCount);
        self::assertTrue($item->isEquippable);
    }

    public function testItemsMemoization(): void
    {
        $factory = new Psr17Factory();
        $httpClient = $this->createStub(ClientInterface::class);

        $wow = UncannyWoWClient::create(
            clientId: 'test',
            clientSecret: 'test',
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
        );

        $service1 = $wow->items();
        $service2 = $wow->items();

        self::assertSame($service1, $service2);
    }
}
