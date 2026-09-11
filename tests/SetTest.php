<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\Buraco;
use Garak\Buraco\Exception\InvalidMeldException;
use Garak\Buraco\Set;
use Garak\Card\Card;
use Garak\Card\Rank;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TODO when PHP 8.3 support is dropped (PHPUnit 13 only): the try/catch block in invalidSets() can become
 * expectException() + expectExceptionMessageIsOrContains(), which is missing in PHPUnit 12.
 */
final class SetTest extends TestCase
{
    #[Test]
    #[DataProvider('validProvider')]
    public function validSets(string $cards, Rank $rank, ?string $wildcard): void
    {
        $set = new Set(self::cards($cards));
        self::assertSame($rank, $set->getRank());
        self::assertSame($wildcard, null === $set->getWildcard() ? null : (string) $set->getWildcard());
        self::assertSame($cards, $set->toString(true));
    }

    /** @return iterable<string, array{string, Rank, ?string}> */
    public static function validProvider(): iterable
    {
        yield 'three kings' => ['Kh,Kd,Ks', Rank::King, null];
        yield 'same suit twice' => ['Kh,Kh,Ks', Rank::King, null];
        yield 'same suit twice from two decks' => ['Khr,Khb,Ks', Rank::King, null];
        yield 'four aces' => ['Ah,Ad,As,Ac', Rank::Ace, null];
        yield 'joker' => ['5h,wb,5s', Rank::Five, 'wb'];
        yield 'two as wildcard' => ['5h,5s,2s', Rank::Five, '2s'];
        yield 'two naturals plus wildcard' => ['wb,9c,9d', Rank::Nine, 'wb'];
        yield 'nine cards' => ['7h,7d,7s,7c,7h,7d,7s,7c,wb', Rank::Seven, 'wb'];
    }

    #[Test]
    #[DataProvider('invalidProvider')]
    public function invalidSets(string $cards, string $message): void
    {
        try {
            new Set(self::cards($cards));
            self::fail(\sprintf('Set "%s" should be invalid.', $cards));
        } catch (InvalidMeldException $e) {
            self::assertStringContainsString($message, $e->getMessage());
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidProvider(): iterable
    {
        yield 'too short' => ['Kh,Kd', 'at least 3 cards'];
        yield 'too many naturals' => ['7h,7d,7s,7c,7h,7d,7s,7c,7h', 'more than 8 natural cards'];
        yield 'different ranks' => ['Kh,Kd,Qs', 'share the rank'];
        yield 'two wildcards' => ['Kh,wb,wr', 'more than 1 wildcard'];
        yield 'joker and two' => ['Kh,Kd,wb,2s', 'more than 1 wildcard'];
        yield 'only jokers' => ['wb,wr,wb', 'wildcards only'];
        yield 'only twos' => ['2h,2d,2s', 'wildcards only'];
        yield 'a run is not a set' => ['5h,6h,7h', 'share the rank'];
    }

    #[Test]
    #[DataProvider('buracoProvider')]
    public function buraco(string $cards, ?Buraco $expected): void
    {
        self::assertSame($expected, (new Set(self::cards($cards)))->getBuraco());
    }

    /** @return iterable<string, array{string, ?Buraco}> */
    public static function buracoProvider(): iterable
    {
        yield 'six cards' => ['7h,7d,7s,7c,7h,7d', null];
        yield 'clean' => ['7h,7d,7s,7c,7h,7d,7s', Buraco::Clean];
        yield 'dirty' => ['7h,7d,7s,7c,7h,7d,wb', Buraco::Dirty];
        yield 'semi-clean' => ['7h,7d,7s,7c,7h,7d,7s,wb', Buraco::SemiClean];
        yield 'super' => ['7h,7d,7s,7c,7h,7d,7s,7c', Buraco::Super];
        yield 'dirty super' => ['7h,7d,7s,7c,7h,7d,7s,7c,2d', Buraco::DirtySuper];
    }

    /** @return list<Card> */
    private static function cards(string $cards): array
    {
        return \array_map(static fn (string $rs): Card => Card::fromRankSuit($rs), \explode(',', $cards));
    }
}
