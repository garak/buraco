<?php

namespace Garak\Buraco;

use Garak\Buraco\Exception\InvalidMeldException;
use Garak\Card\Rank;

/**
 * Three to nine cards of the same rank, suits are irrelevant (two decks, so a suit may repeat).
 * At most one wildcard (a joker or a two). A set cannot be made of twos.
 */
final class Set extends Meld
{
    private Rank $rank;

    protected function validate(): void
    {
        $wildcards = [];
        $naturals = [];
        foreach ($this->cards as $index => $card) {
            if (CardValue::isWildcard($card)) {
                $wildcards[] = $index;
            } else {
                $naturals[] = $card;
            }
        }
        if ([] === $naturals) {
            throw new InvalidMeldException(\sprintf('A set cannot be made of wildcards only, got %s.', $this));
        }
        if (\count($wildcards) > Rules::MAX_WILDCARDS) {
            throw new InvalidMeldException(\sprintf('A set cannot have more than %d wildcard, got %s.', Rules::MAX_WILDCARDS, $this));
        }
        if (\count($naturals) > Rules::MAX_SET_NATURALS) {
            throw new InvalidMeldException(\sprintf('A set cannot have more than %d natural cards, got %s.', Rules::MAX_SET_NATURALS, $this));
        }
        $rank = \array_first($naturals)->getRank();
        foreach ($naturals as $card) {
            if (!$card->getRank()->isEqual($rank)) {
                throw new InvalidMeldException(\sprintf('All cards in a set must share the rank, got %s.', $this));
            }
        }
        $this->wildcardIndex = \array_first($wildcards);
        $this->rank = $rank;
    }

    public function getBuraco(): ?Buraco
    {
        $count = \count($this->cards);
        if ($count < Rules::BURACO_LENGTH) {
            return null;
        }
        $naturals = \count($this->getNaturalCards());
        if (null === $this->wildcardIndex) {
            return $naturals >= Rules::MAX_SET_NATURALS ? Buraco::Super : Buraco::Clean;
        }
        if ($naturals >= Rules::MAX_SET_NATURALS) {
            return Buraco::DirtySuper;
        }

        return $naturals >= Rules::BURACO_LENGTH ? Buraco::SemiClean : Buraco::Dirty;
    }

    public function getRank(): Rank
    {
        return $this->rank;
    }
}
