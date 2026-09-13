<?php

namespace Garak\Buraco;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Garak\Buraco\Exception\IllegalMoveException;
use Garak\Buraco\Exception\NotYourTurnException;
use Garak\Card\Card;
use Garak\Card\Pile;

/**
 * A single hand ("smazzata") of buraco, for two players or two partnerships of two.
 *
 * On their turn a player first draws a card from the stock or takes the whole discard pile, then melds
 * as many cards as they like on the team's table (opening new melds, or adding cards to existing ones),
 * and ends the turn by discarding one card.
 * A player who runs out of cards takes the team's pozzetto and goes on with it; once the pozzetto is gone,
 * running out of cards means closing the game, which requires a buraco on the table and a final discard.
 * The game also ends, without closing bonus, when the stock is exhausted.
 */
class Game
{
    /** @var Collection<int, Player> */
    protected Collection $players;

    /** @var array<int, Hand> */
    private array $hands = [];

    /** @var array<int, Table> keyed by team */
    private array $tables = [];

    /** @var array<int, list<Card>|null> keyed by team, null once taken */
    private array $pozzetti = [];

    private Pile $stock;

    private Pile $discards;

    private GameStatus $status = GameStatus::Waiting;

    private TurnPhase $phase = TurnPhase::Draw;

    private int $current = 0;

    private bool $lastTurn = false;

    private ?Card $singleTaken = null;

    private ?Team $closer = null;

    public function __construct(private readonly Rules $rules = new Rules())
    {
        $this->players = new ArrayCollection();
        $this->stock = new Pile();
        $this->discards = new Pile();
    }

    public function getRules(): Rules
    {
        return $this->rules;
    }

    public function join(Player $player): void
    {
        if (GameStatus::Waiting !== $this->status) {
            throw new IllegalMoveException('Cannot join a game already started.');
        }
        if ($this->hasPlayer($player)) {
            throw new \InvalidArgumentException('Player already joined.');
        }
        $this->players->add($player);
    }

    public function hasPlayer(Player $player): bool
    {
        return null !== $this->findPlayer($player);
    }

    /** @return Collection<int, Player> */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function getTeam(Player $player): Team
    {
        return self::teamOfIndex($this->indexOf($player));
    }

    /** @return list<Player> */
    public function getTeamPlayers(Team $team): array
    {
        $players = [];
        foreach ($this->players as $index => $player) {
            if (self::teamOfIndex($index) === $team) {
                $players[] = $player;
            }
        }

        return $players;
    }

    /**
     * Starts the hand: deals the cards, sets the pozzetti aside, turns the first card of the stock face up.
     *
     * @param array<int, Card>|null $deck Pre-arranged deck, useful for testing: hand size cards to each player in turn,
     *                                    then pozzetto size cards to each team, then the card that starts the discard
     *                                    pile, then the stock (the next card to be drawn first).
     *                                    If null, a shuffled deck built from the rules is used.
     */
    public function deal(?array $deck = null): void
    {
        if (GameStatus::Waiting !== $this->status) {
            throw new IllegalMoveException('Cards already dealt.');
        }
        $count = $this->players->count();
        if (2 !== $count && 4 !== $count) {
            throw new IllegalMoveException('Two or four players are needed.');
        }
        $cards = $deck ?? $this->rules->createDeck();
        $needed = $count * $this->rules->handSize + 2 * $this->rules->pozzettoSize + 2 + $this->rules->unplayableStockCards;
        if (\count($cards) < $needed) {
            throw new IllegalMoveException(\sprintf('At least %d cards are needed to deal to %d players, %d given.', $needed, $count, \count($cards)));
        }
        // the stock is drawn from the top, so the first card of the deck must end up on top
        $this->stock = new Pile(\array_reverse($cards));
        if (null === $deck) {
            $this->stock->shuffle();
        }
        for ($i = 0; $i < $count; ++$i) {
            $this->hands[$i] = new Hand($this->drawMany($this->rules->handSize));
        }
        foreach (Team::cases() as $team) {
            $this->pozzetti[$team->value] = $this->drawMany($this->rules->pozzettoSize);
            $this->tables[$team->value] = new Table();
        }
        $this->discards = new Pile([$this->stock->draw()]);
        $this->status = GameStatus::Playing;
    }

