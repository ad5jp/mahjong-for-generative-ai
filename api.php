<?php

use App\AutoProcessor;
use App\Game;
use App\LogicAgent;
use App\ManualProcessor;
use App\Player;

require('vendor/autoload.php');

$json = file_get_contents("php://input");

$payload = $json ? (json_decode($json, true) ?: []) : [];

// $processor = new ManualProcessor();
$processor = new AutoProcessor();
$processor->reset();
if (!empty($payload['reset'])) {
    $processor->reset();
}

$game = $processor->load(function () {
    return new Game([
        new Player('Chat GPT', new LogicAgent()),
        new Player('Gemini', new LogicAgent()),
        new Player('Copilot', new LogicAgent()),
        new Player('Perplexity', new LogicAgent()),
    ]);
});

$alert = null;

try {
    $processor->proceed($game, $payload);
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
