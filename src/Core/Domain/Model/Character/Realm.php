<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Character;

readonly class Realm
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
    ) {}
}
