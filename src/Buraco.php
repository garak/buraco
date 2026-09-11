<?php

namespace Garak\Buraco;

/**
 * The kinds of buraco (a meld of seven or more cards), from the most to the least valuable.
 * Which kinds are rewarded, and how much, is up to the Rules.
 */
enum Buraco
{
    /** A run of all the thirteen cards of a suit, without wildcards */
    case Royal;
    /** A run spanning all the thirteen values of a suit, with a wildcard */
    case DirtyRoyal;
    /** A set of all the eight cards of a rank, without wildcards */
    case Super;
    /** A set of all the eight cards of a rank plus a wildcard */
    case DirtySuper;
    /** No wildcards (a natural two is not a wildcard) */
    case Clean;
    /** A wildcard at either end of a run of at least seven natural cards, or in a set of at least seven natural cards */
    case SemiClean;
    /** A wildcard anywhere else */
    case Dirty;

    public function isClean(): bool
    {
        return match ($this) {
            self::Royal, self::Super, self::Clean => true,
            default => false,
        };
    }
}
