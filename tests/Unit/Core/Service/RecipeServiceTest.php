<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\RecipeRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Recipe\Recipe;
use UncannyWoW\Core\Domain\Model\Recipe\RecipeCraftedQuantity;
use UncannyWoW\Core\Service\RecipeService;

#[CoversClass(RecipeService::class)]
final class RecipeServiceTest extends TestCase
{
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->config = new ClientConfiguration(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            region: Region::EU,
            defaultLocale: Locale::FR_FR,
        );
    }

    public function testGetUsesDefaults(): void
    {
        $repository = $this->createMock(RecipeRepositoryInterface::class);
        $service = new RecipeService($repository, $this->config);
        $recipe = new Recipe(100, 'Test Recipe', 200, 'Test Item', RecipeCraftedQuantity::fixed(1), []);

        $repository->expects(self::once())
            ->method('getById')
            ->with(Region::EU, 100, Locale::FR_FR)
            ->willReturn($recipe);

        $result = $service->get(100);
        $this->assertSame($recipe, $result);
    }

    public function testGetWithOverrides(): void
    {
        $repository = $this->createMock(RecipeRepositoryInterface::class);
        $service = new RecipeService($repository, $this->config);
        $recipe = new Recipe(100, 'Test Recipe', 200, 'Test Item', RecipeCraftedQuantity::fixed(1), []);

        $repository->expects(self::once())
            ->method('getById')
            ->with(Region::US, 100, Locale::EN_US)
            ->willReturn($recipe);

        $result = $service->get(100, Region::US, Locale::EN_US);
        $this->assertSame($recipe, $result);
    }

    public function testGetInvalidIdThrows(): void
    {
        $stubRepo = $this->createStub(RecipeRepositoryInterface::class);
        $service = new RecipeService($stubRepo, $this->config);

        $this->expectException(\InvalidArgumentException::class);
        $service->get(0);
    }
}