    public function getStatus(): GameStatus
    {
        return $this->status;
    }

    public function isOver(): bool
    {
        return GameStatus::Ended === $this->status;
    }

    public function getCurrentPlayer(): Player
    {
        $this->assertPlaying();

        return $this->players->get($this->current) ?? throw new \LogicException('No current player.');
    }

    /**
     * What the current player has to do next: draw (or take the discard pile), or meld and discard.
     */
    public function getPhase(): TurnPhase
    {
        $this->assertPlaying();

        return $this->phase;
    }

    /**
     * Whether the game ends with the current turn, because the stock is exhausted.
     */
    public function isLastTurn(): bool
    {
        return $this->lastTurn;
    }

    public function getHand(Player $player): Hand
    {
        return $this->hands[$this->indexOf($player)] ?? throw new IllegalMoveException('Cards not dealt yet.');
    }

    public function getTable(Player|Team $who): Table
    {
        return $this->tables[$this->teamOf($who)->value] ?? throw new IllegalMoveException('Cards not dealt yet.');
    }

    public function getStockCount(): int
    {
        return $this->stock->count();
    }

    /**
     * The card on top of the stock, the next one to be drawn, or null when the stock is empty. It is face down:
     * only its back is meant to be shown.
     */
    public function getTopOfStock(): ?Card
    {
        return $this->stock->top();
    }

    /**
     * The discard pile, from the bottom to the top.
     *
     * @return list<Card>
     */
    public function getDiscards(): array
    {
        return $this->discards->getCards();
    }

    public function getTopDiscard(): ?Card
    {
        return $this->discards->top();
    }

    public function hasTakenPozzetto(Player|Team $who): bool
    {
        $team = $this->teamOf($who);

        return \array_key_exists($team->value, $this->pozzetti) && null === $this->pozzetti[$team->value];
    }

    /**
     * Draws the top card of the stock.
     *
     * @throws IllegalMoveException
     */
    public function draw(Player $player): Card
    {
        $index = $this->assertTurn($player, TurnPhase::Draw);
        if ($this->stock->count() <= $this->rules->unplayableStockCards) {
            throw new IllegalMoveException('The stock is exhausted.');
        }
        $card = $this->stock->draw();
        $this->hands[$index] = $this->hands[$index]->add($card);
        if ($this->stock->count() <= $this->rules->unplayableStockCards) {
            $this->lastTurn = true;
        }
        $this->phase = TurnPhase::Play;

        return $card;
    }

    /**
     * Takes the whole discard pile instead of drawing.
     *
     * @return list<Card> the cards taken, from the bottom to the top of the pile
     *
     * @throws IllegalMoveException
     */
    public function takeDiscardPile(Player $player): array
    {
        $index = $this->assertTurn($player, TurnPhase::Draw);
        if ($this->discards->isEmpty()) {
            throw new IllegalMoveException('The discard pile is empty.');
        }
        $hand = $this->hands[$index];
        if ($this->rules->mustMeldAfterTakingPile && !MeldFinder::canMeld([...\array_values($hand->getCards()), ...$this->discards->getCards()], $this->tables[self::teamOfIndex($index)->value], $this->rules)) {
            throw new IllegalMoveException('The discard pile can be taken only when at least one card can be melded.');
        }
        $cards = $this->discards->takeAll();
        $this->singleTaken = 1 === \count($cards) ? $cards[0] : null;
        foreach ($cards as $card) {
            $hand = $hand->add($card);
        }
        $this->hands[$index] = $hand;
        $this->phase = TurnPhase::Play;

        return $cards;
    }

