<?php

namespace Garak\Buraco;

use Garak\Card\CardComparator;
use Garak\Card\Hand as BaseHand;

/**
 * Sorted for display by suit, then by value (ace after the king). Jokers go last.
 */
final class Hand extends BaseHand
{
    public function __construct(array $cards, bool $start = true, ?callable $checking = null, ?callable $sorting = null)
    {
        $this->cards = \array_values($cards);
        $this->sorting = $sorting ?? function (): void {
            $this->cards = (new CardComparator())->sort($this->cards);
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
        return CardValue::sum($this->cards);
    }
}
