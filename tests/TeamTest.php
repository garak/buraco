<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\Team;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TeamTest extends TestCase
{
    #[Test]
    public function opponent(): void
    {
        self::assertSame(Team::Second, Team::First->getOpponent());
        self::assertSame(Team::First, Team::Second->getOpponent());
        self::assertSame(Team::First, Team::First->getOpponent()->getOpponent());
    }

    #[Test]
    public function playersAlternateBetweenTeams(): void
    {
        self::assertSame(Team::First, Team::from(0));
        self::assertSame(Team::Second, Team::from(1));
        self::assertSame(Team::First, Team::from(2 % 2));
    }
}
