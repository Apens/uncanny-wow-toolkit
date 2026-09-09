<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Character;

use UncannyWoW\Core\Domain\Enum\Faction;
use UncannyWoW\Core\Domain\Model\Realm\Realm;

readonly class CharacterProfile
{
    public function __construct(
        public CharacterId $id,
        public string $name,
        public int $level,
        public Realm $realm,
        public PlayableClass $playableClass,
        public Faction $faction,
    ) {}
}
