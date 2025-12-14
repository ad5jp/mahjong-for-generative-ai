<?php

declare(strict_types=1);

use App\Game;
use App\Player;
use App\Processor\Processor;

require('vendor/autoload.php');

require('config.php');

$setting = include('setting.php');

$json = file_get_contents("php://input");

$payload = $json ? (json_decode($json, true) ?: []) : [];

/** @var Processor $processor */
$processor = new $setting['processor']();
// $processor->reset();

if (!empty($payload['mode'])) {
    if ($payload['mode'] === 'reset') {
        $processor->reset();
    }
}

$game = $processor->load(function () use ($setting) {
    return new Game([
        new Player($setting['players'][0]['name'], new $setting['players'][0]['agent']()),
        new Player($setting['players'][1]['name'], new $setting['players'][1]['agent']()),
        new Player($setting['players'][2]['name'], new $setting['players'][2]['agent']()),
        new Player($setting['players'][3]['name'], new $setting['players'][3]['agent']()),
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
