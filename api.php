<?php

use App\Action;
use App\Game;
use App\Player;
use App\Processor;

require('vendor/autoload.php');

$json = file_get_contents("php://input");

$payload = $json ? (json_decode($json, true) ?: []) : [];

$processor = new Processor();

if (!empty($payload['reset'])) {
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

$alert = null;

try {
    $action = new Action(isset($payload['action']) ? $payload['action'] : []);
    $game->play($action);
} catch (Throwable $e) {
    $alert = $e->getMessage();
}

$processor->store($game);

$prompts = $processor->makePrompts($game);

header('Content-Type: application/json');
echo json_encode([
    'game' => $game,
    'prompts' => $prompts,
    'alert' => $alert,
]);
