<?php

declare(strict_types=1);

namespace App;

use Exception;

class Game
{
    const STATE_READY = 0;
    const STATE_DISCARD = 1;
    const STATE_CALL = 2;
    const STATE_END = 3;

    public int $round = 1; // 1:東1局 2:東2局 ... 8:南4局

    public int $round_chain = 0; // N本場

    public int $dealer_player = 0; // 親 (players の index)

    public int $current_player = 0; // 手番 (players の index)

    public int $state = 0; // プレイ状態 (0:対局準備中 1:捨て待ち 2:鳴き待ち 3:終局)

    public bool $after_kan = false; // 明槓後か（捨てた後、ドラをめくる）

    /**
     * @var Player[]
     */
    public array $players = [];

    /**
     * @var Pai[]
     */
    public array $deck = []; // 山

    /**
     * @var Pai[]
     */
    public array $dora = []; // ドラ指示牌

    /**
     * @var Pai[]
     */
    public array $bed = []; // 王牌（ドラ指示牌除く）

    public function __construct(array $players, int $initial_score = 27000)
    {
        $this->players = array_map(function (Player $player) use ($initial_score) {
            $player->score = $initial_score;
            return $player;
        }, $players);
    }

    public function showRound(): string
    {
        $directions = ['東', '南', '西', '北'];
        $direction_index = floor($this->round / 4);
        $count = $this->round % 4;
        return sprintf('%s%s局 (%s本場)', $directions[$direction_index], $count, $this->round_chain);
    }

    public function nextPlayerIndex(): int
    {
        $next = $this->current_player + 1;
        return $next >= 4 ? $next - 4 : $next;
    }

    public function acrossPlayerIndex(): int
    {
        $across = $this->current_player + 2;
        return $across >= 4 ? $across - 4 : $across;
    }

    public function prevPlayerIndex(): int
    {
        $prev = $this->current_player + 3;
        return $prev >= 4 ? $prev - 4 : $prev;
    }

    public function currentPlayer(): Player
    {
        return $this->players[$this->current_player];
    }

    public function nextPlayer(): Player
    {
        return $this->players[$this->nextPlayerIndex()];
    }

    public function acrossPlayer(): Player
    {
        return $this->players[$this->acrossPlayerIndex()];
    }

    public function prevPlayer(): Player
    {
        return $this->players[$this->prevPlayerIndex()];
    }

