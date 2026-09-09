<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\ConnectedRealm;

use UncannyWoW\Core\Domain\Model\Realm\Realm;

final readonly class ConnectedRealm
{
    /**
     * @param list<Realm> $realms
     */
    public function __construct(
        public int $id,
        public array $realms,
    ) {}
}
