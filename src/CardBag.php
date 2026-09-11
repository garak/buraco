<?php

namespace Garak\Buraco;

use Garak\Card\Card;

/**
 * Multiset of cards, keyed by their full string form (back included), so that
 * identical faces from different decks are told apart.
 *
 * @internal
 */
final class CardBag
{
    /** @var array<string, int> */
    private array $counts = [];

    /** @param iterable<Card> $cards */
    public function __construct(iterable $cards = [])
    {
        foreach ($cards as $card) {
            $this->add($card);
        }
    }

    public function add(Card $card): void
    {
        $key = $card->toString(true);
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }

    /**
     * Cards in this bag that are not in the other one (with multiplicity).
     *
     * @return list<string>
     */
    public function diff(self $other): array
    {
        $missing = [];
        foreach ($this->counts as $key => $count) {
            $extra = $count - ($other->counts[$key] ?? 0);
            for ($i = 0; $i < $extra; ++$i) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    public function isSubsetOf(self $other): bool
    {
        return [] === $this->diff($other);
    }

    public function equals(self $other): bool
    {
        return $this->isSubsetOf($other) && $other->isSubsetOf($this);
    }
}
