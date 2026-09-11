<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\Meld;
use Garak\Buraco\MeldFinder;
use Garak\Buraco\Rules;
use Garak\Buraco\Table;
use Garak\Card\Card;
use Garak\Card\Rank;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MeldFinderTest extends TestCase
{
    #[Test]
    #[DataProvider('attachProvider')]
    public function attach(string $meld, string $card, ?string $expected): void
    {
        $result = MeldFinder::attach(Meld::createFromString($meld), Card::fromRankSuit($card));
        self::assertSame($expected, null === $result ? null : (string) $result);
    }

    /** @return iterable<string, array{string, string, ?string}> */
    public static function attachProvider(): iterable
    {
        yield 'card to a set' => ['Kh,Kd,Ks', 'Kc', 'Kh,Kd,Ks,Kc'];
        yield 'wildcard to a set' => ['Kh,Kd,Ks', 'wb', 'Kh,Kd,Ks,wb'];
        yield 'second wildcard to a set' => ['Kh,Kd,wb', '2s', null];
        yield 'wrong rank' => ['Kh,Kd,Ks', 'Qc', null];
        yield 'card after a run' => ['5h,6h,7h', '8h', '5h,6h,7h,8h'];
        yield 'card before a run' => ['5h,6h,7h', '4h', '4h,5h,6h,7h'];
        yield 'wildcard to a run' => ['5h,6h,7h', 'wb', 'wb,5h,6h,7h'];
        yield 'replacing the wildcard moves it' => ['5h,wb,7h', '6h', 'wb,5h,6h,7h'];
        yield 'card after the wildcard' => ['5h,6h,wb', '8h', '5h,6h,wb,8h'];
        yield 'wrong suit' => ['5h,6h,7h', '8c', null];
        yield 'not adjacent' => ['5h,6h,7h', 'Th', null];
        yield 'second wildcard to a run' => ['5h,6h,wb', '2c', null];
        yield 'natural two' => ['3h,4h,5h', '2h', '2h,3h,4h,5h'];
    }

    #[Test]
    #[DataProvider('canMeldProvider')]
    public function canMeld(string $cards, string $table, bool $expected, ?Rules $rules = null): void
    {
        $cards = \array_map(static fn (string $rs): Card => Card::fromRankSuit($rs), \explode(',', $cards));
        self::assertSame($expected, MeldFinder::canMeld($cards, Table::createFromString($table), $rules ?? new Rules()));
    }

    /** @return iterable<string, array{string, string, bool, 3?: Rules}> */
    public static function canMeldProvider(): iterable
    {
        yield 'nothing' => ['Kh,5c,9d', '', false];
        yield 'a set' => ['Kh,5c,9d,Kd,Ks', '', true];
        yield 'a run' => ['Kh,5c,9d,6c,7c', '', true];
        yield 'a run out of order' => ['7c,5c,6c', '', true];
        yield 'a run with a wildcard' => ['Kh,5c,9d,7c,wb', '', true];
        yield 'attach to the table' => ['Kh,5c,9d', '9s,9h,9c', true];
        yield 'set rank not allowed' => ['Kh,Kd,Ks', '', false, Rules::international()];
        yield 'set rank allowed' => ['Ah,Ad,As', '', true, Rules::international()];
        yield 'attach to a set even if a new one would not be allowed' => ['Kh,5c,9d', 'Ks,Kd,Kc', true, Rules::international()];
        yield 'set of a rank already on the table cannot be opened' => ['9h,9h,9d', '9s,9h,9c,9d,9s,9h,9c,9d,wb', false];
    }

    #[Test]
    public function allowedSetRanks(): void
    {
        self::assertTrue((new Rules(setRanks: [Rank::Ace]))->allowsSetOf(Rank::Ace));
        self::assertFalse((new Rules(setRanks: [Rank::Ace]))->allowsSetOf(Rank::King));
    }
}
