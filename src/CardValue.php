<?php

namespace Garak\Buraco;

use Garak\Card\Card;
use Garak\Card\Rank;

/**
 * Card values in buraco: the position of a card in a run (ace low or high) and the points it is worth.
 * Jokers and twos are wildcards (a two of the right suit in its natural place is not, see Run).
 */
final class CardValue
{
    public const int ACE_LOW = 1;
    public const int ACE_HIGH = 14;

    public static function isJoker(Card $card): bool
    {
        return Rank::Joker === $card->getRank();
    }

    public static function isTwo(Card $card): bool
    {
        return Rank::Two === $card->getRank();
    }

    /**
     * Whether the card can be used as a wildcard: a joker or a two.
     */
    public static function isWildcard(Card $card): bool
    {
        return self::isJoker($card) || self::isTwo($card);
    }

    /**
     * Position of a card in a run, 1 (ace) to 13 (king), or 14 for an ace played high.
     */
    public static function of(Card $card, bool $aceHigh = false): int
    {
        return self::ofRank($card->getRank(), $aceHigh);
    }

    public static function ofRank(Rank $rank, bool $aceHigh = false): int
    {
        return match ($rank) {
            Rank::Ace => $aceHigh ? self::ACE_HIGH : self::ACE_LOW,
            Rank::Joker => throw new \InvalidArgumentException('A joker has no value on its own.'),
            default => $rank->getInt(),
        };
    }

    /**
     * Points of a card: positive when melded on the table, negative when left in hand at the end of the game.
     */
    public static function points(Card $card): int
    {
        return match ($card->getRank()) {
            Rank::Joker => 30,
            Rank::Two => 20,
            Rank::Ace => 15,
            Rank::King, Rank::Queen, Rank::Jack, Rank::Ten, Rank::Nine, Rank::Eight => 10,
            default => 5,
        };
    }
}
