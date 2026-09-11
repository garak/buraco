<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\Hand;
use Garak\Card\Card;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HandTest extends TestCase
{
    #[Test]
    public function keepsOrderButSortsForDisplay(): void
    {
        $hand = Hand::createFromString('Kh,Ah,2c,wb');
        self::assertSame('Kh,Ah,2c,wb', (string) $hand);
        self::assertSame('2♣ A♥ K♥ wb', $hand->toText());
    }

    #[Test]
    public function pointsOfCardsInHand(): void
    {
        self::assertSame(75, Hand::createFromString('Kh,Ah,2c,wb')->getPoints());
        self::assertSame(0, (new Hand([]))->getPoints());
    }

    #[Test]
    public function countFace(): void
    {
        $hand = Hand::createFromString('Khr,Khb,Kd');
        self::assertSame(2, $hand->countFace(Card::fromRankSuit('Kh')));
        self::assertSame(2, $hand->countFace(Card::fromRankSuit('Khr')));
        self::assertSame(1, $hand->countFace(Card::fromRankSuit('Kdb')));
        self::assertSame(0, $hand->countFace(Card::fromRankSuit('Ks')));
    }

    #[Test]
    public function addAndPlayKeepBacks(): void
    {
        $hand = Hand::createFromString('Khr,Khb');
        $hand = $hand->add(Card::fromRankSuit('Ahr'));
        self::assertCount(3, $hand);
        $hand = $hand->play(Card::fromRankSuit('Khb'));
        self::assertSame('Khr,Ahr', $hand->toString(true));
    }

    #[Test]
    public function customSortingAndChecking(): void
    {
        $checked = false;
        $hand = new Hand(
            [Card::fromRankSuit('2c'), Card::fromRankSuit('Ac')],
            checking: static function () use (&$checked): void { $checked = true; },
            sorting: static function (): void {},
        );
        self::assertTrue($checked);
        self::assertSame('2♣ A♣', $hand->toText());
    }
}
