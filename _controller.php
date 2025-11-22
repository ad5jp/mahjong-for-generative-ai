<?php

use App\Action;
use App\Game;
use App\Player;
use App\Processor;

require('vendor/autoload.php');

$processor = new Processor();

if (!empty($_POST['reset'])) {
    $processor->reset();
}

$game = $processor->load(function () {
    return new Game([
        new Player('Chat GPT'),
        new Player('Gemini'),
        new Player('Copilot'),
        new Player('Perplexity'),
    ]);
});

$action = new Action(isset($_POST['action']) ? json_decode($_POST['action'], true) : []);

$listen = $game->play($action);

$processor->store($game);

require('view.php');