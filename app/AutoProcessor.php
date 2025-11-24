<?php

declare(strict_types=1);

namespace App;

class AutoProcessor extends Processor
{
    public function proceed(Game $game, array $payload): void
    {
        if ($game->state === Game::STATE_READY) {
            $action = new Action(['command' => Action::START]);

        } elseif ($game->state === Game::STATE_DISCARD) {
            $action = $game->currentPlayer()->agent->decideDiscard($game);

        } elseif ($game->state === Game::STATE_CALL) {
            $actions = [];
            $player_indexes = [
                $game->nextPlayerIndex(),
                $game->acrossPlayerIndex(),
                $game->prevPlayerIndex(),
            ];

            foreach ($player_indexes as $player_index) {
                if ($game->canCall($player_index)) {
                    $actions[] = $game->players[$player_index]->agent->decideCall($game, $player_index);
                }
            }


            ($action = array_find($actions, fn (Action $action) => $action->command === Action::RON))
                || ($action = array_find($actions, fn (Action $action) => $action->command === Action::PON))
                || ($action = array_find($actions, fn (Action $action) => $action->command === Action::KAN))
                || ($action = array_find($actions, fn (Action $action) => $action->command === Action::CHII))
                || ($action = new Action(['command' => Action::SKIP]));

        } elseif ($game->state === Game::STATE_END) {
            // TODO
            $action = new Action(['command' => Action::CALCULATE, 'points' => [0, 0, 0, 0]]);
        }

        $game->play($action);
    }
}
