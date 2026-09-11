<?php

namespace Garak\Buraco;

use Garak\Card\Card;
use Garak\Card\Hand as BaseHand;

final class Hand extends BaseHand
{
    public function __construct(array $cards, bool $start = true, ?callable $checking = null, ?callable $sorting = null)
    {
        $this->cards = \array_values($cards);
        $this->sorting = $sorting ?? function (): void {
            CardSorter::sort($this->cards);
        };
        if ($start && null !== $checking) {
            $checking($cards);
        }
    }

    /**
     * Value of the cards in hand: charged to the player when the game ends.
     */
    public function getPoints(): int
    {
        return \array_sum(\array_map(static fn (Card $card): int => CardValue::points($card), $this->cards));
    }

    /**
     * How many cards with the same face (rank and suit, whatever the back) are in hand.
     */
    public function countFace(Card $card): int
    {
        return \count(\array_filter($this->cards, static fn (Card $c): bool => $card->isSameFace($c)));
    }
}
