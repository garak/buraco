<?php

namespace Garak\Buraco;

use Garak\Buraco\Exception\InvalidMeldException;
use Garak\Card\Card;

/**
 * Finds out what can be melded with a bunch of cards: used to enforce the rule of the international variant,
 * where the discard pile can be taken only when at least one of its cards (or of the hand) can be melded.
 */
final class MeldFinder
{
    /**
     * Whether at least one of the cards can be attached to a meld of the table, or three of them can open a new meld.
     *
     * @param list<Card> $cards
     */
    public static function canMeld(array $cards, Table $table, Rules $rules): bool
    {
        foreach ($cards as $card) {
            foreach ($table->getMelds() as $meld) {
                if (null !== self::attach($meld, $card)) {
                    return true;
                }
            }
        }
        $taken = \array_map(static fn (Set $set) => $set->getRank(), $table->getSets());
        foreach ($cards as $i => $first) {
            foreach (\array_slice($cards, $i + 1) as $j => $second) {
                foreach (\array_slice($cards, $i + $j + 2) as $third) {
                    $meld = self::create([$first, $second, $third]);
                    if (null === $meld) {
                        continue;
                    }
                    if ($meld instanceof Set && (!$rules->allowsSetOf($meld->getRank()) || \in_array($meld->getRank(), $taken, true))) {
                        continue;
                    }

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extends a meld with a card, moving the wildcard of a run if needed. Returns null when the card does not fit.
     */
    public static function attach(Meld $meld, Card $card): ?Meld
    {
        if ($meld instanceof Set) {
            try {
                return new Set([...$meld->getCards(), $card]);
            } catch (InvalidMeldException) {
                return null;
            }
        }
        $naturals = $meld->getNaturalCards();
        $wildcard = $meld->getWildcard();
        $count = \count($naturals);
        for ($i = 0; $i <= $count; ++$i) {
            $cards = $naturals;
            \array_splice($cards, $i, 0, [$card]);
            if (null === $wildcard) {
                $run = self::run($cards);
                if (null !== $run) {
                    return $run;
                }
                continue;
            }
            for ($j = 0; $j <= $count + 1; ++$j) {
                $withWildcard = $cards;
                \array_splice($withWildcard, $j, 0, [$wildcard]);
                $run = self::run($withWildcard);
                if (null !== $run) {
                    return $run;
                }
            }
        }

        return null;
    }

    /**
     * A valid meld made of exactly the given three cards, in any order, or null.
     *
     * @param array{Card, Card, Card} $cards
     */
    private static function create(array $cards): ?Meld
    {
        try {
            return new Set($cards);
        } catch (InvalidMeldException) {
        }
        [$a, $b, $c] = $cards;
        foreach ([[$a, $b, $c], [$a, $c, $b], [$b, $a, $c], [$b, $c, $a], [$c, $a, $b], [$c, $b, $a]] as $order) {
            $run = self::run($order);
            if (null !== $run) {
                return $run;
            }
        }

        return null;
    }

    /** @param list<Card> $cards */
    private static function run(array $cards): ?Run
    {
        try {
            return new Run($cards);
        } catch (InvalidMeldException) {
            return null;
        }
    }
}