    public function play(Action $action): void
    {
        // TODO 流局処理
        // TODO 保留棒

        // コメントのリセット
        foreach ($this->players as $player) {
            $player->comment = null;
        }
        if ($this->state === self::STATE_DISCARD) {
            $this->currentPlayer()->comment = $action->comment;
        }
        if ($this->state === self::STATE_CALL && isset($action->player)) {
            $this->players[$action->player]->comment = $action->comment;
        }

        if ($this->state === self::STATE_READY) {
            if ($action->command === Action::START) {
                // 配牌する
                $this->startNewRound();

                $this->state = self::STATE_DISCARD;
            }
        } elseif ($this->state === self::STATE_DISCARD) {
            if ($action->command === Action::DISCARD) {
                // 打牌
                $player = $this->currentPlayer();
                $player->discard($action->target, $action->riichi);
                // 明槓後ならカンドラをめくる
                if ($this->after_kan) {
                    $this->dora[] = array_shift($this->bed);
                    $this->after_kan = false;
                }
                $this->state = self::STATE_CALL;
            } elseif ($action->command === Action::TSUMO) {
                // 終局
                $this->state = self::STATE_END;
            } elseif ($action->command === Action::ANKAN) {
                $this->currentPlayer()->ankan($action->target);
                // 嶺上牌をツモる
                $this->currentPlayer()->draw(array_shift($this->bed));
                // カンドラをめくる
                $this->dora[] = array_shift($this->bed);
            } elseif ($action->command === Action::KAKAN) {
                $this->currentPlayer()->kakan($action->target);
                // 嶺上牌をツモる
                $this->currentPlayer()->draw(array_shift($this->bed));
                // カンドラはまだめくらいない
                $this->after_kan = true;
            }
        } elseif ($this->state === self::STATE_CALL) {
            if ($action->command === Action::SKIP) {
                // 次のプレイヤーの手番に
                $this->current_player = $this->current_player === 3 ? 0 : $this->current_player + 1;
                // 山からツモる
                $player = $this->currentPlayer();
                $player->draw(array_shift($this->deck));
                $this->state = self::STATE_DISCARD;
            } elseif ($action->command === Action::PON) {
                // ポン実行
                $this->doPon($action->player);
                // ポンしたプレイヤーの手番に
                $this->current_player = $action->player;
                $this->state = self::STATE_DISCARD;
            } elseif ($action->command === Action::KAN) {
                // カン実行
                $this->doKan($action->player);
                // カンしたプレイヤーの手番に
                $this->current_player = $action->player;
                // 嶺上牌をツモる
                $this->currentPlayer()->draw(array_shift($this->bed));
                // カンドラはまだめくらいない
                $this->after_kan = true;
                $this->state = self::STATE_DISCARD;
            } elseif ($action->command === Action::CHII) {
                // チー実行
                $this->doChi($action->player, $action->components);
                // チーしたプレイヤーの手番に
                $this->current_player = $action->player;
                $this->state = self::STATE_DISCARD;
            } elseif ($action->command === Action::RON) {
                // ロン実行
                $this->doRon($action->player);
                // ロンしたプレイヤーをアクティブに
                $this->current_player = $action->player;
                // 終局
                $this->state = self::STATE_END;
            }
        } elseif ($this->state === self::STATE_END) {
            if ($action->command === Action::CALCULATE) {
                // 得点を移動
                $this->players[0]->score += $action->points[0];
                $this->players[1]->score += $action->points[1];
                $this->players[2]->score += $action->points[2];
                $this->players[3]->score += $action->points[3];

                // 次局へ
                if ($this->current_player === $this->dealer_player) {
                    // 連荘
                    $this->round_chain++;
                } else {
                    // 親流れ
                    $this->round++;
                    $this->round_chain = 0;
                    $this->dealer_player = $this->dealer_player === 3 ? 0 : $this->dealer_player + 1;
                }
                $this->state = self::STATE_READY;
            }
        }
    }

    private function startNewRound(): void
    {
        // 全ての牌をシャッフル
        $this->deck = [];
        foreach (Pai::cases() as $pai) {
            $this->deck = array_merge($this->deck, array_fill(0, 4, $pai));
        }
        shuffle($this->deck);

        // 手配を配る
        foreach ($this->players as $index => $player) {
            $player->open = [];
            $player->river = [];
            $player->hand = array_splice($this->deck, 0, ($index === $this->dealer_player ? 14 : 13));
            $player->sortHand();
        }

        // 1枚をドラ指示牌に
        $this->dora = array_splice($this->deck, 0, 1);

        // 13枚を王牌に
        $this->bed = array_splice($this->deck, 0, 13);

        // 親の手番にする
        $this->current_player = $this->dealer_player;
    }

    private function doPon(int $player_index): void
    {
        if ($player_index === $this->current_player) {
            throw new Exception('自分の捨牌はポンできません！');
        }

        // 対象牌
        /** @var RiverPai $last_river */
        $last_river = end($this->currentPlayer()->river);

        // 対象プレイヤー
        $action_player = $this->players[$player_index];

        // リーチかかってないか確認
        if ($action_player->riichi) {
            throw new Exception('リーチしているのでポンできません！');
        }

        // 手牌に2枚以上あるか確認
        $same = array_filter($action_player->hand, fn (Pai $pai) => $pai === $last_river->pai);
        if (count($same) < 2) {
            throw new Exception('同じ牌を2枚以上持っていないので、ポンできません！');
        }

        // 手牌から2枚取り除く
        $count = 0;
        foreach ($action_player->hand as $i => $pai) {
            if ($pai === $last_river->pai) {
                unset($action_player->hand[$i]);
                $count++;
                if ($count === 2) {
                    break;
                }
            }
        }
        $action_player->hand = array_values($action_player->hand);

        // 鳴かれた捨牌
        $last_river->called = true;

        // 副露する
        $open = new OpenPais();
        $open->type = OpenType::PON;
        $open->pais = array_fill(0, 3, $last_river->pai);
        $open->from = match ($player_index) {
            $this->nextPlayerIndex() => OpenFrom::LEFT,
            $this->acrossPlayerIndex() => OpenFrom::ACROSS,
            $this->prevPlayerIndex() => OpenFrom::RIGHT,
        };
        $action_player->open[] = $open;
    }

