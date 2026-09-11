<?php

namespace Garak\Buraco;

use Garak\Buraco\Exception\InvalidMeldException;
use Garak\Card\Card;
use Garak\Card\Suit;

/**
 * Three to fourteen consecutive cards of the same suit, given in ascending order.
 * The ace is low when it comes first (A,2,3) and high when it comes last (Q,K,A), never both.
 * Jokers and twos are wildcards and stand for the card implied by their position, with two exceptions:
 * a two of the same suit sitting right before the three is a natural two, and a wildcard placed after the king
 * stands for a high ace (so a full A to K run can hold a wildcard as its fourteenth card).
 * At most one wildcard per run.
 */
final class Run extends Meld
{
    /** A valid run holds one wildcard at most, plus maybe a natural two: more movable cards are not worth trying. */
    private const int MAX_MOVABLE = 2;

    /** @var list<int> */
    private array $values;

    private Suit $suit;

    /**
     * The run the cards make in some order, or null when no order makes one.
     *
     * The natural cards go in ascending order, the ace low unless it only fits high. A wildcard is tried in every
     * place, from the end of the run backwards, so that "3h,2h,wb" reads as 2h,3h,wb rather than wb,2h,3h; a two of
     * the run's suit is tried in its natural place first ("4c,2c,3c" is 2c,3c,4c, not 3c,4c,2c).
     *
     * @param array<int|string, Card> $cards
     */
    public static function fromAnyOrder(array $cards): ?self
    {
        $cards = \array_values($cards);
        $movable = \array_values(\array_filter($cards, static fn (Card $card): bool => CardValue::isWildcard($card)));
        if (\count($movable) > self::MAX_MOVABLE) {
            return null;
        }
        $naturals = \array_values(\array_filter($cards, static fn (Card $card): bool => !CardValue::isWildcard($card)));
        if ([] === $naturals) {
            return null;
        }
        $suit = $naturals[0]->getSuit();
        foreach ([false, true] as $aceHigh) {
            \usort($naturals, static fn (Card $a, Card $b): int => CardValue::of($a, $aceHigh) <=> CardValue::of($b, $aceHigh));
            if (null !== $run = self::place($naturals, $movable, $suit)) {
                return $run;
            }
        }

        return null;
    }

    /**
     * @param list<Card> $cards   in order
     * @param list<Card> $movable the cards still to find a place for
     */
    private static function place(array $cards, array $movable, Suit $suit): ?self
    {
        if ([] === $movable) {
            try {
                return new self($cards);
            } catch (InvalidMeldException) {
                return null;
            }
        }
        $card = \array_shift($movable);
        $positions = \range(\count($cards), 0);
        if (CardValue::isTwo($card) && $card->getSuit()->isEqual($suit)) {
            \array_unshift($positions, 0);
        }
        foreach ($positions as $i) {
            $attempt = $cards;
            \array_splice($attempt, $i, 0, [$card]);
            if (null !== $run = self::place($attempt, $movable, $suit)) {
                return $run;
            }
        }

        return null;
    }

    protected function validate(): void
    {
        $count = \count($this->cards);
        if ($count > Rules::MAX_RUN_LENGTH) {
            throw new InvalidMeldException(\sprintf('A run cannot have more than %d cards, got %s.', Rules::MAX_RUN_LENGTH, $this));
        }
        $suit = null;
        $first = null;
        foreach ($this->cards as $index => $card) {
            if (CardValue::isWildcard($card)) {
                continue;
            }
            $suit ??= $card->getSuit();
            if (!$card->getSuit()->isEqual($suit)) {
                throw new InvalidMeldException(\sprintf('All cards in a run must share the suit, got %s.', $this));
            }
            $candidate = CardValue::of($card, aceHigh: $index > 0) - $index;
            if (null !== $first && $candidate !== $first) {
                throw new InvalidMeldException(\sprintf('Cards in a run must be consecutive, got %s.', $this));
            }
            $first = $candidate;
        }
        if (null === $suit || null === $first) {
            throw new InvalidMeldException(\sprintf('A run cannot be made of wildcards only, got %s.', $this));
        }
        $last = $first + $count - 1;
        if ($first < CardValue::ACE_LOW || $last > CardValue::ACE_HIGH) {
            throw new InvalidMeldException(\sprintf('A run cannot go below the ace or above the ace, got %s.', $this));
        }
        if (CardValue::ACE_LOW === $first && CardValue::ACE_HIGH === $last && !CardValue::isWildcard($this->cards[0]) && !CardValue::isWildcard($this->cards[$count - 1])) {
            throw new InvalidMeldException(\sprintf('The ace cannot be both low and high, got %s.', $this));
        }
        foreach ($this->cards as $index => $card) {
            $naturalTwo = CardValue::isTwo($card) && $card->getSuit()->isEqual($suit) && 2 === $first + $index;
            if ($naturalTwo || !CardValue::isWildcard($card)) {
                continue;
            }
            if (null !== $this->wildcardIndex) {
                throw new InvalidMeldException(\sprintf('A run cannot have more than %d wildcard, got %s.', Rules::MAX_WILDCARDS, $this));
            }
            $this->wildcardIndex = $index;
        }
        $this->values = \range($first, $last);
        $this->suit = $suit;
    }

    public function getBuraco(): ?Buraco
    {
        $count = \count($this->cards);
        if ($count < Rules::BURACO_LENGTH) {
            return null;
        }
        if (null === $this->wildcardIndex) {
            return $count >= Rules::MAX_RUN_LENGTH - 1 ? Buraco::Royal : Buraco::Clean;
        }
        if ($count >= Rules::MAX_RUN_LENGTH - 1) {
            return Buraco::DirtyRoyal;
        }
        $atEnd = 0 === $this->wildcardIndex || $count - 1 === $this->wildcardIndex;
        if ($atEnd && $count - 1 >= Rules::BURACO_LENGTH) {
            return Buraco::SemiClean;
        }

        return Buraco::Dirty;
    }

    /**
     * Value of each position in the run, wildcards resolved: 1 (low ace) to 14 (high ace).
     *
     * @return list<int>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function getSuit(): Suit
    {
        return $this->suit;
    }
}
