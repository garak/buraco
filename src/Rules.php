<?php

namespace Garak\Buraco;

use Garak\Card\Card;
use Garak\Card\Rank;

/**
 * Game parameters. Defaults follow the Italian tournament rules (FITAB code): two decks with four jokers,
 * eleven cards each, two pozzetti of eleven, the last two cards of the stock are not playable, any rank can
 * form a set, and buracos are worth 300 (royal), 250 (dirty royal and super), 200 (clean and dirty super),
 * 150 (semi-clean) or 100 (dirty) points. See international() for the other common variant.
 */
final readonly class Rules
{
    public const int MIN_MELD_LENGTH = 3;
    public const int MAX_WILDCARDS = 1;
    public const int MAX_SET_NATURALS = 8;
    public const int MAX_RUN_LENGTH = 14;
    public const int BURACO_LENGTH = 7;

    /**
     * @param int             $unplayableStockCards    the game ends with the turn of the player who leaves this many cards in the stock
     * @param int|null        $semiCleanBuraco         null to count a semi-clean buraco as a dirty one
     * @param int|null        $royalBuraco             null to count a royal buraco as a clean one
     * @param int|null        $dirtyRoyalBuraco        null to count a dirty royal buraco as a dirty one
     * @param int|null        $superBuraco             null to count a super buraco as a clean one
     * @param int|null        $dirtySuperBuraco        null to count a dirty super buraco as a dirty one
     * @param bool            $cleanBuracoToClose      whether a clean buraco (rather than any buraco) is needed to close
     * @param list<Rank>|null $setRanks                ranks that can form a set, null for any
     * @param bool            $mustMeldAfterTakingPile whether a player who takes the discard pile must meld at least one card
     */
    public function __construct(
        public int $decks = 2,
        public int $jokers = 4,
        public int $handSize = 11,
        public int $pozzettoSize = 11,
        public int $unplayableStockCards = 2,
        public int $closingBonus = 100,
        public int $pozzettoPenalty = 100,
        public int $cleanBuraco = 200,
        public int $dirtyBuraco = 100,
        public ?int $semiCleanBuraco = 150,
        public ?int $royalBuraco = 300,
        public ?int $dirtyRoyalBuraco = 250,
        public ?int $superBuraco = 250,
        public ?int $dirtySuperBuraco = 200,
        public bool $cleanBuracoToClose = false,
        public ?array $setRanks = null,
        public bool $mustMeldAfterTakingPile = false,
    ) {
        if ($decks < 1) {
            throw new \InvalidArgumentException('At least one deck is required.');
        }
        if ($jokers < 0 || $jokers > $decks * 2) {
            throw new \InvalidArgumentException(\sprintf('Jokers must be between 0 and %d.', $decks * 2));
        }
        if ($handSize < 1 || $pozzettoSize < 1) {
            throw new \InvalidArgumentException('Hand and pozzetto sizes must be positive.');
        }
        if ($unplayableStockCards < 0) {
            throw new \InvalidArgumentException('Unplayable stock cards cannot be negative.');
        }
        foreach ([$closingBonus, $pozzettoPenalty, $cleanBuraco, $dirtyBuraco, $semiCleanBuraco, $royalBuraco, $dirtyRoyalBuraco, $superBuraco, $dirtySuperBuraco] as $points) {
            if (null !== $points && $points < 0) {
                throw new \InvalidArgumentException('Points cannot be negative.');
            }
        }
        if ([] === $setRanks) {
            throw new \InvalidArgumentException('Set ranks cannot be empty: pass null to allow any rank.');
        }
    }

    /**
     * The international variant (F.I.Bur. code): a clean buraco is needed to close, sets can be made only of
     * aces and threes, the discard pile can be taken only to meld, and there are no semi-clean, royal or super buracos.
     */
    public static function international(): self
    {
        return new self(
            semiCleanBuraco: null,
            royalBuraco: null,
            dirtyRoyalBuraco: null,
            superBuraco: null,
            dirtySuperBuraco: null,
            cleanBuracoToClose: true,
            setRanks: [Rank::Ace, Rank::Three],
            mustMeldAfterTakingPile: true,
        );
    }

    /**
     * Builds the deck for a game, unshuffled: all regular cards from every deck, plus the configured number of jokers.
     *
     * @return list<Card>
     */
    public function createDeck(): array
    {
        $jokersLeft = $this->jokers;
        $deck = [];
        foreach (Card::getDeck(num: $this->decks, allowJokers: $this->jokers > 0) as $card) {
            if (Rank::Joker === $card->getRank()) {
                if ($jokersLeft <= 0) {
                    continue;
                }
                --$jokersLeft;
            }
            $deck[] = $card;
        }

        return $deck;
    }

    /**
     * Bonus for a buraco of the given kind.
     */
    public function getBuracoPoints(Buraco $buraco): int
    {
        return match ($buraco) {
            Buraco::Royal => $this->royalBuraco ?? $this->cleanBuraco,
            Buraco::DirtyRoyal => $this->dirtyRoyalBuraco ?? $this->dirtyBuraco,
            Buraco::Super => $this->superBuraco ?? $this->cleanBuraco,
            Buraco::DirtySuper => $this->dirtySuperBuraco ?? $this->dirtyBuraco,
            Buraco::Clean => $this->cleanBuraco,
            Buraco::SemiClean => $this->semiCleanBuraco ?? $this->dirtyBuraco,
            Buraco::Dirty => $this->dirtyBuraco,
        };
    }

    public function allowsSetOf(Rank $rank): bool
    {
        return null === $this->setRanks || \in_array($rank, $this->setRanks, true);
    }
}