    /**
     * @param array{0: Pai, 1:Pai} $components
     */
    private function doChi(int $player_index, array $components): void
    {
        $chiable_player = $this->current_player === 3 ? 0 : $this->current_player + 1;

        if ($player_index !== $chiable_player) {
            throw new Exception('打牌した人の下家しかチーできません');
        }

        // 対象牌
        /** @var RiverPai $last_river */
        $last_river = end($this->currentPlayer()->river);

        // 対象プレイヤー
        $action_player = $this->players[$player_index];

        // リーチかかってないか確認
        if ($action_player->riichi) {
            throw new Exception('リーチしているのでチーできません！');
        }

        // 対象牌を手牌から取り除く
        $first_index = array_search($components[0], $action_player->hand);
        if ($first_index === false) {
            throw new Exception($components[0]->value . 'が手牌にありません！');
        }
        unset($action_player->hand[$first_index]);
        $second_index = array_search($components[1], $action_player->hand);
        if ($second_index === false) {
            throw new Exception($components[1]->value . 'が手牌にありません！');
        }
        unset($action_player->hand[$second_index]);
        $action_player->hand = array_values($action_player->hand);

        // 鳴かれた捨牌
        $last_river->called = true;

        // 副露する
        $open = new OpenPais();
        $open->type = OpenType::CHII;
        $open->pais = [$last_river->pai, $components[0], $components[1]];
        $open->from = OpenFrom::LEFT;
        $action_player->open[] = $open;
    }

    private function doKan(int $player_index): void
    {
        if ($player_index === $this->current_player) {
            throw new Exception('自分の捨牌はカンできません！');
        }

        // 対象牌
        /** @var RiverPai $last_river */
        $last_river = end($this->currentPlayer()->river);

        // 対象プレイヤー
        $action_player = $this->players[$player_index];

        // リーチかかってないか確認
        if ($action_player->riichi) {
            throw new Exception('リーチしているのでカンできません！');
        }

        // 手牌に3枚あるか確認
        $same = array_filter($action_player->hand, fn (Pai $pai) => $pai === $last_river->pai);
        if (count($same) < 3) {
            throw new Exception('同じ牌を3枚持っていないので、カンできません！');
        }

        // 手牌から3枚取り除く
        $count = 0;
        foreach ($action_player->hand as $i => $pai) {
            if ($pai === $last_river->pai) {
                unset($action_player->hand[$i]);
                $count++;
                if ($count === 3) {
                    break;
                }
            }
        }
        $action_player->hand = array_values($action_player->hand);

        // 鳴かれた捨牌
        $last_river->called = true;

        // 副露する
        $open = new OpenPais();
        $open->type = OpenType::KAN;
        $open->pais = array_fill(0, 4, $last_river->pai);
        $open->from = match ($player_index) {
            $this->nextPlayerIndex() => OpenFrom::LEFT,
            $this->acrossPlayerIndex() => OpenFrom::ACROSS,
            $this->prevPlayerIndex() => OpenFrom::RIGHT,
        };
        $action_player->open[] = $open;
    }

    private function doRon(int $player_index): void
    {
        if ($player_index === $this->current_player) {
            throw new Exception('自分の捨牌はロンできません！');
        }

        // 対象牌
        /** @var RiverPai $last_river */
        $last_river = end($this->currentPlayer()->river);
        $last_river->called = true;

        // 対象プレイヤー
        $action_player = $this->players[$player_index];

        // 手牌に加える
        $action_player->draw($last_river->pai);
    }

