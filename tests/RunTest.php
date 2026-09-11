<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\Buraco;
use Garak\Buraco\Exception\InvalidMeldException;
use Garak\Buraco\Run;
use Garak\Card\Card;
use Garak\Card\Suit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TODO when PHP 8.3 support is dropped (PHPUnit 13 only): the try/catch block in invalidRuns() can become
 * expectException() + expectExceptionMessageIsOrContains(), which is missing in PHPUnit 12.
 */
final class RunTest extends TestCase
{
    /** @param list<int> $values */
    #[Test]
    #[DataProvider('validProvider')]
    public function validRuns(string $cards, array $values, ?string $wildcard): void
    {
        $run = new Run(self::cards($cards));
        self::assertSame($values, $run->getValues());
        self::assertSame($wildcard, null === $run->getWildcard() ? null : (string) $run->getWildcard());
        self::assertSame($cards, $run->toString(true));
    }

    /** @return iterable<string, array{string, list<int>, ?string}> */
    public static function validProvider(): iterable
    {
        yield 'ace low' => ['Ah,2h,3h', [1, 2, 3], null];
        yield 'ace high' => ['Qs,Ks,As', [12, 13, 14], null];
        yield 'natural two' => ['2h,3h,4h', [2, 3, 4], null];
        yield 'joker in the middle' => ['5d,wb,7d', [5, 6, 7], 'wb'];
        yield 'two as wildcard in the middle' => ['5d,2c,7d', [5, 6, 7], '2c'];
        yield 'two of the same suit as wildcard' => ['5d,2d,7d', [5, 6, 7], '2d'];
        yield 'joker at the start' => ['wb,5d,6d', [4, 5, 6], 'wb'];
        yield 'joker at the end' => ['5d,6d,wr', [5, 6, 7], 'wr'];
        yield 'joker as low ace' => ['wb,2h,3h', [1, 2, 3], 'wb'];
        yield 'joker as high ace' => ['Qh,Kh,wb', [12, 13, 14], 'wb'];
        yield 'natural two plus wildcard' => ['Ah,2h,3h,2c', [1, 2, 3, 4], '2c'];
        yield 'two of the suit out of place is a wildcard' => ['3h,4h,5h,2h', [3, 4, 5, 6], '2h'];
        yield 'full suit' => ['Ac,2c,3c,4c,5c,6c,7c,8c,9c,Tc,Jc,Qc,Kc', \range(1, 13), null];
        yield 'full suit ace high' => ['2c,3c,4c,5c,6c,7c,8c,9c,Tc,Jc,Qc,Kc,Ac', \range(2, 14), null];
        yield 'full suit plus a wildcard as high ace' => ['Ac,2c,3c,4c,5c,6c,7c,8c,9c,Tc,Jc,Qc,Kc,wb', \range(1, 14), 'wb'];
        yield 'full suit plus a wildcard as low ace' => ['wb,2c,3c,4c,5c,6c,7c,8c,9c,Tc,Jc,Qc,Kc,Ac', \range(1, 14), 'wb'];
        yield 'backs are irrelevant' => ['Ahr,2hb,3hr', [1, 2, 3], null];
    }

