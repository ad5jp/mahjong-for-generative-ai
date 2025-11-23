<?php

declare(strict_types=1);

namespace App;

interface Agent
{
    public function decideDiscard(Game $game): Action;

    public function decideCall(Game $game, int $my_player_index): Action;
}
