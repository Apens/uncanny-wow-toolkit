<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Contract\Repository;

use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Recipe\Recipe;

interface RecipeRepositoryInterface
{
    /**
     * Retrieve a recipe by its positive integer ID.
     */
    public function getById(Region $region, int $id, Locale $locale): Recipe;
}
