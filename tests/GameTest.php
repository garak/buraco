<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\Exception\IllegalMoveException;
use Garak\Buraco\Exception\NotYourTurnException;
use Garak\Buraco\Game;
use Garak\Buraco\GameStatus;
use Garak\Buraco\Meld;
use Garak\Buraco\Rules;
use Garak\Buraco\Team;
use Garak\Buraco\TurnPhase;
use Garak\Card\Card;
use Garak\Card\Rank;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TODO when PHP 8.3 support is dropped (PHPUnit 13 only): the try/catch blocks in dealNeedsTwoOrFourPlayers()
 * and dealNeedsEnoughCards() can become expectException() + expectExceptionMessageIsOrContains(), which is
 * missing in PHPUnit 12. The try/catch blocks inside the game scenarios stay: the test goes on after the exception.
 */
final class GameTest extends TestCase
{
    #[Test]
    public function joinPlayers(): void
    {
        $game = new Game();
        $alice = new StubPlayer('Alice');
        $bob = new StubPlayer('Bob');
        self::assertFalse($game->hasPlayer($alice));
        $game->join($alice);
        self::assertTrue($game->hasPlayer($alice));
        self::assertTrue($alice->isPlaying($game));
        self::assertFalse($bob->isPlaying($game));
        self::assertCount(1, $game->getPlayers());
        self::assertSame(GameStatus::Waiting, $game->getStatus());
        self::assertSame(Team::First, $game->getTeam($alice));
        self::assertFalse($game->hasTakenPozzetto($alice));
        self::assertSame(11, $game->getRules()->handSize);
        self::assertNull($game->getWinner());
        self::assertNull($game->getClosingTeam());
        self::assertFalse($game->isLastTurn());
    }

    #[Test]
    public function cannotJoinTwice(): void
    {
        $game = new Game();
        $game->join(new StubPlayer('Alice'));
        $this->expectException(\InvalidArgumentException::class);
        $game->join(new StubPlayer('Alice'));
    }

    #[Test]
    public function cannotJoinAfterDeal(): void
    {
        $game = self::game();
        $this->expectException(IllegalMoveException::class);
        $game->join(new StubPlayer('Carol'));
    }

    #[Test]
    public function dealNeedsTwoOrFourPlayers(): void
    {
        $game = new Game();
        $game->join(new StubPlayer('Alice'));
        $game->join(new StubPlayer('Bob'));
        $game->join(new StubPlayer('Carol'));
        try {
            $game->deal();
            self::fail('Three players should not be allowed.');
        } catch (IllegalMoveException $e) {
            self::assertSame('Two or four players are needed.', $e->getMessage());
        }
    }

    #[Test]
    public function dealNeedsEnoughCards(): void
    {
        $game = new Game(new Rules(handSize: 2, pozzettoSize: 1));
        $game->join(new StubPlayer('Alice'));
        $game->join(new StubPlayer('Bob'));
        try {
            $game->deal(self::cards('Ah,2h,3h,4h,5h,6h,7h,8h,9h'));
            self::fail('Nine cards should not be enough.');
        } catch (IllegalMoveException $e) {
            self::assertSame('At least 10 cards are needed to deal to 2 players, 9 given.', $e->getMessage());
        }
    }

    #[Test]
    public function cannotDealTwice(): void
    {
        $game = self::game();
        $this->expectException(IllegalMoveException::class);
        $game->deal();
    }

