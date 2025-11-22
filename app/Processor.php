<?php

declare(strict_types=1);

namespace App;

class Processor
{
    public function __construct()
    {
        session_name('mj');
        session_start();
        session_regenerate_id();
    }

    public function load(callable $start_new_game): Game
    {
        if (isset($_SESSION['game'])) {
            return unserialize($_SESSION['game']);
        };

        return call_user_func($start_new_game);
    }

    public function store(Game $game): void
    {
        $_SESSION['game'] = serialize($game);
    }

    public function reset(): void
    {
        unset($_SESSION['game']);
    }

    public function makePrompts(Game $game): array
    {
        $prompts = [];

        if ($game->state === Game::STATE_DISCARD) {
            $prompts[] = [
                'name' => $game->currentPlayer()->name,
                'content' => $game->promptDiscard(),
            ];
        } elseif ($game->state === Game::STATE_CALL) {
            foreach ([$game->nextPlayerIndex(), $game->acrossPlayerIndex(), $game->prevPlayerIndex()] as $i) {
                $prompts[] = [
                    'name' => $game->players[$i]->name,
                    'content' => $game->promptCall($i),
                ];
            }
        }

        return $prompts;
    }
}
