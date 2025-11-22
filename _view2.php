<?php
use App\Listen;
use App\Game;

/**
 * @var Game $game
 * @var Listen $listen
 */
?><!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mahjong</title>
    <link rel="stylesheet" href="assets/pai.css">
    <style>
        * {box-sizing: border-box;}
        body {background: #8D8;}
        .players {display: flex; flex-wrap: wrap;}
        .player {width: 50%; padding: 10px;}
        .player-0, .player-2 {margin: 0 25%;}
        .player-active {background: #8F8;}
        .player h2 {margin: 0 0 10px;}
        label {display: block; width: 200px; padding: 0 5px; background: #FFF; border-radius: 5px 5px 0 0;}
        textarea {display: block; width: 500px; height: 100px; margin-bottom: 20px;}
        button {padding: 10px 40px;}
    </style>
</head>
<body>
    <h1><?php echo $game->showRound() ?></h1>

    <div class="pais">
        <span class="pai back"></span>
        <span class="pai back"></span>
        <?php foreach ($game->dora as $pai): ?>
        <?php echo $pai->html(); ?>
        <?php endforeach; ?>
        <?php for ($i = 0; $i < 5 - count($game->dora); $i++): ?>
        <span class="pai back"></span>
        <?php endfor; ?>
    </div>

    <div class="players">
    <?php foreach ([0,1,3,2] as $i): $player = $game->players[$i]; ?>
        <div class="player player-<?php echo $i; ?> <?php echo $i === $game->current_player ? 'player-active' : ''; ?>">
            <h2>
                <?php echo $player->name . ($i === $game->dealer_player ? ' (親)' : '') ?>
                <small><?php echo number_format($player->score); ?>点</small>
                <?php if ($player->riichi): ?>[リーチ]<?php endif; ?>
            </h2>
            <div class="pais">
                <?php foreach ($player->river as $river_pai): ?>
                    <?php echo $river_pai->html(); ?>
                <?php endforeach; ?>
            </div>
            <div class="pais">
                <div class="hand">
                    <?php foreach ($player->hand as $pai): ?>
                        <?php echo $pai->html(); ?>
                    <?php endforeach; ?>
                    <?php if ($player->drawing): ?>
                        <div class="tsumo">
                            <?php echo $player->drawing->html(); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php foreach ($player->open as $open_pais): ?>
                    <?php echo $open_pais->html(); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <form action="" method="post">
        <?php if ($game->state === Game::STATE_READY): ?>
            <textarea name="action"><?php echo json_encode(['command' => 'start']); ?></textarea>
        <?php elseif ($game->state === Game::STATE_DISCARD): ?>
            <label><?php echo $game->currentPlayer()->name; ?> プロンプト</label>
            <textarea readonly><?php echo $game->promptDiscard(); ?></textarea>
            <label>レスポンス</label>
            <textarea name="action"><?php echo json_encode(['command' => 'discard', 'target' => '', 'riichi' => false]); ?></textarea>
        <?php elseif ($game->state === Game::STATE_CALL): ?>
            <?php foreach ($game->players as $i => $player): ?>
            <?php if ($i === $game->current_player) continue; ?>
            <label><?php echo $player->name; ?> プロンプト</label>
            <textarea readonly><?php echo $game->promptCall($i); ?></textarea>
            <?php endforeach; ?>
            <label>レスポンス</label>
            <textarea name="action"><?php echo json_encode(['command' => 'skip']); ?></textarea>
        <?php elseif ($game->state === Game::STATE_END): ?>
            <textarea name="action"><?php echo json_encode(['command' => 'calculate', 'points' => [0, 0, 0, 0]]); ?></textarea>
        <?php endif; ?>

        <button type="submit">実行</button>
    </form>
    <div style="text-align:right">
        <form action="" method="post"><input type="submit" name="reset" value="リセット"></form>
    </div>
</body>
</html>