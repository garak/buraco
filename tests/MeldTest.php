<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\Exception\InvalidMeldException;
use Garak\Buraco\Meld;
use Garak\Buraco\Run;
use Garak\Buraco\Set;
use Garak\Card\Card;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TODO when PHP 8.3 support is dropped (PHPUnit 13 only): the try/catch blocks can become
 * expectException() + expectExceptionMessageIsOrContains(), which is missing in PHPUnit 12.
 */
final class MeldTest extends TestCase
{
    #[Test]
    public function fromCardsGuessesType(): void
    {
        self::assertInstanceOf(Set::class, Meld::createFromString('Kh,Kd,Ks'));
        self::assertInstanceOf(Set::class, Meld::createFromString('Kh,wb,Ks,Kc'));
        self::assertInstanceOf(Set::class, Meld::createFromString('3h,3d,2s'));
        self::assertInstanceOf(Run::class, Meld::createFromString('Ah,2h,3h'));
        self::assertInstanceOf(Run::class, Meld::createFromString('2h,3h,wb'));
        self::assertInstanceOf(Run::class, Meld::createFromString('wb,2h,3h,4h'));
        self::assertInstanceOf(Run::class, Meld::createFromString('Qh,Kh,Ah'));
    }

    #[Test]
    public function fromCardsRejectsInvalid(): void
    {
        try {
            Meld::createFromString('Kh,Kd,Qs');
            self::fail('Neither a set nor a run.');
        } catch (InvalidMeldException $e) {
            self::assertSame('All cards in a run must share the suit, got Kh,Kd,Qs.', $e->getMessage());
        }
    }

    #[Test]
    public function fromCardsReportsTheSetErrorWhenNeitherFits(): void
    {
        try {
            Meld::createFromString('Kh,wb,2s');
            self::fail('Neither a set nor a run.');
        } catch (InvalidMeldException $e) {
            self::assertSame('A set cannot have more than 1 wildcard, got Kh,wb,2s.', $e->getMessage());
        }
    }

    #[Test]
    public function tooShort(): void
    {
        try {
            Meld::createFromString('Kh,Kd');
            self::fail('Two cards are not a meld.');
        } catch (InvalidMeldException $e) {
            self::assertSame('A meld needs at least 3 cards, 2 given.', $e->getMessage());
        }
    }

    #[Test]
    public function naturalCardsAndWildcard(): void
    {
        $meld = Meld::createFromString('Kh,wb,Ks');
        self::assertCount(3, $meld);
        self::assertTrue($meld->hasWildcard());
        self::assertSame('wb', (string) $meld->getWildcard());
        self::assertSame('Kh,Ks', \implode(',', $meld->getNaturalCards()));
        self::assertSame('Kh,wb,Ks', \implode(',', $meld->getCards()));
        self::assertFalse(Meld::createFromString('Kh,Kd,Ks')->hasWildcard());
        self::assertNull(Meld::createFromString('Kh,Kd,Ks')->getWildcard());
        // a natural two is not a wildcard
        self::assertSame('2h,3h,4h', \implode(',', Meld::createFromString('2h,3h,4h')->getNaturalCards()));
    }

    #[Test]
    public function points(): void
    {
        self::assertSame(30, Meld::createFromString('Kh,Kd,Ks')->getPoints());
        self::assertSame(50, Meld::createFromString('Kh,wb,Ks')->getPoints());
        self::assertSame(65, Meld::createFromString('Ah,2h,wb')->getPoints());
        self::assertSame(40, Meld::createFromString('Ah,2h,3h')->getPoints());
    }

    #[Test]
    public function isBuraco(): void
    {
        self::assertFalse(Meld::createFromString('3h,4h,5h,6h,7h,8h')->isBuraco());
        self::assertTrue(Meld::createFromString('3h,4h,5h,6h,7h,8h,9h')->isBuraco());
    }

    #[Test]
    public function hasSameCards(): void
    {
        self::assertTrue(Meld::createFromString('Kh,Kd,Ks')->hasSameCards(Meld::createFromString('Ks,Kh,Kd')));
        self::assertFalse(Meld::createFromString('Kh,Kd,Ks')->hasSameCards(Meld::createFromString('Kh,Kd,Kc')));
        self::assertFalse(Meld::createFromString('Khr,Kd,Ks')->hasSameCards(Meld::createFromString('Khb,Kd,Ks')));
    }

    #[Test]
    public function contains(): void
    {
        $small = Meld::createFromString('4h,5h,6h');
        $big = Meld::createFromString('3h,4h,5h,6h,7h');
        self::assertTrue($big->contains($small));
        self::assertFalse($small->contains($big));
        self::assertTrue($small->contains($small));
        self::assertFalse(Meld::createFromString('4hr,5h,6h')->contains(Meld::createFromString('4hb,5h,6h')));
    }

    #[Test]
    public function toStringWithBack(): void
    {
        $meld = new Run([Card::fromRankSuit('Ahr'), Card::fromRankSuit('2hb'), Card::fromRankSuit('3h')]);
        self::assertSame('Ah,2h,3h', (string) $meld);
        self::assertSame('Ahr,2hb,3h', $meld->toString(true));
    }
}
