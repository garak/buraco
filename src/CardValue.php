<?php

namespace Garak\Buraco;

use Garak\Card\Card;
use Garak\Card\CardValues;
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
        return $card->isJoker();
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
        return $card->isJoker() || self::isTwo($card);
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
        if ($rank->isJoker()) {
            throw new \InvalidArgumentException('A joker has no value on its own.');
        }

        return ($aceHigh ? CardValues::aceHigh() : CardValues::aceLow())->ofRank($rank);
    }

    /**
     * Points of a card: positive when melded on the table, negative when left in hand at the end of the game.
     */
    public static function points(Card $card): int
    {
        return self::pointValues()->of($card);
    }

    /**
     * Points of the cards, see points().
     *
     * @param iterable<Card> $cards
     */
    public static function sum(iterable $cards): int
    {
        return self::pointValues()->sum($cards);
    }

    private static function pointValues(): CardValues
    {
        static $values = new CardValues([
            Rank::Joker->value => 30,
            Rank::Two->value => 20,
            Rank::Ace->value => 15,
            Rank::King->value => 10,
            Rank::Queen->value => 10,
            Rank::Jack->value => 10,
            Rank::Ten->value => 10,
            Rank::Nine->value => 10,
            Rank::Eight->value => 10,
        ], default: 5);

        return $values;
    }
}