    /**
     * Lays cards on the table by submitting the new layout of the whole team's table.
     *
     * The layout must contain every meld already on the table (each one possibly extended with cards from the
     * hand, its wildcard moved if needed) plus any number of new melds from the hand. It must add at least one
     * card. Can be called several times in the same turn.
     * A player who melds their last card takes the pozzetto and goes on playing; that is not allowed once the
     * pozzetto is gone, because closing requires a final discard.
     *
     * @param array<int|string, Meld> $melds
     *
     * @throws IllegalMoveException
     */
    public function meld(Player $player, array $melds): void
    {
        $index = $this->assertTurn($player, TurnPhase::Play);
        $team = self::teamOfIndex($index);
        $table = $this->tables[$team->value];
        $layout = new Table($melds);
        $before = new CardBag($table->getCards());
        $after = new CardBag($layout->getCards());
        $removed = $before->diff($after);
        if ([] !== $removed) {
            throw new IllegalMoveException(\sprintf('Cards cannot leave the table: %s.', \implode(',', $removed)));
        }
        $played = $after->diff($before);
        if ([] === $played) {
            throw new IllegalMoveException('At least one card from the hand must be melded.');
        }
        $hand = $this->hands[$index];
        $notInHand = (new CardBag(\array_map(static fn (string $rs): Card => Card::fromRankSuit($rs), $played)))->diff(new CardBag($hand->getCards()));
        if ([] !== $notInHand) {
            throw new IllegalMoveException(\sprintf('Cards not in hand: %s.', \implode(',', $notInHand)));
        }
        $this->assertMeldsGrow($table, $layout);
        $ranks = [];
        foreach ($layout->getSets() as $set) {
            $rank = $set->getRank();
            if (!$this->rules->allowsSetOf($rank)) {
                throw new IllegalMoveException(\sprintf('Sets of %s are not allowed.', $rank->name));
            }
            if (\in_array($rank, $ranks, true)) {
                throw new IllegalMoveException(\sprintf('A team cannot have two sets of %s.', $rank->name));
            }
            $ranks[] = $rank;
        }
        if ($this->hasTakenPozzetto($team) && \count($played) === \count($hand)) {
            throw new IllegalMoveException('Cannot close by melding all the cards: a final discard is required.');
        }
        foreach ($played as $rs) {
            $hand = $hand->play(Card::fromRankSuit($rs));
        }
        if (1 === \count($hand)) {
            try {
                $this->assertDiscardable($hand->getCards()[0], $hand, $layout, $team);
            } catch (IllegalMoveException $e) {
                throw new IllegalMoveException(\sprintf('This play would leave a card that cannot be discarded: %s', $e->getMessage()), previous: $e);
            }
        }
        $this->hands[$index] = $hand;
        $this->tables[$team->value] = $layout;
        if ($hand->isEmpty()) {
            $this->takePozzetto($index);
        }
    }

    /**
     * Ends the turn by discarding a card.
     *
     * Discarding the last card takes the pozzetto, or closes the game once the pozzetto is gone: in that case the
     * team must have a buraco on the table and the card cannot be a wildcard.
     *
     * @throws IllegalMoveException
     */
    public function discard(Player $player, Card $card): void
    {
        $index = $this->assertTurn($player, TurnPhase::Play);
        $team = self::teamOfIndex($index);
        $hand = $this->hands[$index];
        if (!$hand->has($card)) {
            throw new IllegalMoveException(\sprintf('Card %s is not in hand.', $card));
        }
        $closing = $this->assertDiscardable($card, $hand, $this->tables[$team->value], $team);
        $this->discards->add($card);
        $hand = $hand->play($card);
        $this->hands[$index] = $hand;
        if ($closing) {
            $this->end($team);

            return;
        }
        if ($hand->isEmpty()) {
            $this->takePozzetto($index);
        }
        if ($this->lastTurn) {
            $this->end(null);

            return;
        }
        $this->advance();
    }

    /**
     * The team that closed the game, or null if the game is not over or ended because the stock was exhausted.
     */
    public function getClosingTeam(): ?Team
    {
        return $this->closer;
    }

    /**
     * The team with the highest score, or null if the game is not over or the teams are tied.
     */
    public function getWinner(): ?Team
    {
        if (!$this->isOver()) {
            return null;
        }
        $first = $this->getScore(Team::First)->total;
        $second = $this->getScore(Team::Second)->total;

        if ($first === $second) {
            return null;
        }

        return $first > $second ? Team::First : Team::Second;
    }