    #[Test]
    #[DataProvider('invalidProvider')]
    public function invalidRuns(string $cards, string $message): void
    {
        try {
            new Run(self::cards($cards));
            self::fail(\sprintf('Run "%s" should be invalid.', $cards));
        } catch (InvalidMeldException $e) {
            self::assertStringContainsString($message, $e->getMessage());
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidProvider(): iterable
    {
        yield 'too short' => ['Ah,2h', 'at least 3 cards'];
        yield 'too long' => ['wb,Ac,2c,3c,4c,5c,6c,7c,8c,9c,Tc,Jc,Qc,Kc,Ac', 'more than 14 cards'];
        yield 'different suits' => ['Ah,2h,3c', 'share the suit'];
        yield 'not consecutive' => ['Ah,2h,4h', 'consecutive'];
        yield 'descending' => ['3h,2h,Ah', 'consecutive'];
        yield 'duplicate' => ['5h,5h,6h', 'consecutive'];
        yield 'joker below the low ace' => ['wb,Ah,2h', 'above the ace'];
        yield 'joker above the high ace' => ['Kh,Ah,wb', 'above the ace'];
        yield 'wrapping around' => ['Kh,Ah,2h', 'above the ace'];
        yield 'ace in the middle' => ['Kh,Ah,2h,3h', 'consecutive'];
        yield 'ace both low and high' => ['Ac,2c,3c,4c,5c,6c,7c,8c,9c,Tc,Jc,Qc,Kc,Ac', 'both low and high'];
        yield 'two wildcards' => ['wb,5h,wr', 'more than 1 wildcard'];
        yield 'two twos as wildcards' => ['2c,5h,2d', 'more than 1 wildcard'];
        yield 'only wildcards' => ['wb,wr,2c', 'wildcards only'];
        yield 'only twos' => ['2h,2h,2h', 'wildcards only'];
        yield 'a set is not a run' => ['Kh,Kd,Ks', 'share the suit'];
    }

    #[Test]
    #[DataProvider('anyOrderProvider')]
    public function fromAnyOrder(string $cards, string $expected, ?string $wildcard): void
    {
        $run = Run::fromAnyOrder(self::cards($cards));
        self::assertNotNull($run, \sprintf('Some order of "%s" makes a run.', $cards));
        self::assertSame($expected, $run->toString(true));
        self::assertSame($wildcard, null === $run->getWildcard() ? null : (string) $run->getWildcard());
    }

    /** @return iterable<string, array{string, string, ?string}> */
    public static function anyOrderProvider(): iterable
    {
        yield 'already in order' => ['Ah,2h,3h', 'Ah,2h,3h', null];
        yield 'descending' => ['3h,2h,Ah', 'Ah,2h,3h', null];
        yield 'shuffled' => ['5d,3d,6d,4d', '3d,4d,5d,6d', null];
        yield 'ace high when it only fits high' => ['As,Qs,Ks', 'Qs,Ks,As', null];
        yield 'ace low when it fits low' => ['Kc,Qc,Jc,Tc,9c,8c,7c,6c,5c,4c,3c,2c,Ac', 'Ac,2c,3c,4c,5c,6c,7c,8c,9c,Tc,Jc,Qc,Kc', null];
        yield 'joker at the end rather than at the start' => ['3h,2h,wb', '2h,3h,wb', 'wb'];
        yield 'joker in the middle' => ['7d,wb,5d', '5d,wb,7d', 'wb'];
        yield 'joker as high ace' => ['wb,Kh,Qh', 'Qh,Kh,wb', 'wb'];
        yield 'joker as low ace' => ['3h,wb,Ah', 'Ah,wb,3h', 'wb'];
        yield 'two of the suit in its natural place' => ['4c,2c,3c', '2c,3c,4c', null];
        yield 'two of the suit as a wildcard when it does not fit' => ['5h,4h,2h', '2h,4h,5h', '2h'];
        yield 'two of the suit as a wildcard below the ace high' => ['Kh,2h,Ah', '2h,Kh,Ah', '2h'];
        yield 'two of another suit as a wildcard' => ['7d,5d,2c', '5d,2c,7d', '2c'];
        yield 'natural two plus a wildcard' => ['2c,3h,Ah,2h', 'Ah,2h,3h,2c', '2c'];
        yield 'natural two rather than a wildcard' => ['wb,2h,4h,3h', '2h,3h,4h,wb', 'wb'];
        yield 'backs are irrelevant' => ['3hr,2hb,Ahr', 'Ahr,2hb,3hr', null];
    }

    #[Test]
    public function fromAnyOrderIgnoresKeys(): void
    {
        $run = Run::fromAnyOrder(['a' => Card::fromRankSuit('3h'), 'b' => Card::fromRankSuit('Ah'), 'c' => Card::fromRankSuit('2h')]);
        self::assertNotNull($run);
        self::assertSame('Ah,2h,3h', (string) $run);
    }

    #[Test]
    #[DataProvider('noOrderProvider')]
    public function noOrderMakesARun(string $cards): void
    {
        self::assertNull(Run::fromAnyOrder(self::cards($cards)));
    }

    /** @return iterable<string, array{string}> */
    public static function noOrderProvider(): iterable
    {
        yield 'too short' => ['2h,Ah'];
        yield 'different suits' => ['Ah,3c,2h'];
        yield 'not consecutive' => ['4h,Ah,2h'];
        yield 'duplicate' => ['5h,6h,5h'];
        yield 'ace in the middle' => ['Kh,Ah,2h,3h'];
        yield 'ace both low and high' => ['Ac,2c,3c,4c,5c,6c,7c,8c,9c,Tc,Jc,Qc,Kc,Ac'];
        yield 'two wildcards' => ['wr,5h,wb'];
        yield 'three wildcards' => ['2c,5h,wb,wr'];
        yield 'only wildcards' => ['wb,2c,wr'];
        yield 'a set is not a run' => ['Kh,Kd,Ks'];
    }

    #[Test]
    #[DataProvider('buracoProvider')]
    public function buraco(string $cards, ?Buraco $expected): void
    {
        self::assertSame($expected, (new Run(self::cards($cards)))->getBuraco());
    }

    /** @return iterable<string, array{string, ?Buraco}> */
    public static function buracoProvider(): iterable
    {
        yield 'six cards' => ['3h,4h,5h,6h,7h,8h', null];
        yield 'clean' => ['3h,4h,5h,6h,7h,8h,9h', Buraco::Clean];
        yield 'clean with natural two' => ['2h,3h,4h,5h,6h,7h,8h', Buraco::Clean];
        yield 'dirty' => ['3h,4h,5h,wb,7h,8h,9h', Buraco::Dirty];
        yield 'dirty with the wildcard at the end but only six naturals' => ['3h,4h,5h,6h,7h,8h,wb', Buraco::Dirty];
        yield 'semi-clean, wildcard first' => ['wb,3h,4h,5h,6h,7h,8h,9h', Buraco::SemiClean];
        yield 'semi-clean, wildcard last' => ['3h,4h,5h,6h,7h,8h,9h,2c', Buraco::SemiClean];
        yield 'semi-clean with natural two' => ['2h,3h,4h,5h,6h,7h,8h,2c', Buraco::SemiClean];
        yield 'not semi-clean, wildcard inside' => ['3h,4h,5h,6h,wb,8h,9h,Th', Buraco::Dirty];
        yield 'royal' => ['Ac,2c,3c,4c,5c,6c,7c,8c,9c,Tc,Jc,Qc,Kc', Buraco::Royal];
        yield 'royal ace high' => ['2c,3c,4c,5c,6c,7c,8c,9c,Tc,Jc,Qc,Kc,Ac', Buraco::Royal];
        yield 'dirty royal, wildcard inside' => ['Ac,2c,3c,4c,5c,wb,7c,8c,9c,Tc,Jc,Qc,Kc', Buraco::DirtyRoyal];
        yield 'dirty royal, fourteen cards' => ['Ac,2c,3c,4c,5c,6c,7c,8c,9c,Tc,Jc,Qc,Kc,wb', Buraco::DirtyRoyal];
    }

    #[Test]
    public function suitOfTheRun(): void
    {
        self::assertSame(Suit::Diamonds, (new Run(self::cards('wb,5d,6d')))->getSuit());
        self::assertSame(Suit::Diamonds, (new Run(self::cards('2h,5d,6d')))->getSuit());
    }

    /** @return list<Card> */
    private static function cards(string $cards): array
    {
        return \array_map(static fn (string $rs): Card => Card::fromRankSuit($rs), \explode(',', $cards));
    }
}
