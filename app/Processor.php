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
}
