<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Economy;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItem;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionItemModifier;
use UncannyWoW\Core\Domain\Model\Economy\MarketItemIdentity;

final class MarketItemIdentityTest extends TestCase
{
    public function testFromAuctionItemAndFingerprint(): void
    {
        $item = new AuctionItem(
            id: 19019,
            context: 0,
            bonusLists: [1487, 6652],
            modifiers: [new AuctionItemModifier(28, 156)],
        );

        $identity = MarketItemIdentity::fromAuctionItem($item);

        self::assertSame(19019, $identity->itemId);
        self::assertSame(0, $identity->context);
        self::assertSame([1487, 6652], $identity->bonusLists);
        self::assertStringContainsString('"id":19019', $identity->getFingerprint());
        self::assertStringContainsString('"ctx":0', $identity->getFingerprint());
        self::assertStringContainsString('"bl":[1487,6652]', $identity->getFingerprint());
        self::assertStringContainsString('"mod":[[28,156]]', $identity->getFingerprint());
    }

    public function testBonusListOrderingProducesDistinctFingerprints(): void
    {
        $itemA = new AuctionItem(id: 19019, bonusLists: [1, 2]);
        $itemB = new AuctionItem(id: 19019, bonusLists: [2, 1]);

        $identityA = MarketItemIdentity::fromAuctionItem($itemA);
        $identityB = MarketItemIdentity::fromAuctionItem($itemB);

        self::assertNotSame($identityA->getFingerprint(), $identityB->getFingerprint());
        self::assertFalse($identityA->equals($identityB));
    }

    public function testIdenticalOrderingProducesEqualFingerprints(): void
    {
        $itemA = new AuctionItem(id: 19019, bonusLists: [1, 2]);
        $itemB = new AuctionItem(id: 19019, bonusLists: [1, 2]);

        $identityA = MarketItemIdentity::fromAuctionItem($itemA);
        $identityB = MarketItemIdentity::fromAuctionItem($itemB);

        self::assertSame($identityA->getFingerprint(), $identityB->getFingerprint());
        self::assertTrue($identityA->equals($identityB));
    }

    public function testModifierOrderingProducesDistinctFingerprints(): void
    {
        $itemA = new AuctionItem(id: 19019, modifiers: [
            new AuctionItemModifier(28, 156),
            new AuctionItemModifier(29, 200),
        ]);
        $itemB = new AuctionItem(id: 19019, modifiers: [
            new AuctionItemModifier(29, 200),
            new AuctionItemModifier(28, 156),
        ]);

        $identityA = MarketItemIdentity::fromAuctionItem($itemA);
        $identityB = MarketItemIdentity::fromAuctionItem($itemB);

        self::assertNotSame($identityA->getFingerprint(), $identityB->getFingerprint());
        self::assertFalse($identityA->equals($identityB));
    }

    public function testDifferentVariantsProduceDifferentFingerprints(): void
    {
        $gearNormal = new AuctionItem(id: 200000, context: 1, bonusLists: [100]);
        $gearHeroic = new AuctionItem(id: 200000, context: 2, bonusLists: [200]);

        $identityNormal = MarketItemIdentity::fromAuctionItem($gearNormal);
        $identityHeroic = MarketItemIdentity::fromAuctionItem($gearHeroic);

        self::assertNotSame($identityNormal->getFingerprint(), $identityHeroic->getFingerprint());
        self::assertFalse($identityNormal->equals($identityHeroic));
    }

    public function testPetSpeciesIdProducesDistinctFingerprints(): void
    {
        $petA = new AuctionItem(id: 82800, petSpeciesId: 100, petBreedId: 3, petLevel: 25, petQualityId: 3);
        $petB = new AuctionItem(id: 82800, petSpeciesId: 200, petBreedId: 3, petLevel: 25, petQualityId: 3);

        $idA = MarketItemIdentity::fromAuctionItem($petA);
        $idB = MarketItemIdentity::fromAuctionItem($petB);

        self::assertNotSame($idA->getFingerprint(), $idB->getFingerprint());
        self::assertFalse($idA->equals($idB));
    }

    public function testPetBreedIdProducesDistinctFingerprints(): void
    {
        $petA = new AuctionItem(id: 82800, petSpeciesId: 256, petBreedId: 3, petLevel: 25, petQualityId: 3);
        $petB = new AuctionItem(id: 82800, petSpeciesId: 256, petBreedId: 4, petLevel: 25, petQualityId: 3);

        $idA = MarketItemIdentity::fromAuctionItem($petA);
        $idB = MarketItemIdentity::fromAuctionItem($petB);

        self::assertNotSame($idA->getFingerprint(), $idB->getFingerprint());
        self::assertFalse($idA->equals($idB));
    }

    public function testPetLevelProducesDistinctFingerprints(): void
    {
        $petA = new AuctionItem(id: 82800, petSpeciesId: 256, petBreedId: 3, petLevel: 1, petQualityId: 3);
        $petB = new AuctionItem(id: 82800, petSpeciesId: 256, petBreedId: 3, petLevel: 25, petQualityId: 3);

        $idA = MarketItemIdentity::fromAuctionItem($petA);
        $idB = MarketItemIdentity::fromAuctionItem($petB);

        self::assertNotSame($idA->getFingerprint(), $idB->getFingerprint());
        self::assertFalse($idA->equals($idB));
    }

    public function testPetQualityIdProducesDistinctFingerprints(): void
    {
        $petA = new AuctionItem(id: 82800, petSpeciesId: 256, petBreedId: 3, petLevel: 25, petQualityId: 2);
        $petB = new AuctionItem(id: 82800, petSpeciesId: 256, petBreedId: 3, petLevel: 25, petQualityId: 3);

        $idA = MarketItemIdentity::fromAuctionItem($petA);
        $idB = MarketItemIdentity::fromAuctionItem($petB);

        self::assertNotSame($idA->getFingerprint(), $idB->getFingerprint());
        self::assertFalse($idA->equals($idB));
    }
}
