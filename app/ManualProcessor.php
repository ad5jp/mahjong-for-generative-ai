<?php

declare(strict_types=1);

namespace App;

class ManualProcessor extends Processor
{
    public function proceed(Game $game, array $payload): void
    {
        $action = new Action(isset($payload['action']) ? $payload['action'] : []);
        $game->play($action);
    }
}
