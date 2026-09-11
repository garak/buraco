<?php

namespace Garak\Buraco;

/**
 * A turn has two phases: first the player draws a card from the stock or takes the whole discard pile,
 * then they meld as much as they like and end the turn by discarding one card.
 */
enum TurnPhase
{
    case Draw;
    case Play;
}
