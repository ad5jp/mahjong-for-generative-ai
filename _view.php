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
        body {margin: 0;}
        .table {position: relative; width: 100vw; height: 60vw; background: #080;}
        .board {position: absolute; left: 20vw; top: 20vw; width: 60vw; height: 20vw; display: flex; flex-direction: column; align-items: center; justify-content: center;}
        .player {position: absolute; width: 60vw; height: 20vw; padding: 10px 30px;}
        .player-0 {left: 20vw; top: 40vw;}
        .player-1 {left: 60vw; top: 20vw; rotate: -90deg;}
        .player-2 {left: 20vw; top: 0; rotate: 180deg;}
        .player-3 {left: -20vw; top: 20vw; rotate: 90deg;}
        .player h2 {width: 300px; margin: 0 auto 10px; text-align: center;}
        .player-active h2 {border-bottom: 3px solid #A00;}
        .comment {position: absolute; top: -5vw; left: 0; right: 0; width: 50%; margin: 0 auto; padding: 15px; background: #FFF; border-radius: 10px;}
        .comment::after {content: ""; position: absolute; bottom: -48px; right: 50%; border: 25px solid transparent; border-top: 25px solid #FFF;}
        .console {padding: 30px; background: #DDD;}
        label {display: block; width: 200px; padding: 0 5px; background: #FFF; border-radius: 5px 5px 0 0;}
        textarea {display: block; width: 500px; height: 100px; margin-bottom: 20px;}
        button {padding: 10px 40px;}
    </style>
</head>
<body>
<main class="table">
    <div class="board">
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
    </div>

    <div class="players">
    <?php foreach ($game->players as $i => $player): ?>
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
            <?php if ($player->comment): ?>
                <div class="comment"><?php echo $player->comment; ?></div>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>

</main>
<aside class="console">
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
</aside>
</body>
</html>