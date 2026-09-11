<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\Exception\InvalidMeldException;
use Garak\Buraco\Meld;
use Garak\Buraco\Table;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    #[Test]
    public function emptyTable(): void
    {
        $table = Table::createFromString('');
        self::assertTrue($table->isEmpty());
        self::assertCount(0, $table);
        self::assertSame([], $table->getCards());
        self::assertSame('', (string) $table);
        self::assertSame(0, $table->getPoints());
        self::assertFalse($table->hasBuraco());
    }

    #[Test]
    public function cardsOfAllMelds(): void
    {
        $table = Table::createFromString('Kh,Kd,Ks;Ahr,2hb,3h');
        self::assertCount(2, $table);
        self::assertSame('Kh,Kd,Ks,Ah,2h,3h', \implode(',', $table->getCards()));
        self::assertSame('Kh,Kd,Ks;Ah,2h,3h', (string) $table);
        self::assertSame('Kh,Kd,Ks;Ahr,2hb,3h', $table->toString(true));
        self::assertCount(2, $table->getMelds());
        self::assertSame('Kh,Kd,Ks', \implode(' ', $table->getSets()));
        self::assertSame('Ah,2h,3h', \implode(' ', $table->getRuns()));
        self::assertSame(30 + 15 + 20 + 5, $table->getPoints());
    }

    #[Test]
    public function buracos(): void
    {
        $table = Table::createFromString('Kh,Kd,Ks;3h,4h,5h,6h,7h,8h,wb;9c,9d,9s,9c,9d,9s,9h');
        self::assertSame('3h,4h,5h,6h,7h,8h,wb 9c,9d,9s,9c,9d,9s,9h', \implode(' ', $table->getBuracos()));
        self::assertTrue($table->hasBuraco());
        self::assertTrue($table->hasBuraco(clean: true));
        $dirtyOnly = Table::createFromString('Kh,Kd,Ks;3h,4h,5h,6h,7h,8h,wb');
        self::assertTrue($dirtyOnly->hasBuraco());
        self::assertFalse($dirtyOnly->hasBuraco(clean: true));
    }

    #[Test]
    public function anyOrder(): void
    {
        self::assertSame('Kh,Kd,Ks;Ah,2h,3h', (string) Table::createFromString('Kh,Kd,Ks;3h,2h,Ah', anyOrder: true));
        $this->expectException(InvalidMeldException::class);
        Table::createFromString('Kh,Kd,Ks;3h,2h,Ah');
    }

    #[Test]
    public function meldsAreReindexed(): void
    {
        $table = new Table(['a' => Meld::createFromString('Kh,Kd,Ks')]);
        self::assertSame([0], \array_keys($table->getMelds()));
    }
}
