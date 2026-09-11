<?php

namespace Garak\Buraco\Test;

use Garak\Buraco\Player;

final class StubPlayer extends Player
{
    public function isEqual(Player $player): bool
    {
        return $this->name === $player->getName();
    }
}