    #[Test]
    public function dealWithShuffledDeckToFourPlayers(): void
    {
        $game = new Game();
        $alice = new StubPlayer('Alice');
        $bob = new StubPlayer('Bob');
        $carol = new StubPlayer('Carol');
        $dave = new StubPlayer('Dave');
        foreach ([$alice, $bob, $carol, $dave] as $player) {
            $game->join($player);
        }
        $game->deal();
        self::assertSame(GameStatus::Playing, $game->getStatus());
        self::assertSame(TurnPhase::Draw, $game->getPhase());
        foreach ([$alice, $bob, $carol, $dave] as $player) {
            self::assertCount(11, $game->getHand($player));
        }
        self::assertSame(108 - 44 - 22 - 1, $game->getStockCount());
        self::assertCount(1, $game->getDiscards());
        self::assertNotNull($game->getTopDiscard());
        self::assertTrue($game->getTable($alice)->isEmpty());
        self::assertTrue($game->getTable(Team::Second)->isEmpty());
        self::assertSame($alice, $game->getCurrentPlayer());
        self::assertSame([$alice, $carol], $game->getTeamPlayers(Team::First));
        self::assertSame([$bob, $dave], $game->getTeamPlayers(Team::Second));
        self::assertSame(Team::Second, $game->getTeam($dave));
        self::assertNull($game->getWinner());
        self::assertFalse($game->isOver());
    }

    #[Test]
    public function noCurrentPlayerBeforeDeal(): void
    {
        $game = new Game();
        $this->expectException(IllegalMoveException::class);
        $game->getCurrentPlayer();
    }

    #[Test]
    public function noHandBeforeDeal(): void
    {
        $game = new Game();
        $alice = new StubPlayer('Alice');
        $game->join($alice);
        $this->expectException(IllegalMoveException::class);
        $game->getHand($alice);
    }

    #[Test]
    public function noTableBeforeDeal(): void
    {
        $game = new Game();
        $this->expectException(IllegalMoveException::class);
        $game->getTable(Team::First);
    }

    #[Test]
    public function unknownPlayer(): void
    {
        $game = self::game();
        $this->expectException(\InvalidArgumentException::class);
        $game->getHand(new StubPlayer('Carol'));
    }

