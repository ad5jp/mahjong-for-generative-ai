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

    public int|null $won_player = null; // 上がったプレーヤー (players の index)

    public bool $exhausted = false; // 流局

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

    public function lastRiver(): Pai|null
    {
        $river = $this->currentPlayer()->river;
        if (count($river) === 0) {
            return null;
        }

        return $river[array_key_last($river)]->pai;
    }

    public function callLastRiver(): void
    {
        $river = $this->currentPlayer()->river;
        if (count($river) === 0) {
            return;
        }

        $river[array_key_last($river)]->called = true;
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
                $this->currentPlayer()->canTsumo(true);
                $this->won_player = $this->current_player;
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
                if (count($this->deck) === 0) {
                    // 流局
                    $this->state = self::STATE_END;
                    $this->exhausted = true;
                } else {
                    // 次のプレイヤーの手番に
                    $this->current_player = $this->current_player === 3 ? 0 : $this->current_player + 1;
                    // 山からツモる
                    $player = $this->currentPlayer();
                    $player->draw(array_shift($this->deck));
                    $this->state = self::STATE_DISCARD;
                }
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
                // ロン可否判定
                $this->canRon($action->player, true);
                // ロンしたプレイヤーをアクティブに
                $this->won_player = $action->player;
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
                if ($this->won_player === $this->dealer_player) {
                    // 連荘
                    $this->round_chain++;
                } else {
                    // 親流れ
                    $this->round++;
                    $this->round_chain = 0;
                    $this->dealer_player = $this->dealer_player === 3 ? 0 : $this->dealer_player + 1;
                }

                $this->won_player = null;
                $this->exhausted = false;
                $this->after_kan = false;
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
            $player->drawing = null;
            $player->riichi = false;
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

    public function canCall(int $player_index): bool
    {
        return $this->canPon($player_index)
            || $this->canChi($player_index)
            || $this->canKan($player_index)
            || $this->canRon($player_index);
    }

    public function canPon(int $player_index, bool $throw = false): bool
    {
        if (count($this->deck) === 0) {
            return $throw ? throw new Exception('河底牌はポンできません！') : false;
        }

        if ($player_index === $this->current_player) {
            return $throw ? throw new Exception('自分の捨牌はポンできません！') : false;
        }

        // 対象牌
        $target_pai = $this->lastRiver();

        // 対象プレイヤー
        $action_player = $this->players[$player_index];

        // リーチかかってないか確認
        if ($action_player->riichi) {
            return $throw ? throw new Exception('リーチしているのでポンできません！') : false;
        }

        // 手牌に2枚以上あるか確認
        $same = array_filter($action_player->hand, fn (Pai $pai) => $pai === $target_pai);
        if (count($same) < 2) {
            return $throw ? throw new Exception('同じ牌を2枚以上持っていないので、ポンできません！') : false;
        }

        return true;
    }

    private function doPon(int $player_index): void
    {
        $this->canPon($player_index, true);

        // 対象牌
        $target_pai = $this->lastRiver();

        // 対象プレイヤー
        $action_player = $this->players[$player_index];

        // 手牌から2枚取り除く
        $count = 0;
        foreach ($action_player->hand as $i => $pai) {
            if ($pai === $target_pai) {
                unset($action_player->hand[$i]);
                $count++;
                if ($count === 2) {
                    break;
                }
            }
        }
        $action_player->hand = array_values($action_player->hand);

        // 鳴かれた捨牌
        $this->callLastRiver();

        // 副露する
        $open = new OpenPais();
        $open->type = OpenType::PON;
        $open->pais = array_fill(0, 3, $target_pai);
        $open->from = match ($player_index) {
            $this->nextPlayerIndex() => OpenFrom::LEFT,
            $this->acrossPlayerIndex() => OpenFrom::ACROSS,
            $this->prevPlayerIndex() => OpenFrom::RIGHT,
        };
        $action_player->open[] = $open;
    }

    public function canChi(int $player_index, bool $throw = false): bool
    {
        if (count($this->deck) === 0) {
            return $throw ? throw new Exception('河底牌はチーできません！') : false;
        }

        $chiable_player = $this->current_player === 3 ? 0 : $this->current_player + 1;

        if ($player_index !== $chiable_player) {
            return $throw ? throw new Exception('打牌した人の下家しかチーできません') : false;
        }

        // 対象プレイヤー
        $action_player = $this->players[$player_index];

        // リーチかかってないか確認
        if ($action_player->riichi) {
            return $throw ? throw new Exception('リーチしているのでチーできません！') : false;
        }

        // 対象牌
        $target_pai = $this->lastRiver();

        // チー可能な牌があるか
        $chiiables = $target_pai->chiiables();
        foreach ($chiiables as $chiiable) {
            $found_0 = array_search($chiiable[0], $action_player->hand);
            $found_1 = array_search($chiiable[1], $action_player->hand);
            if ($found_0 !== false && $found_1 !== false) {
                return true;
            }
        }

        return $throw ? throw new Exception('チー可能な牌がありません！') : false;
    }

    /**
     * @param array{0: Pai, 1:Pai} $components
     */
    private function doChi(int $player_index, array $components): void
    {
        $this->canChi($player_index, true);

        // 対象牌
        $target_pai = $this->lastRiver();

        // 対象プレイヤー
        $action_player = $this->players[$player_index];

        // チーの組合せ牌が正しいかチェック
        $chiiables = $target_pai->chiiables();
        if (count(array_filter($chiiables, function ($chiiable) use ($components) {
            return ($chiiable[0] === $components[0] && $chiiable[1] === $components[1])
                || ($chiiable[0] === $components[1] && $chiiable[1] === $components[0]);
        })) === 0) {
            throw new Exception('チーの組合せ牌が間違っています');
        }

        // 組合せ牌が手牌にあるかチェック
        $first_index = array_search($components[0], $action_player->hand);
        $second_index = array_search($components[1], $action_player->hand);
        if ($first_index === false || $second_index === false) {
            throw new Exception('チーの組合せ牌が手にありません');
        }

        // 組合せ牌を手牌から取り除く
        unset($action_player->hand[$first_index]);
        unset($action_player->hand[$second_index]);
        $action_player->hand = array_values($action_player->hand);

        // 鳴かれた捨牌
        $this->callLastRiver();

        // 副露する
        $open = new OpenPais();
        $open->type = OpenType::CHII;
        $open->pais = [$target_pai, $components[0], $components[1]];
        $open->from = OpenFrom::LEFT;
        $action_player->open[] = $open;
    }

    public function canKan(int $player_index, bool $throw = false): bool
    {
        if (count($this->deck) === 0) {
            return $throw ? throw new Exception('河底牌はカンできません！') : false;
        }

        if ($player_index === $this->current_player) {
            return $throw ? throw new Exception('自分の捨牌はカンできません！') : false;
        }

        // 対象牌
        $target_pai = $this->lastRiver();

        // 対象プレイヤー
        $action_player = $this->players[$player_index];

        // リーチかかってないか確認
        if ($action_player->riichi) {
            return $throw ? throw new Exception('リーチしているのでカンできません！') : false;
        }

        // 手牌に3枚以上あるか確認
        $same = array_filter($action_player->hand, fn (Pai $pai) => $pai === $target_pai);
        if (count($same) < 3) {
            return $throw ? throw new Exception('同じ牌を3枚持っていないので、カンできません！') : false;
        }

        return true;
    }

    private function doKan(int $player_index): void
    {
        $this->canKan($player_index, true);

        // 対象牌
        $target_pai = $this->lastRiver();

        // 対象プレイヤー
        $action_player = $this->players[$player_index];

        // 手牌から3枚取り除く
        $count = 0;
        foreach ($action_player->hand as $i => $pai) {
            if ($pai === $target_pai) {
                unset($action_player->hand[$i]);
                $count++;
                if ($count === 3) {
                    break;
                }
            }
        }
        $action_player->hand = array_values($action_player->hand);

        // 鳴かれた捨牌
        $this->callLastRiver();

        // 副露する
        $open = new OpenPais();
        $open->type = OpenType::KAN;
        $open->pais = array_fill(0, 4, $target_pai);
        $open->from = match ($player_index) {
            $this->nextPlayerIndex() => OpenFrom::LEFT,
            $this->acrossPlayerIndex() => OpenFrom::ACROSS,
            $this->prevPlayerIndex() => OpenFrom::RIGHT,
        };
        $action_player->open[] = $open;
    }

    public function canRon(int $player_index, bool $throw = false): bool
    {
        if ($player_index === $this->current_player) {
            return $throw ? throw new Exception('自分の捨牌はロンできません！') : false;
        }

        // 対象牌
        $target_pai = $this->lastRiver();

        // 対象プレイヤー
        $action_player = $this->players[$player_index];

        // 手配＋対象牌
        $hand = array_merge($action_player->hand, [$target_pai]);

        if (Finalize::verify($hand)) {
            return true;
        }

        return $throw ? throw new Exception('ロンできる形ではありません') : false;
    }

    public function promptDiscard(): string
    {
        $can_ankan = $this->currentPlayer()->canAnkan();
        $can_kakan = $this->currentPlayer()->drawing && $this->currentPlayer()->canKakan($this->currentPlayer()->drawing);
        $can_tsumo = $this->currentPlayer()->canTsumo();
        $can_riichi = $this->currentPlayer()->canRiichi();

        $calls = array_values(array_filter([
            $can_ankan ? '暗槓' : null,
            $can_kakan ? '加槓' : null,
            $can_tsumo ? 'ツモ' : null,
        ]));

        $direction = "捨てる（打牌する）手牌を選択してください。";
        if (count($calls) === 1) {
            $direction = sprintf("捨てる（打牌する）手牌を選択するか、%sを宣言してください。", $calls[0]);
        } elseif (count($calls) > 1) {
            $direction = sprintf("捨てる（打牌する）手牌を選択するか、%sのいずれかを宣言してください。", join('、', $calls));
        }


        $prompt = '';
        $prompt .= 'あなたは麻雀の対局中です。状況は以下の通りで、あなたの手番です。' . $direction . "\n";
        $prompt .= "\n";
        $prompt .= '# 場' . "\n";
        $prompt .= $this->showRound() . "\n";
        $prompt .= 'ドラ: ' . join(' ', array_map(fn (Pai $pai) => $pai->forDora()->letter(), $this->dora)) . "\n";
        $prompt .= "\n";
        $prompt .= '## あなた' . ($this->current_player === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->currentPlayer()->score . "\n" ;
        $prompt .= '手牌: ' . $this->currentPlayer()->showHand() . "\n" ;
        if ($this->currentPlayer()->drawing) {
            $prompt .= 'ツモ牌: ' . $this->currentPlayer()->drawing->letter() . ' ※上記の手配にもツモ牌が含まれています。' . "\n";
        }
        $prompt .= '副露牌: ' . $this->currentPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->currentPlayer()->showRiver() . "\n" ;
        $prompt .= "\n";
        $prompt .= '## 下家' . ($this->nextPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->nextPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->nextPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->nextPlayer()->showRiver() . "\n" ;
        $prompt .= "\n";
        $prompt .= '## 対面' . ($this->acrossPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->acrossPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->acrossPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->acrossPlayer()->showRiver() . "\n" ;
        $prompt .= "\n";
        $prompt .= '## 上家' . ($this->prevPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->prevPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->prevPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->prevPlayer()->showRiver() . "\n" ;
        $prompt .= "\n";
        $prompt .= '# 回答形式' . "\n";
        $prompt .= '以下のようなJSON形式で、回答のみを示して下さい。' . "\n";
        $prompt .= "\n";
        if (count($calls) > 0) {
            $prompt .= '## 打牌する場合' . "\n";
        }
        $prompt .= '```' . "\n";
        $prompt .= '{' . "\n";
        $prompt .= '    "command": "discard",' . "\n";
        $prompt .= '    "target": "' . ($this->currentPlayer()->drawing?->letter() ?? $this->currentPlayer()->hand[0]->letter()) . '",' . "\n";
        if ($can_riichi) {
            $prompt .= '    "riichi": false,' . "\n";
        }
        $prompt .= '    "comment": ""' . "\n";
        $prompt .= '}' . "\n";
        $prompt .= '```' . "\n";
        $prompt .= 'target ... 対象牌' . "\n";
        if ($can_riichi) {
            $prompt .= 'riichi ... リーチを宣言するか否か' . "\n";
        }
        $prompt .= 'comment ... 判断の理由 (50文字以内)' . "\n";
        $prompt .= "\n";
        if ($can_ankan) {
            $prompt .= '## 暗槓する場合' . "\n";
            $prompt .= '```' . "\n";
            $prompt .= '{' . "\n";
            $prompt .= '    "command": "ankan",' . "\n";
            $prompt .= '    "target": "' . ($this->currentPlayer()->drawing?->letter() ?? $this->currentPlayer()->hand[0]->letter()) . '",' . "\n";
            $prompt .= '    "comment": ""' . "\n";
            $prompt .= '}' . "\n";
            $prompt .= '```' . "\n";
            $prompt .= 'target ... 対象牌' . "\n";
            $prompt .= 'comment ... 判断の理由 (50文字以内)' . "\n";
            $prompt .= "\n";
        }
        if ($can_kakan) {
            $prompt .= '## 加槓する場合' . "\n";
            $prompt .= '```' . "\n";
            $prompt .= '{' . "\n";
            $prompt .= '    "command": "kakan",' . "\n";
            $prompt .= '    "target": "' . $this->currentPlayer()->drawing->letter() . '",' . "\n";
            $prompt .= '    "comment": ""' . "\n";
            $prompt .= '}' . "\n";
            $prompt .= '```' . "\n";
            $prompt .= 'target ... 対象牌' . "\n";
            $prompt .= 'comment ... 判断の理由 (50文字以内)' . "\n";
            $prompt .= "\n";
        }
        if ($can_tsumo) {
            $prompt .= '## ツモを宣言する場合' . "\n";
            $prompt .= '```' . "\n";
            $prompt .= '{' . "\n";
            $prompt .= '    "command": "tsumo",' . "\n";
            $prompt .= '    "comment": ""' . "\n";
            $prompt .= '}' . "\n";
            $prompt .= '```' . "\n";
            $prompt .= 'comment ... 判断の理由 (50文字以内)' . "\n";
            $prompt .= "\n";
        }

        return $prompt;
    }

    public function promptCall(int $player_index): string
    {
        $discard_player_label = match ($player_index) {
            $this->nextPlayerIndex() => '上家',
            $this->acrossPlayerIndex() => '対面',
            $this->prevPlayerIndex() => '下家',
        };

        $target_pai = $this->lastRiver();

        $can_ron = $this->canRon($player_index);
        $can_pon = $this->canPon($player_index);
        $can_kan = $this->canKan($player_index);
        $can_chii = $this->canChi($player_index);

        $calls = array_values(array_filter([
            $can_ron ? 'ロン' : null,
            $can_pon ? 'ポン' : null,
            $can_kan ? 'カン' : null,
            $can_chii ? 'チー' : null,
        ]));

        $direction = 'あなたは' . join('、', $calls) . 'を宣言することができます。' . "\n";
        if (count($calls) === 1) {
            $direction .= $calls[0] . 'を宣言するか、何もしないかを決めてください';
        } else {
            $direction .= join('、', $calls) . 'のいずれかを宣言するか、何もしないかを決めてください';
        }

        // テキスト生成の便宜上、current_player を一時的に変える（あとで戻す）
        $buffer_current_player = $this->current_player;
        $this->current_player = $player_index;

        $prompt = '';
        $prompt .= "あなたは麻雀の対局中です。状況は以下の通りで、{$discard_player_label}が「{$target_pai->letter()}」を打牌しました。" . "\n";
        $prompt .= $direction . "\n";
        $prompt .= "\n";
        $prompt .= '# 場' . "\n";
        $prompt .= $this->showRound() . "\n";
        $prompt .= 'ドラ: ' . join(' ', array_map(fn (Pai $pai) => $pai->forDora()->letter(), $this->dora)) . "\n";
        $prompt .= "\n";
        $prompt .= '## あなた' . ($this->current_player === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->currentPlayer()->score . "\n" ;
        $prompt .= '手牌: ' . $this->currentPlayer()->showHand() . "\n" ;
        $prompt .= '副露牌: ' . $this->currentPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->currentPlayer()->showRiver() . "\n" ;
        $prompt .= "\n";
        $prompt .= '## 下家' . ($this->nextPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->nextPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->nextPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->nextPlayer()->showRiver() . "\n" ;
        $prompt .= "\n";
        $prompt .= '## 対面' . ($this->acrossPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->acrossPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->acrossPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->acrossPlayer()->showRiver() . "\n" ;
        $prompt .= "\n";
        $prompt .= '## 上家' . ($this->prevPlayerIndex() === $this->dealer_player ? '(親)' : '') . "\n";
        $prompt .= '得点: ' . $this->prevPlayer()->score . "\n" ;
        $prompt .= '副露牌: ' . $this->prevPlayer()->showOpen() . "\n" ;
        $prompt .= '捨牌: ' . $this->prevPlayer()->showRiver() . "\n" ;
        $prompt .= "\n";
        $prompt .= '# 回答形式' . "\n";
        $prompt .= '以下のようなJSON形式で、回答のみを示して下さい。' . "\n";
        $prompt .= "\n";
        $prompt .= "## 何もしない場合";
        $prompt .= '```' . "\n";
        $prompt .= '{' . "\n";
        $prompt .= '    "player": ' . $player_index . ',' . "\n";
        $prompt .= '    "command": "skip",' . "\n";
        $prompt .= '    "comment": ""' . "\n";
        $prompt .= '}' . "\n";
        $prompt .= '```' . "\n";
        $prompt .= 'player ... 固定値 ' . $player_index . "\n";
        $prompt .= 'comment ... 判断の理由 (50文字以内)' . "\n";
        $prompt .= "\n";
        if ($can_ron) {
            $prompt .= "## ロンを宣言する場合";
            $prompt .= '```' . "\n";
            $prompt .= '{' . "\n";
            $prompt .= '    "player": ' . $player_index . ',' . "\n";
            $prompt .= '    "command": "ron",' . "\n";
            $prompt .= '    "comment": ""' . "\n";
            $prompt .= '}' . "\n";
            $prompt .= '```' . "\n";
            $prompt .= 'player ... 固定値 ' . $player_index . "\n";
            $prompt .= 'comment ... 判断の理由 (50文字以内)' . "\n";
            $prompt .= "\n";
        }
        if ($can_pon) {
            $prompt .= "## ポンを宣言する場合";
            $prompt .= '```' . "\n";
            $prompt .= '{' . "\n";
            $prompt .= '    "player": ' . $player_index . ',' . "\n";
            $prompt .= '    "command": "pon",' . "\n";
            $prompt .= '    "comment": ""' . "\n";
            $prompt .= '}' . "\n";
            $prompt .= '```' . "\n";
            $prompt .= 'player ... 固定値 ' . $player_index . "\n";
            $prompt .= 'comment ... 判断の理由 (50文字以内)' . "\n";
            $prompt .= "\n";
        }
        if ($can_kan) {
            $prompt .= "## カンを宣言する場合";
            $prompt .= '```' . "\n";
            $prompt .= '{' . "\n";
            $prompt .= '    "player": ' . $player_index . ',' . "\n";
            $prompt .= '    "command": "kan",' . "\n";
            $prompt .= '    "comment": ""' . "\n";
            $prompt .= '}' . "\n";
            $prompt .= '```' . "\n";
            $prompt .= 'player ... 固定値 ' . $player_index . "\n";
            $prompt .= 'comment ... 判断の理由 (50文字以内)' . "\n";
            $prompt .= "\n";
        }
        if ($can_chii) {
            $prompt .= "## チーを宣言する場合";
            $prompt .= '```' . "\n";
            $prompt .= '{' . "\n";
            $prompt .= '    "player": ' . $player_index . ',' . "\n";
            $prompt .= '    "command": "chii",' . "\n";
            $prompt .= '    "components": ["1萬", "2萬"],' . "\n";
            $prompt .= '    "comment": ""' . "\n";
            $prompt .= '}' . "\n";
            $prompt .= '```' . "\n";
            $prompt .= 'player ... 固定値 ' . $player_index . "\n";
            $prompt .= 'components ... 組み合わせる牌' . "\n";
            $prompt .= 'comment ... 判断の理由 (50文字以内)' . "\n";
            $prompt .= "\n";
        }

        $this->current_player = $buffer_current_player;

        return $prompt;
    }
}
