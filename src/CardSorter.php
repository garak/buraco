<?php

namespace Garak\Buraco;

use Garak\Card\Card;

/**
 * Sorts by suit, then by value (ace after the king). Jokers go last.
 */
final class CardSorter
{
    /** @param array<int|string, Card> $cards */
    public static function sort(array &$cards): void
    {
        \usort($cards, static function (Card $card1, Card $card2): int {
            $joker1 = CardValue::isJoker($card1);
            $joker2 = CardValue::isJoker($card2);
            if ($joker1 || $joker2) {
                return $joker1 <=> $joker2;
            }
            if (!$card1->getSuit()->isEqual($card2->getSuit())) {
                return $card1->getSuit()->getInt() <=> $card2->getSuit()->getInt();
            }

            return CardValue::of($card1, aceHigh: true) <=> CardValue::of($card2, aceHigh: true);
        });
    }
}
