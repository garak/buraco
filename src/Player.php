<?php

namespace Garak\Buraco;

abstract class Player implements \Stringable
{
    public function __construct(protected string $name)
    {
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isPlaying(Game $game): bool
    {
        return $game->hasPlayer($this);
    }

    abstract public function isEqual(self $player): bool;
}
