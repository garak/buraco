<?php

namespace Garak\Buraco;

/**
 * The two sides of the game. With two players each player is a team; with four, partners sit opposite
 * (the first and third players joined are one team, the second and fourth the other).
 */
enum Team: int
{
    case First = 0;
    case Second = 1;

    public function getOpponent(): self
    {
        return match ($this) {
            self::First => self::Second,
            self::Second => self::First,
        };
    }
}