    #[Test]
    public function fullGameWithPozzettoOnDiscardAndClosing(): void
    {
        // Alice: 3h,4h,5h,6h - Bob: Kc,Kd,Ks,Js - pozzetti: 7h,8h,9h / Ac,2c,3c - first discard: Th - stock: Qs,2s,...
        $rules = new Rules(decks: 1, jokers: 1, handSize: 4, pozzettoSize: 3);
        $game = new Game($rules);
        $alice = new StubPlayer('Alice');
        $bob = new StubPlayer('Bob');
        $game->join($alice);
        $game->join($bob);
        $game->deal(self::deck('3h,4h,5h,6h,Kc,Kd,Ks,Js,7h,8h,9h,Ac,2c,3c,Th,Qs,2s', $rules));
        self::assertSame('3h,4h,5h,6h', (string) $game->getHand($alice));
        self::assertSame('Kc,Kd,Ks,Js', (string) $game->getHand($bob));
        self::assertSame('Th', (string) $game->getTopDiscard());
        self::assertSame(53 - 8 - 6 - 1, $game->getStockCount());

        // not Bob's turn
        try {
            $game->draw($bob);
            self::fail('Bob should not be allowed to draw.');
        } catch (NotYourTurnException) {
        }
        // Alice must draw first
        try {
            $game->meld($alice, self::melds('3h,4h,5h,6h'));
            self::fail('Alice should draw first.');
        } catch (IllegalMoveException $e) {
            self::assertSame('Draw a card or take the discard pile first.', $e->getMessage());
        }

        // Alice draws, melds all but one card and takes the pozzetto with the discard
        self::assertSame('Qs', (string) $game->draw($alice));
        self::assertSame(TurnPhase::Play, $game->getPhase());
        try {
            $game->draw($alice);
            self::fail('Alice has already drawn.');
        } catch (IllegalMoveException $e) {
            self::assertSame('Already drawn: meld or discard.', $e->getMessage());
        }
        $game->meld($alice, self::melds('3h,4h,5h,6h'));
        self::assertSame('Qs', (string) $game->getHand($alice));
        self::assertSame('3h,4h,5h,6h', (string) $game->getTable($alice));
        self::assertSame($alice, $game->getCurrentPlayer());
        $game->discard($alice, Card::fromRankSuit('Qs'));
        self::assertTrue($game->hasTakenPozzetto($alice));
        self::assertSame('7h,8h,9h', (string) $game->getHand($alice));
        self::assertSame('Th,Qs', \implode(',', $game->getDiscards()));
        self::assertSame($bob, $game->getCurrentPlayer());
        // provisional scores: 20 on the table, 25 in hand
        self::assertSame(-5, $game->getScore($alice)->total);
        self::assertSame(-100 - 40, $game->getScore(Team::Second)->total);

        // Bob draws a wildcard, melds the kings and discards the wildcard
        self::assertSame('2s', (string) $game->draw($bob));
        $game->meld($bob, self::melds('Kc,Kd,Ks'));
        $game->discard($bob, Card::fromRankSuit('2s'));
        self::assertSame('Js', (string) $game->getHand($bob));
        self::assertFalse($game->hasTakenPozzetto($bob));

        // Alice takes the pile
        self::assertSame('Th,Qs,2s', \implode(',', $game->takeDiscardPile($alice)));
        self::assertSame([], $game->getDiscards());
        self::assertSame('7h,8h,9h,Th,Qs,2s', (string) $game->getHand($alice));
        $illegal = [
            '3h,4h,5h,6h' => 'At least one card from the hand must be melded.',
            '' => 'Cards cannot leave the table: 3h,4h,5h,6h.',
            '3h,4h,5h,6h;Kc,Kd,Ks' => 'Cards not in hand: Kc,Kd,Ks.',
            '3h,4h,5h;6h,7h,8h,9h' => 'Meld 3h,4h,5h,6h cannot be split, merged or removed.',
            '3h,4h,5h,6h,7h,8h,9h,Th,Qs,2s' => 'This play would leave a card that cannot be discarded',
        ];
        foreach ($illegal as $layout => $message) {
            try {
                $game->meld($alice, self::melds($layout));
                self::fail(\sprintf('Layout "%s" should be illegal.', $layout));
            } catch (IllegalMoveException $e) {
                self::assertStringStartsWith($message, $e->getMessage());
            } catch (\InvalidArgumentException) {
                // a layout that cannot even be built
            }
        }
        // a semi-clean buraco, then Alice closes
        $game->meld($alice, self::melds('3h,4h,5h,6h,7h,8h,9h,Th,2s'));
        self::assertSame('Qs', (string) $game->getHand($alice));
        $game->discard($alice, Card::fromRankSuit('Qs'));
        self::assertTrue($game->isOver());
        self::assertSame(GameStatus::Ended, $game->getStatus());
        self::assertSame(Team::First, $game->getClosingTeam());
        self::assertSame(Team::First, $game->getWinner());
        $score = $game->getScore($alice);
        self::assertSame(150, $score->buracos);
        self::assertSame(100, $score->closing);
        self::assertSame(75, $score->table);
        self::assertSame(0, $score->hands);
        self::assertSame(0, $score->pozzetto);
        self::assertSame(325, $score->total);
        $score = $game->getScore($bob);
        self::assertSame(0, $score->buracos);
        self::assertSame(0, $score->closing);
        self::assertSame(30, $score->table);
        self::assertSame(-10, $score->hands);
        self::assertSame(-100, $score->pozzetto);
        self::assertSame(-80, $score->total);

        $this->expectException(IllegalMoveException::class);
        $game->draw($bob);
    }

