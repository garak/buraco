<?php

namespace Garak\Buraco;

/**
 * Breakdown of a team's score. Bonuses and melded cards count positive, cards in hand and a missing pozzetto
 * count negative. While the game is in progress the figures are provisional.
 */
final readonly class Score
{
    public int $total;

    /**
     * @param int $buracos  bonuses for the buracos on the table
     * @param int $closing  bonus for closing the game
     * @param int $table    value of the cards on the table
     * @param int $hands    value of the cards still in hand, as a negative number
     * @param int $pozzetto penalty for not having taken the pozzetto, as a negative number
     */
    public function __construct(
        public int $buracos,
        public int $closing,
        public int $table,
        public int $hands,
        public int $pozzetto,
    ) {
        $this->total = $buracos + $closing + $table + $hands + $pozzetto;
    }
}
