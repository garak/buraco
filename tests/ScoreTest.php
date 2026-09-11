<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\Score;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScoreTest extends TestCase
{
    #[Test]
    public function total(): void
    {
        $score = new Score(buracos: 300, closing: 100, table: 85, hands: -25, pozzetto: -100);
        self::assertSame(360, $score->total);
    }
}