    #[Test]
    public function pozzettoOnTheFlyAndStockExhaustion(): void
    {
        // Alice: 4h,5h,6h - Bob: 9c,9d,9s - pozzetti: 7h,8h,wb / Qd,Qs,Th - first discard: Jd - stock: 3h,Kc,5c,6c,7c
        $rules = new Rules(decks: 1, jokers: 1, handSize: 3, pozzettoSize: 3);
        $game = new Game($rules);
        $alice = new StubPlayer('Alice');
        $bob = new StubPlayer('Bob');
        $game->join($alice);
        $game->join($bob);
        $game->deal(self::cards('4h,5h,6h,9c,9d,9s,7h,8h,wb,Qd,Qs,Th,Jd,3h,Kc,5c,6c,7c'));
        self::assertSame(5, $game->getStockCount());

        // Alice melds every card and goes on with the pozzetto
        self::assertSame('3h', (string) $game->draw($alice));
        $game->meld($alice, self::melds('3h,4h,5h,6h'));
        self::assertTrue($game->hasTakenPozzetto($alice));
        self::assertSame('7h,8h,wb', (string) $game->getHand($alice));
        self::assertSame($alice, $game->getCurrentPlayer());
        self::assertSame(TurnPhase::Play, $game->getPhase());
        try {
            $game->meld($alice, self::melds('3h,4h,5h,6h,7h,8h,wb'));
            self::fail('Closing without a discard should be illegal.');
        } catch (IllegalMoveException $e) {
            self::assertSame('Cannot close by melding all the cards: a final discard is required.', $e->getMessage());
        }
        try {
            $game->meld($alice, self::melds('3h,4h,5h,6h,7h,8h'));
            self::fail('Keeping only a wildcard should be illegal.');
        } catch (IllegalMoveException $e) {
            self::assertSame('This play would leave a card that cannot be discarded: Cannot close by discarding a wildcard.', $e->getMessage());
        }
        $game->meld($alice, self::melds('3h,4h,5h,6h,7h'));
        $game->discard($alice, Card::fromRankSuit('wb'));
        self::assertSame('8h', (string) $game->getHand($alice));
        self::assertSame('Jd,wb', \implode(',', $game->getDiscards()));

        // Bob takes the pozzetto with the discard
        self::assertSame('Kc', (string) $game->draw($bob));
        $game->meld($bob, self::melds('9c,9d,9s'));
        $game->discard($bob, Card::fromRankSuit('Kc'));
        self::assertTrue($game->hasTakenPozzetto(Team::Second));
        self::assertSame('Qd,Qs,Th', (string) $game->getHand($bob));
        self::assertFalse($game->isLastTurn());

        // Alice draws the third last card: the game ends with her turn
        self::assertSame('5c', (string) $game->draw($alice));
        self::assertSame(2, $game->getStockCount());
        self::assertTrue($game->isLastTurn());
        try {
            $game->meld($alice, self::melds('3h,4h,5h,6h,7h,8h'));
            self::fail('Keeping a single card without a buraco should be illegal.');
        } catch (IllegalMoveException $e) {
            self::assertSame('This play would leave a card that cannot be discarded: Cannot close without a buraco.', $e->getMessage());
        }
        try {
            $game->discard($alice, Card::fromRankSuit('Kc'));
            self::fail('Kc is not in hand.');
        } catch (IllegalMoveException $e) {
            self::assertSame('Card Kc is not in hand.', $e->getMessage());
        }
        $game->discard($alice, Card::fromRankSuit('5c'));
        self::assertTrue($game->isOver());
        self::assertNull($game->getClosingTeam());
        self::assertSame(Team::First, $game->getWinner());
        $score = $game->getScore(Team::First);
        self::assertSame(0, $score->closing);
        self::assertSame(25, $score->table);
        self::assertSame(-10, $score->hands);
        self::assertSame(15, $score->total);
        $score = $game->getScore(Team::Second);
        self::assertSame(30, $score->table);
        self::assertSame(-30, $score->hands);
        self::assertSame(0, $score->pozzetto);
        self::assertSame(0, $score->total);
    }

    #[Test]
    public function closingWithADirtyBuraco(): void
    {
        $game = self::gameAboutToClose(new Rules(decks: 1, jokers: 1, handSize: 4, pozzettoSize: 3));
        $alice = new StubPlayer('Alice');
        $bob = new StubPlayer('Bob');
        $game->meld($alice, self::melds('3h,4h,5h,6h,7h,8h,wb'));
        $game->discard($alice, Card::fromRankSuit('Th'));
        self::assertTrue($game->isOver());
        self::assertSame(Team::First, $game->getClosingTeam());
        self::assertSame(100, $game->getScore($alice)->buracos);
        self::assertSame(100, $game->getScore($alice)->closing);
        self::assertSame(-100, $game->getScore($bob)->pozzetto);
    }