    public function promptDiscard(): string
    {
        $prompt = '';
        $prompt .= 'あなたは麻雀の対局中です。状況は以下の通りで、あなたの手番です。手牌を捨てるか、暗槓、加槓、ツモのいずれかを宣言してください。' . "\n";
        $prompt .= "\n";
        $prompt .= '# 場' . "\n";
        $prompt .= $this->showRound() . "\n";
        $prompt .= 'ドラ: ' . join(' ', array_map(fn (Pai $pai) => $pai->next()->letter(), $this->dora)) . "\n";
        $prompt .= '## あなた' . ($this->current_player === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->currentPlayer()->score . "\n" ;
        $prompt .= '手牌: ' . $this->currentPlayer()->showHand() . "\n" ;
        $prompt .= '副露牌: ' . $this->currentPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->currentPlayer()->showRiver() . "\n" ;
        $prompt .= '## 下家' . ($this->nextPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->nextPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->nextPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->nextPlayer()->showRiver() . "\n" ;
        $prompt .= '## 対面' . ($this->acrossPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->acrossPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->acrossPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->acrossPlayer()->showRiver() . "\n" ;
        $prompt .= '## 上家' . ($this->prevPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->prevPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->prevPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->prevPlayer()->showRiver() . "\n" ;
        $prompt .= "\n";
        $prompt .= '# 回答形式' . "\n";
        $prompt .= '以下のようなJSON形式で、回答のみを示して下さい。' . "\n";
        $prompt .= '```' . "\n";
        $prompt .= '{' . "\n";
        $prompt .= '    "command": "discard",' . "\n";
        $prompt .= '    "target": "' . $this->currentPlayer()->hand[0]->letter() . '",' . "\n";
        $prompt .= '    "riichi": false,' . "\n";
        $prompt .= '    "comment": ""' . "\n";
        $prompt .= '}' . "\n";
        $prompt .= '```' . "\n";
        $prompt .= 'command ... discard: 打牌, ankan: 暗槓, kakan: 加槓, tsumo: ツモ のいずれか' . "\n";
        $prompt .= 'target ... 打牌、暗槓、加槓 の場合の対象牌' . "\n";
        $prompt .= 'riichi ... 打牌の場合、リーチするか否か' . "\n";
        $prompt .= 'comment ... 判断の理由 (50文字以内)' . "\n";

        return $prompt;
    }

    public function promptCall(int $player_index): string
    {
        $discard_player_label = match ($player_index) {
            $this->nextPlayerIndex() => '上家',
            $this->acrossPlayerIndex() => '対面',
            $this->prevPlayerIndex() => '下家',
        };

        // テキスト生成の便宜上、current_player を一時的に変える（あとで戻す）
        $buffer_current_player = $this->current_player;
        $this->current_player = $player_index;

        $prompt = '';
        $prompt .= "あなたは麻雀の対局中です。状況は以下の通りで、{$discard_player_label}が打牌しました。ポン、カン、チー、ロンのいずれかを宣言するか、何もしないかを決めてください。" . "\n";
        $prompt .= "\n";
        $prompt .= '# 場' . "\n";
        $prompt .= $this->showRound() . "\n";
        $prompt .= 'ドラ: ' . join(' ', array_map(fn (Pai $pai) => $pai->next()->letter(), $this->dora)) . "\n";
        $prompt .= '## あなた' . ($this->current_player === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->currentPlayer()->score . "\n" ;
        $prompt .= '手牌: ' . $this->currentPlayer()->showHand() . "\n" ;
        $prompt .= '副露牌: ' . $this->currentPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->currentPlayer()->showRiver() . "\n" ;
        $prompt .= '## 下家' . ($this->nextPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->nextPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->nextPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->nextPlayer()->showRiver() . "\n" ;
        $prompt .= '## 対面' . ($this->acrossPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->acrossPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->acrossPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->acrossPlayer()->showRiver() . "\n" ;
        $prompt .= '## 上家' . ($this->prevPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->prevPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->prevPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->prevPlayer()->showRiver() . "\n" ;
        $prompt .= "\n";
        $prompt .= '# 回答形式' . "\n";
        $prompt .= '以下のようなJSON形式で、回答のみを示して下さい。' . "\n";
        $prompt .= '```' . "\n";
        $prompt .= '{' . "\n";
        $prompt .= '    "player": ' . $player_index . ',' . "\n";
        $prompt .= '    "command": "chii",' . "\n";
        $prompt .= '    "components": ["1萬", "2萬"],' . "\n";
        $prompt .= '    "comment": ""' . "\n";
        $prompt .= '}' . "\n";
        $prompt .= '```' . "\n";
        $prompt .= 'player ... 固定値 ' . $player_index . "\n";
        $prompt .= 'command ... pon: ポン, kan: カン, chii: チー, ron: ロン skip: 何もしない のいずれか' . "\n";
        $prompt .= 'components ... チーの場合、組み合わせる牌' . "\n";
        $prompt .= 'comment ... 判断の理由 (50文字以内)' . "\n";

        $this->current_player = $buffer_current_player;

        return $prompt;
    }
}
