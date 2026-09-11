<?php

namespace Garak\Buraco;

use Garak\Buraco\Exception\InvalidMeldException;
use Garak\Card\Card;

/**
 * A valid combination of cards laid on the table: a Set (cards of the same rank) or a Run (a sequence of the
 * same suit). Melds are immutable and validated on construction. At most one card is used as a wildcard.
 */
abstract class Meld implements \Countable, \Stringable
{
    /** @var list<Card> */
    protected array $cards;

    /** Position of the card used as a wildcard, if any */
    protected ?int $wildcardIndex = null;

    /**
     * @param array<int|string, Card> $cards for runs, in ascending order
     *
     * @throws InvalidMeldException
     */
    final public function __construct(array $cards)
    {
        $this->cards = \array_values($cards);
        $count = \count($this->cards);
        if ($count < Rules::MIN_MELD_LENGTH) {
            throw new InvalidMeldException(\sprintf('A meld needs at least %d cards, %d given.', Rules::MIN_MELD_LENGTH, $count));
        }
        $this->validate();
    }

    /**
     * Guesses the meld type: a Set when all the cards that are not jokers or twos share the rank, a Run otherwise.
     * A set that turns out to be invalid is retried as a run (e.g. "2h,3h,wb" is a run with a natural two).
     *
     * @param array<int|string, Card> $cards
     * @param bool                    $anyOrder whether the cards of a run may be given in any order (see Run::fromAnyOrder());
     *                                          when no order makes a run of them, the error is about the cards as given
     *
     * @throws InvalidMeldException
     */
    public static function fromCards(array $cards, bool $anyOrder = false): self
    {
        try {
            return self::guess($cards);
        } catch (InvalidMeldException $e) {
            if (!$anyOrder) {
                throw $e;
            }

            return Run::fromAnyOrder($cards) ?? throw $e;
        }
    }

    /**
     * @param string $cards    comma-separated cards (e.g. "5h,5d,wb")
     * @param bool   $anyOrder see fromCards()
     */
    public static function createFromString(string $cards, bool $anyOrder = false): self
    {
        return static::fromCards(\array_map(static fn (string $rs): Card => Card::fromRankSuit($rs), \explode(',', $cards)), $anyOrder);
    }

    /**
     * @param array<int|string, Card> $cards
     *
     * @throws InvalidMeldException
     */
    private static function guess(array $cards): self
    {
        $regular = \array_filter($cards, static fn (Card $card): bool => !CardValue::isWildcard($card));
        $ranks = \array_unique(\array_map(static fn (Card $card): string => $card->getRank()->value, $regular));
        if (\count($ranks) > 1) {
            return new Run($cards);
        }
        try {
            return new Set($cards);
        } catch (InvalidMeldException $e) {
            try {
                return new Run($cards);
            } catch (InvalidMeldException) {
                throw $e;
            }
        }
    }

    /**
     * @throws InvalidMeldException
     */
    abstract protected function validate(): void;

    /**
     * The kind of buraco this meld is, or null when it has fewer than seven cards.
     */
    abstract public function getBuraco(): ?Buraco;

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(bool $withBack = false): string
    {
        return \implode(',', \array_map(static fn (Card $card): string => $card->toString($withBack), $this->cards));
    }

    /** @return list<Card> */
    public function getCards(): array
    {
        return $this->cards;
    }

    /**
     * Cards not used as wildcards. A two in its natural place in a run is natural.
     *
     * @return list<Card>
     */
    public function getNaturalCards(): array
    {
        return \array_values(\array_filter($this->cards, fn (Card $card, int $index): bool => $index !== $this->wildcardIndex, \ARRAY_FILTER_USE_BOTH));
    }

    public function getWildcard(): ?Card
    {
        return null === $this->wildcardIndex ? null : $this->cards[$this->wildcardIndex];
    }

    public function hasWildcard(): bool
    {
        return null !== $this->wildcardIndex;
    }

    /**
     * Total value of the cards, as counted at the end of the game.
     */
    public function getPoints(): int
    {
        return \array_sum(\array_map(static fn (Card $card): int => CardValue::points($card), $this->cards));
    }

    public function isBuraco(): bool
    {
        return \count($this->cards) >= Rules::BURACO_LENGTH;
    }

    public function count(): int
    {
        return \count($this->cards);
    }

    /**
     * Same cards, regardless of order.
     */
    public function hasSameCards(self $meld): bool
    {
        return (new CardBag($this->cards))->equals(new CardBag($meld->cards));
    }

    /**
     * Whether every card of the given meld is in this one.
     */
    public function contains(self $meld): bool
    {
        return (new CardBag($meld->cards))->isSubsetOf(new CardBag($this->cards));
    }
}