    #[Test]
    public function internationalRulesNeedACleanBuracoToClose(): void
    {
        $game = self::gameAboutToClose(new Rules(decks: 1, jokers: 1, handSize: 4, pozzettoSize: 3, cleanBuracoToClose: true));
        $alice = new StubPlayer('Alice');
        try {
            $game->meld($alice, self::melds('3h,4h,5h,6h,7h,8h,wb'));
            self::fail('A dirty buraco is not enough to close.');
        } catch (IllegalMoveException $e) {
            self::assertSame('This play would leave a card that cannot be discarded: Cannot close without a clean buraco.', $e->getMessage());
        }
        $game->meld($alice, self::melds('3h,4h,5h,6h,7h,8h'));
        self::assertSame('wb,Th', (string) $game->getHand($alice));
        // keeping a wildcard only is fine: a card will be drawn next turn
        $game->discard($alice, Card::fromRankSuit('Th'));
        self::assertSame('wb', (string) $game->getHand($alice));
        self::assertFalse($game->isOver());
    }

    #[Test]
    public function internationalRulesRestrictSetsAndDiscardPile(): void
    {
        // Alice: Kc,Kd,Ks,3s - Bob: 3c,3d,4s,5s - pozzetti: 5h,6h,7h / Ah,Ad,As - first discard: 9d - stock: Ac,8s,...
        $rules = new Rules(decks: 1, jokers: 1, handSize: 4, pozzettoSize: 3, setRanks: [Rank::Ace, Rank::Three], mustMeldAfterTakingPile: true);
        $game = new Game($rules);
        $alice = new StubPlayer('Alice');
        $bob = new StubPlayer('Bob');
        $game->join($alice);
        $game->join($bob);
        $game->deal(self::deck('Kc,Kd,Ks,3s,3c,3d,4s,5s,5h,6h,7h,Ah,Ad,As,9d,Ac,8s', $rules));
        try {
            $game->takeDiscardPile($alice);
            self::fail('Alice cannot meld anything with the pile.');
        } catch (IllegalMoveException $e) {
            self::assertSame('The discard pile can be taken only when at least one card can be melded.', $e->getMessage());
        }
        self::assertSame('Ac', (string) $game->draw($alice));
        try {
            $game->meld($alice, self::melds('Kc,Kd,Ks'));
            self::fail('Sets of kings are not allowed.');
        } catch (IllegalMoveException $e) {
            self::assertSame('Sets of King are not allowed.', $e->getMessage());
        }
        $game->discard($alice, Card::fromRankSuit('3s'));

        // Bob can take the pile: the three of spades completes a set of threes
        self::assertSame('9d,3s', \implode(',', $game->takeDiscardPile($bob)));
        $game->meld($bob, self::melds('3c,3d,3s'));
        $game->discard($bob, Card::fromRankSuit('9d'));
        self::assertSame('4s,5s', (string) $game->getHand($bob));
    }

