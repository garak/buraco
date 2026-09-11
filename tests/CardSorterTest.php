<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\CardSorter;
use Garak\Card\Card;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CardSorterTest extends TestCase
{
    #[Test]
    public function sortBySuitThenValueJokersLast(): void
    {
        $cards = \array_map(static fn (string $rs): Card => Card::fromRankSuit($rs), ['wb', 'Kh', 'Ah', '2c', 'Ac', 'Td', 'wr', '3s', '2h']);
        CardSorter::sort($cards);
        self::assertSame('Ac,2c,Td,Ah,2h,Kh,3s,wb,wr', \implode(',', $cards));
    }
}
