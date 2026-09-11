<?php

namespace Garak\Buraco;

use Garak\Card\Card;

/**
 * The melds laid down by a team. Immutable: each play replaces the whole table.
 */
final readonly class Table implements \Countable, \Stringable
{
    /** @var list<Meld> */
    private array $melds;

    /** @param array<int|string, Meld> $melds */
    public function __construct(array $melds = [])
    {
        $this->melds = \array_values($melds);
    }

    /**
     * @param string $melds semicolon-separated melds, each one comma-separated (e.g. "5h,5d,5s;7c,8c,9c")
     */
    public static function createFromString(string $melds): self
    {
        if ('' === $melds) {
            return new self();
        }

        return new self(\array_map(static fn (string $meld): Meld => Meld::createFromString($meld), \explode(';', $melds)));
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(bool $withBack = false): string
    {
        return \implode(';', \array_map(static fn (Meld $meld): string => $meld->toString($withBack), $this->melds));
    }

    /** @return list<Meld> */
    public function getMelds(): array
    {
        return $this->melds;
    }

    /** @return list<Set> */
    public function getSets(): array
    {
        return \array_values(\array_filter($this->melds, static fn (Meld $meld): bool => $meld instanceof Set));
    }

    /** @return list<Run> */
    public function getRuns(): array
    {
        return \array_values(\array_filter($this->melds, static fn (Meld $meld): bool => $meld instanceof Run));
    }

    /** @return list<Card> */
    public function getCards(): array
    {
        return \array_merge(...\array_map(static fn (Meld $meld): array => $meld->getCards(), $this->melds));
    }

    /**
     * Melds of seven or more cards.
     *
     * @return list<Meld>
     */
    public function getBuracos(): array
    {
        return \array_values(\array_filter($this->melds, static fn (Meld $meld): bool => $meld->isBuraco()));
    }

    /**
     * @param bool $clean whether only clean buracos count
     */
    public function hasBuraco(bool $clean = false): bool
    {
        return \array_any($this->melds, static fn (Meld $meld): bool => null !== $meld->getBuraco() && (!$clean || $meld->getBuraco()->isClean()));
    }

    /**
     * Total value of the cards on the table.
     */
    public function getPoints(): int
    {
        return \array_sum(\array_map(static fn (Meld $meld): int => $meld->getPoints(), $this->melds));
    }

    public function isEmpty(): bool
    {
        return [] === $this->melds;
    }

    public function count(): int
    {
        return \count($this->melds);
    }
}
