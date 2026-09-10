<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration\Provider\Blizzard;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\ResourceNotFoundException;
use UncannyWoW\Provider\Blizzard\Auth\OAuthTokenProvider;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Hydrator\RecipeHydrator;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardRecipeRepository;

final class BlizzardRecipeRepositoryTest extends TestCase
{
    private Psr17Factory $factory;
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->config = new ClientConfiguration('test-client-id', 'test-client-secret', Region::EU, Locale::FR_FR);
    }

    public function testGetByIdSuccess200(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $recipePayload = json_encode([
            'id' => 37000,
            'name' => 'Algari Healing Potion',
            'crafted_item' => [
                'id' => 212241,
                'name' => 'Algari Healing Potion',
            ],
            'crafted_quantity' => [
                'value' => 1,
            ],
            'reagents' => [
                [
                    'reagent' => [
                        'id' => 210796,
                        'name' => 'Mycobloom',
                    ],
                    'quantity' => 3,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        /** @var list<string> $requestedUrls */
        $requestedUrls = [];
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$requestedUrls, $tokenFixture, $recipePayload) {
                $requestedUrls[] = (string) $request->getUri();
                if (count($requestedUrls) === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }
                return new Response(200, ['Content-Type' => 'application/json'], $recipePayload);
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RecipeHydrator();
        $repository = new BlizzardRecipeRepository($apiClient, $hydrator);

        $recipe = $repository->getById(Region::EU, 37000, Locale::FR_FR);

        self::assertSame(37000, $recipe->id);
        self::assertSame('Algari Healing Potion', $recipe->name);
        self::assertSame(212241, $recipe->craftedItemId);
        self::assertTrue($recipe->hasCraftedItemReference());
        self::assertTrue($recipe->craftedQuantity->isKnown());
        self::assertSame(1, $recipe->craftedQuantity->minimum);
        self::assertCount(1, $recipe->reagents);
        self::assertSame(210796, $recipe->reagents[0]->itemId);

        self::assertCount(2, $requestedUrls);
        self::assertStringContainsString('/data/wow/recipe/37000', $requestedUrls[1]);
        self::assertStringContainsString('namespace=static-eu', $requestedUrls[1]);
        self::assertStringContainsString('locale=fr_FR', $requestedUrls[1]);
    }

    public function testGetById404ThrowsResourceNotFoundException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $notFoundPayload = json_encode(['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'], JSON_THROW_ON_ERROR);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(404, ['Content-Type' => 'application/json'], $notFoundPayload),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RecipeHydrator();
        $repository = new BlizzardRecipeRepository($apiClient, $hydrator);

        try {
            $repository->getById(Region::EU, 99999999, Locale::FR_FR);
            self::fail('Expected ResourceNotFoundException was not thrown.');
        } catch (ResourceNotFoundException $e) {
            self::assertSame('recipe', $e->getResourceType());
            self::assertSame('eu:99999999', $e->getIdentifier());
        }
    }

    public function testGetByIdInvalidIdThrows(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new RecipeHydrator();
        $repository = new BlizzardRecipeRepository($apiClient, $hydrator);

        $this->expectException(\InvalidArgumentException::class);
        $repository->getById(Region::EU, 0, Locale::FR_FR);
    }
}
