<?php

namespace Garak\Buraco;

use Garak\Buraco\Exception\InvalidMeldException;
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
    /** @var list<int> */
    private array $values;

    private Suit $suit;

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
