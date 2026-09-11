<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\CardValue;
use Garak\Card\Card;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CardValueTest extends TestCase
{
    #[Test]
    #[DataProvider('valueProvider')]
    public function valueOfRegularCards(string $card, int $expected): void
    {
        self::assertSame($expected, CardValue::of(Card::fromRankSuit($card)));
    }

    /** @return iterable<string, array{string, int}> */
    public static function valueProvider(): iterable
    {
        yield 'ace is low by default' => ['Ah', 1];
        yield 'two' => ['2c', 2];
        yield 'ten' => ['Td', 10];
        yield 'jack' => ['Js', 11];
        yield 'queen' => ['Qh', 12];
        yield 'king' => ['Kc', 13];
    }

    #[Test]
    public function aceCanBeHigh(): void
    {
        self::assertSame(14, CardValue::of(Card::fromRankSuit('Ah'), aceHigh: true));
        self::assertSame(13, CardValue::of(Card::fromRankSuit('Kh'), aceHigh: true));
    }

    #[Test]
    public function jokerHasNoValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CardValue::of(Card::fromRankSuit('wb'));
    }

    #[Test]
    public function wildcards(): void
    {
        self::assertTrue(CardValue::isJoker(Card::fromRankSuit('wr')));
        self::assertFalse(CardValue::isJoker(Card::fromRankSuit('2h')));
        self::assertTrue(CardValue::isTwo(Card::fromRankSuit('2h')));
        self::assertTrue(CardValue::isWildcard(Card::fromRankSuit('wb')));
        self::assertTrue(CardValue::isWildcard(Card::fromRankSuit('2s')));
        self::assertFalse(CardValue::isWildcard(Card::fromRankSuit('Ah')));
    }

    #[Test]
    #[DataProvider('pointsProvider')]
    public function points(string $card, int $expected): void
    {
        self::assertSame($expected, CardValue::points(Card::fromRankSuit($card)));
    }

    /** @return iterable<string, array{string, int}> */
    public static function pointsProvider(): iterable
    {
        yield 'joker' => ['wb', 30];
        yield 'two' => ['2c', 20];
        yield 'ace' => ['Ah', 15];
        yield 'king' => ['Kh', 10];
        yield 'eight' => ['8d', 10];
        yield 'seven' => ['7d', 5];
        yield 'three' => ['3s', 5];
    }
}