    /**
     * Score of the team: final once the game is over, provisional before.
     */
    public function getScore(Player|Team $who): Score
    {
        $team = $this->teamOf($who);
        $table = $this->getTable($team);
        $buracos = 0;
        foreach ($table->getBuracos() as $meld) {
            $buraco = $meld->getBuraco();
            \assert(null !== $buraco);
            $buracos += $this->rules->getBuracoPoints($buraco);
        }
        $hands = 0;
        foreach ($this->hands as $index => $hand) {
            if (self::teamOfIndex($index) === $team) {
                $hands -= $hand->getPoints();
            }
        }

        return new Score(
            buracos: $buracos,
            closing: $this->closer === $team ? $this->rules->closingBonus : 0,
            table: $table->getPoints(),
            hands: $hands,
            pozzetto: $this->hasTakenPozzetto($team) ? 0 : -$this->rules->pozzettoPenalty,
        );
    }

    /**
     * @return bool whether discarding the card closes the game
     */
    private function assertDiscardable(Card $card, Hand $hand, Table $table, Team $team): bool
    {
        if (null !== $this->singleTaken && $card->isSameFace($this->singleTaken) && $hand->countFace($card) < 2) {
            throw new IllegalMoveException('The only card taken from the discard pile cannot be discarded back.');
        }
        if (1 !== \count($hand) || !$this->hasTakenPozzetto($team)) {
            return false;
        }
        if (CardValue::isWildcard($card)) {
            throw new IllegalMoveException('Cannot close by discarding a wildcard.');
        }
        if (!$table->hasBuraco($this->rules->cleanBuracoToClose)) {
            throw new IllegalMoveException(\sprintf('Cannot close without a %sburaco.', $this->rules->cleanBuracoToClose ? 'clean ' : ''));
        }

        return true;
    }

    /**
     * Every meld of the old table must be found, with all its cards, in exactly one meld of the new one.
     */
    private function assertMeldsGrow(Table $table, Table $layout): void
    {
        $old = $table->getMelds();
        \usort($old, static fn (Meld $a, Meld $b): int => \count($b) <=> \count($a));
        $remaining = $layout->getMelds();
        foreach ($old as $meld) {
            $index = \array_find_key($remaining, static fn (Meld $candidate): bool => $candidate->contains($meld));
            if (null === $index) {
                throw new IllegalMoveException(\sprintf('Meld %s cannot be split, merged or removed.', $meld));
            }
            unset($remaining[$index]);
        }
    }

    private function takePozzetto(int $index): void
    {
        $team = self::teamOfIndex($index);
        $this->hands[$index] = new Hand($this->pozzetti[$team->value] ?? throw new \LogicException('Pozzetto already taken.'));
        $this->pozzetti[$team->value] = null;
    }

    private function end(?Team $closer): void
    {
        $this->closer = $closer;
        $this->status = GameStatus::Ended;
    }

    private function advance(): void
    {
        $this->current = ($this->current + 1) % $this->players->count();
        $this->phase = TurnPhase::Draw;
        $this->singleTaken = null;
    }

    /** @return list<Card> */
    private function drawMany(int $count): array
    {
        $cards = [];
        for ($i = 0; $i < $count; ++$i) {
            $cards[] = $this->stock->draw();
        }

        return $cards;
    }

    private function assertPlaying(): void
    {
        if (GameStatus::Playing !== $this->status) {
            throw new IllegalMoveException(\sprintf('Game is not in progress (%s).', $this->status->name));
        }
    }

    /**
     * @return int index of the player
     */
    private function assertTurn(Player $player, TurnPhase $phase): int
    {
        $this->assertPlaying();
        $index = $this->indexOf($player);
        if ($index !== $this->current) {
            throw new NotYourTurnException(\sprintf('It is %s\'s turn, not %s\'s.', $this->getCurrentPlayer(), $player));
        }
        if ($phase !== $this->phase) {
            throw new IllegalMoveException(TurnPhase::Draw === $this->phase ? 'Draw a card or take the discard pile first.' : 'Already drawn: meld or discard.');
        }

        return $index;
    }

    private function teamOf(Player|Team $who): Team
    {
        return $who instanceof Team ? $who : $this->getTeam($who);
    }

    private static function teamOfIndex(int $index): Team
    {
        return Team::from($index % 2);
    }

    private function indexOf(Player $player): int
    {
        return $this->findPlayer($player) ?? throw new \InvalidArgumentException(\sprintf('Player %s is not in this game.', $player));
    }

    private function findPlayer(Player $player): ?int
    {
        foreach ($this->players as $index => $candidate) {
            if ($candidate->isEqual($player)) {
                return $index;
            }
        }

        return null;
    }
}
