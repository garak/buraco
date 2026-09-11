<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\Buraco;
use Garak\Buraco\CardValue;
use Garak\Buraco\Rules;
use Garak\Card\Card;
use Garak\Card\Rank;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RulesTest extends TestCase
{
    #[Test]
    public function defaultsAreItalian(): void
    {
        $rules = new Rules();
        self::assertSame(2, $rules->decks);
        self::assertSame(4, $rules->jokers);
        self::assertSame(11, $rules->handSize);
        self::assertSame(11, $rules->pozzettoSize);
        self::assertSame(2, $rules->unplayableStockCards);
        self::assertSame(100, $rules->closingBonus);
        self::assertSame(100, $rules->pozzettoPenalty);
        self::assertFalse($rules->cleanBuracoToClose);
        self::assertNull($rules->setRanks);
        self::assertFalse($rules->mustMeldAfterTakingPile);
        self::assertSame(300, $rules->getBuracoPoints(Buraco::Royal));
        self::assertSame(250, $rules->getBuracoPoints(Buraco::DirtyRoyal));
        self::assertSame(250, $rules->getBuracoPoints(Buraco::Super));
        self::assertSame(200, $rules->getBuracoPoints(Buraco::DirtySuper));
        self::assertSame(200, $rules->getBuracoPoints(Buraco::Clean));
        self::assertSame(150, $rules->getBuracoPoints(Buraco::SemiClean));
        self::assertSame(100, $rules->getBuracoPoints(Buraco::Dirty));
        self::assertTrue($rules->allowsSetOf(Rank::King));
    }

    #[Test]
    public function international(): void
    {
        $rules = Rules::international();
        self::assertSame(11, $rules->handSize);
        self::assertTrue($rules->cleanBuracoToClose);
        self::assertTrue($rules->mustMeldAfterTakingPile);
        self::assertTrue($rules->allowsSetOf(Rank::Ace));
        self::assertTrue($rules->allowsSetOf(Rank::Three));
        self::assertFalse($rules->allowsSetOf(Rank::King));
        self::assertSame(200, $rules->getBuracoPoints(Buraco::Royal));
        self::assertSame(100, $rules->getBuracoPoints(Buraco::DirtyRoyal));
        self::assertSame(200, $rules->getBuracoPoints(Buraco::Super));
        self::assertSame(100, $rules->getBuracoPoints(Buraco::DirtySuper));
        self::assertSame(200, $rules->getBuracoPoints(Buraco::Clean));
        self::assertSame(100, $rules->getBuracoPoints(Buraco::SemiClean));
        self::assertSame(100, $rules->getBuracoPoints(Buraco::Dirty));
    }

    #[Test]
    #[DataProvider('deckProvider')]
    public function createDeckWithGivenSize(int $decks, int $jokers, int $expectedCards): void
    {
        $deck = (new Rules(decks: $decks, jokers: $jokers))->createDeck();
        self::assertCount($expectedCards, $deck);
        self::assertCount($jokers, \array_filter($deck, static fn (Card $card): bool => CardValue::isJoker($card)));
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function deckProvider(): iterable
    {
        yield 'buraco' => [2, 4, 108];
        yield 'single deck, no jokers' => [1, 0, 52];
        yield 'single deck, one joker' => [1, 1, 53];
        yield 'two decks, two jokers' => [2, 2, 106];
    }

    #[Test]
    #[DataProvider('invalidProvider')]
    public function invalidParameters(callable $factory): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $factory();
    }

    /** @return iterable<string, array{callable}> */
    public static function invalidProvider(): iterable
    {
        yield 'no decks' => [static fn () => new Rules(decks: 0)];
        yield 'negative jokers' => [static fn () => new Rules(jokers: -1)];
        yield 'too many jokers' => [static fn () => new Rules(decks: 1, jokers: 3)];
        yield 'empty hand' => [static fn () => new Rules(handSize: 0)];
        yield 'empty pozzetto' => [static fn () => new Rules(pozzettoSize: 0)];
        yield 'negative unplayable cards' => [static fn () => new Rules(unplayableStockCards: -1)];
        yield 'negative bonus' => [static fn () => new Rules(closingBonus: -1)];
        yield 'negative buraco' => [static fn () => new Rules(semiCleanBuraco: -1)];
        yield 'no set ranks' => [static fn () => new Rules(setRanks: [])];
    }
}
