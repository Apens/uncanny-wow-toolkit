<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Realm;

use UncannyWoW\Core\Domain\Enum\Locale;

readonly class Realm
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?string $category = null,
        public ?Locale $locale = null,
        public ?string $timezone = null,
        public ?int $connectedRealmId = null,
    ) {}
}