    #[Test]
    public function partnersShareTableAndPozzetto(): void
    {
        // Alice: 3h,4h,5h - Bob: 9c,9d,Kd - Carol: 6h,7h,8h - Dave: Tc,Jc,Qd
        // pozzetti: 9h,Th,Jh / 2c,3c,4c - first discard: Ks - stock: 2d,5d,6d,7d,8d,...
        $rules = new Rules(decks: 1, jokers: 0, handSize: 3, pozzettoSize: 3);
        $game = new Game($rules);
        $alice = new StubPlayer('Alice');
        $bob = new StubPlayer('Bob');
        $carol = new StubPlayer('Carol');
        $dave = new StubPlayer('Dave');
        foreach ([$alice, $bob, $carol, $dave] as $player) {
            $game->join($player);
        }
        $game->deal(self::deck('3h,4h,5h,9c,9d,Kd,6h,7h,8h,Tc,Jc,Qd,9h,Th,Jh,2c,3c,4c,Ks,2d,5d,6d,7d,8d', $rules));

        // Alice takes the team's pozzetto
        $game->draw($alice);
        $game->meld($alice, self::melds('3h,4h,5h'));
        $game->discard($alice, Card::fromRankSuit('2d'));
        self::assertTrue($game->hasTakenPozzetto($carol));
        self::assertSame('9h,Th,Jh', (string) $game->getHand($alice));
        self::assertSame($bob, $game->getCurrentPlayer());

        $game->draw($bob);
        $game->discard($bob, Card::fromRankSuit('5d'));

        // Carol extends the shared run, but cannot keep a single card without a buraco
        $game->draw($carol);
        self::assertSame('3h,4h,5h', (string) $game->getTable($carol));
        try {
            $game->meld($carol, self::melds('3h,4h,5h,6h,7h,8h'));
            self::fail('Carol would be left with one card and no buraco.');
        } catch (IllegalMoveException $e) {
            self::assertStringStartsWith('This play would leave a card that cannot be discarded', $e->getMessage());
        }
        $game->meld($carol, self::melds('3h,4h,5h,6h,7h'));
        $game->discard($carol, Card::fromRankSuit('8h'));
        self::assertSame('6d', (string) $game->getHand($carol));

        $game->draw($dave);
        $game->discard($dave, Card::fromRankSuit('7d'));
        self::assertSame('Ks,2d,5d,8h,7d', \implode(',', $game->getDiscards()));

        // Alice takes the pile and closes for the team with a clean buraco
        $game->takeDiscardPile($alice);
        $game->meld($alice, self::melds('3h,4h,5h,6h,7h,8h,9h,Th,Jh;5d,2d,7d'));
        self::assertSame('Ks', (string) $game->getHand($alice));
        $game->discard($alice, Card::fromRankSuit('Ks'));
        self::assertTrue($game->isOver());
        self::assertSame(Team::First, $game->getClosingTeam());
        $score = $game->getScore(Team::First);
        self::assertSame(200, $score->buracos);
        self::assertSame(100, $score->closing);
        self::assertSame(95, $score->table);
        self::assertSame(-5, $score->hands);
        self::assertSame(0, $score->pozzetto);
        self::assertSame(390, $score->total);
        $score = $game->getScore($dave);
        self::assertSame(0, $score->table);
        self::assertSame(-60, $score->hands);
        self::assertSame(-100, $score->pozzetto);
        self::assertSame(-160, $score->total);
        self::assertSame(Team::First, $game->getWinner());
    }

    #[Test]
    public function twoDecksAndSetsOfTheSameRank(): void
    {
        // Alice: Khr,Kdr,Ksr,Khb,Kdb,Ksb - Bob: 5cr,6cr,7cr,Kcb,8cr,9cr - pozzetti: Ahr / Ahb - first discard: Qsr - stock: 9dr,9db,...
        $rules = new Rules(decks: 2, jokers: 0, handSize: 6, pozzettoSize: 1);
        $game = new Game($rules);
        $alice = new StubPlayer('Alice');
        $bob = new StubPlayer('Bob');
        $game->join($alice);
        $game->join($bob);
        $game->deal(self::deck('Khr,Kdr,Ksr,Khb,Kdb,Ksb,5cr,6cr,7cr,Kcb,8cr,9cr,Ahr,Ahb,Qsr,9dr,9db', $rules));

        // the only card taken from the pile cannot be discarded back
        self::assertSame('Qsr', \implode(',', \array_map(static fn (Card $card): string => $card->toString(true), $game->takeDiscardPile($alice))));
        try {
            $game->discard($alice, Card::fromRankSuit('Qsr'));
            self::fail('Discarding back the only card taken should be illegal.');
        } catch (IllegalMoveException $e) {
            self::assertSame('The only card taken from the discard pile cannot be discarded back.', $e->getMessage());
        }
        try {
            $game->meld($alice, self::melds('Khr,Kdr,Ksr,Khb,Kdb,Ksb'));
            self::fail('Alice would be left with the card she cannot discard.');
        } catch (IllegalMoveException $e) {
            self::assertSame('This play would leave a card that cannot be discarded: The only card taken from the discard pile cannot be discarded back.', $e->getMessage());
        }
        try {
            $game->meld($alice, self::melds('Khr,Kdr,Ksr,Kcb'));
            self::fail('Bob holds the blue king of clubs.');
        } catch (IllegalMoveException $e) {
            self::assertSame('Cards not in hand: Kcb.', $e->getMessage());
        }
        $game->meld($alice, self::melds('Khr,Kdr,Ksr'));
        try {
            $game->meld($alice, self::melds('Khr,Kdr,Ksr;Khb,Kdb,Ksb'));
            self::fail('Two sets of kings should be illegal.');
        } catch (IllegalMoveException $e) {
            self::assertSame('A team cannot have two sets of King.', $e->getMessage());
        }
        // melding twice in the same turn
        $game->meld($alice, self::melds('Khr,Kdr,Ksr,Khb,Kdb'));
        self::assertSame('Ksb,Qsr', $game->getHand($alice)->toString(true));
        $game->discard($alice, Card::fromRankSuit('Ksb'));

        $game->draw($bob);
        $game->meld($bob, self::melds('5cr,6cr,7cr'));
        $game->discard($bob, Card::fromRankSuit('9dr'));

        // once the pile has more cards, any of them can be discarded
        self::assertSame('Ksb,9dr', \implode(',', \array_map(static fn (Card $card): string => $card->toString(true), $game->takeDiscardPile($alice))));
        $game->meld($alice, self::melds('Khr,Kdr,Ksr,Khb,Kdb,Ksb'));
        $game->discard($alice, Card::fromRankSuit('9dr'));
        self::assertSame('Khr,Kdr,Ksr,Khb,Kdb,Ksb', $game->getTable($alice)->toString(true));
        self::assertSame('Qsr', $game->getHand($alice)->toString(true));
    }

    /**
     * Alice: 3h,4h,5h,6h - Bob: 9c,9d,9s,Jc - pozzetti: 7h,8h,wb / Ac,3c,4c - first discard: Ad - stock: 2c,Qd,Th,...
     * Alice has taken the pozzetto and holds 7h,8h,wb,Th, with 3h,4h,5h,6h on the table.
     */
    private static function gameAboutToClose(Rules $rules): Game
    {
        $game = new Game($rules);
        $alice = new StubPlayer('Alice');
        $bob = new StubPlayer('Bob');
        $game->join($alice);
        $game->join($bob);
        $game->deal(self::deck('3h,4h,5h,6h,9c,9d,9s,Jc,7h,8h,wb,Ac,3c,4c,Ad,2c,Qd,Th', $rules));
        $game->draw($alice);
        $game->meld($alice, self::melds('3h,4h,5h,6h'));
        $game->discard($alice, Card::fromRankSuit('2c'));
        self::assertTrue($game->hasTakenPozzetto($alice));
        $game->draw($bob);
        $game->discard($bob, Card::fromRankSuit('Qd'));
        $game->draw($alice);
        self::assertSame('7h,8h,wb,Th', (string) $game->getHand($alice));

        return $game;
    }

    private static function game(): Game
    {
        $game = new Game();
        $game->join(new StubPlayer('Alice'));
        $game->join(new StubPlayer('Bob'));
        $game->deal();

        return $game;
    }

    /** @return list<Card> */
    private static function cards(string $cards): array
    {
        return \array_map(static fn (string $rs): Card => Card::fromRankSuit($rs), \explode(',', $cards));
    }

    /**
     * A full deck starting with the given cards, followed by all the others.
     *
     * @return list<Card>
     */
    private static function deck(string $top, Rules $rules): array
    {
        $cards = self::cards($top);
        $keys = \array_map(static fn (Card $card): string => $card->toString(true), $cards);
        foreach ($rules->createDeck() as $card) {
            if (!\in_array($card->toString(true), $keys, true)) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /** @return list<Meld> */
    private static function melds(string $layout): array
    {
        return '' === $layout ? [] : \array_map(static fn (string $meld): Meld => Meld::createFromString($meld), \explode(';', $layout));
    }
}
