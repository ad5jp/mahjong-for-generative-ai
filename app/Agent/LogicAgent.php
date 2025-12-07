<?php

declare(strict_types=1);

namespace App\Agent;

use App\Action;
use App\Game;
use App\OpenPais;
use App\OpenType;
use App\Pai;
use App\Player;

class LogicAgent implements Agent
{
    public function decideDiscard(Game $game): Action
    {
        $me = $game->currentPlayer();

        // ツモできるならする
        if ($me->canTsumo()) {
            return new Action([
                'command' => Action::TSUMO,
            ]);
        }

        // 暗槓できるならする
        $counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $me->allHand()));
        foreach ($counts as $key => $count) {
            if ($count === 4) {
                return new Action([
                    'command' => Action::ANKAN,
                    'target' => Pai::from($key)->letter()
                ]);
            }
        }

        // 捨牌判定
        ($pai = $this->findSingleHonour($me))
        || ($pai = $this->findSingleEdge($me))
        || ($pai = $this->findSomething($me))
        || ($pai = $me->drawing)
        || ($pai = $me->hand[0]);

        return new Action([
            'command' => Action::DISCARD,
            'target' => $pai->letter()
        ]);
    }

    public function decideCall(Game $game, int $my_player_index): Action
    {
        if ($game->canRon($my_player_index)) {
            return new Action(['command' => Action::RON, 'player' => $my_player_index]);
        } elseif ($game->canPon($my_player_index) && !$game->canKan($my_player_index)) {
            return new Action(['command' => Action::PON, 'player' => $my_player_index]);
        }

        return new Action(['command' => Action::SKIP]);
    }

    /**
     * 孤立した字牌を探す
     *
     * @param Pai[] $hand
     */
    private function findSingleHonour(Player $me): Pai|null
    {
        $counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $me->allHand()));
        foreach(['Z1', 'Z2', 'Z3', 'Z4', 'Z5', 'Z6', 'Z7'] as $value) {
            if (isset($counts[$value]) && $counts[$value] === 1) {
                return Pai::from($value);
            }
        }

        return null;
    }

    /**
     * 孤立した端牌を探す
     * @param Pai[] $hand
     */
    private function findSingleEdge(Player $me): Pai|null
    {
        $counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $me->allHand()));

        $checker = [
            'M1' => 'M2',
            'M9' => 'M8',
            'P1' => 'P2',
            'P9' => 'P8',
            'S1' => 'S2',
            'S9' => 'S8',
            'M2' => 'M3',
            'M8' => 'M7',
            'P2' => 'P3',
            'P8' => 'P7',
            'S2' => 'S3',
            'S8' => 'S7',
        ];

        foreach ($checker as $value => $target) {
            if (isset($counts[$value]) && $counts[$value] === 1) {
                if (!isset($counts[$target])) {
                    return Pai::from($value);
                }
            }
        }

        return null;
    }

    /**
     * 戦略的に捨てるものを探す
     * @param Pai[] $hand
     */
    private function findSomething(Player $me): Pai|null
    {
        // 七対子を狙う
        if ($this->goingToSevenPairs($me)) {
            $counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $me->allHand()));
            // 1枚しかないものを切る
            foreach ($counts as $key => $count) {
                if ($count === 1) {
                    return Pai::from($key);
                }
            }
            // 3枚以上あるものを切る
            foreach ($counts as $key => $count) {
                if ($count >= 3) {
                    return Pai::from($key);
                }
            }
        }

        // 対々和を狙う
        if ($this->goingToFourTriplets($me)) {
            $counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $me->allHand()));
            // 1枚しかないものを切る
            foreach ($counts as $key => $count) {
                if ($count === 1) {
                    return Pai::from($key);
                }
            }
            // なければ、対子のいずれかを切る
            foreach ($counts as $key => $count) {
                if ($count === 2) {
                    return Pai::from($key);
                }
            }
        }

        // 混一色を狙う
        if ($this->goingToFlush($me, $target_category)) {
            // 対象外牌種で1枚しかないものを切る
            $others = array_filter($me->allHand(), fn (Pai $pai) => $pai->category() !== $target_category && $pai->category() !== 'Z');
            $counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $others));
            foreach ($counts as $key => $count) {
                if ($count === 1) {
                    return Pai::from($key);
                }
            }
            // なければ、字牌で1枚しかないものを切る
            $others = array_filter($me->allHand(), fn (Pai $pai) => $pai->category() === 'Z');
            $counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $others));
            foreach ($counts as $key => $count) {
                if ($count === 1) {
                    return Pai::from($key);
                }
            }
            // それもなければ、対象外牌種のいずれかを切る
            return $others[0];
        }

        // 孤立牌を切る
        $counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $me->allHand()));
        foreach ($counts as $key => $count) {
            if ($count > 1) {
                continue;
            }

            // 前後の牌
            $prev = Pai::from($key)->prev();
            $next = Pai::from($key)->next();

            // どっちかが null なら端牌から字牌なので切って良い
            if ($prev === null || $next === null) {
                return Pai::from($key);
            }

            // どちらも手牌になければ切る
            $found_prev = array_find($me->allHand(), fn (Pai $pai) => $pai === $prev);
            $found_next = array_find($me->allHand(), fn (Pai $pai) => $pai === $next);
            if ($found_prev === null && $found_next === null) {
                return Pai::from($key);
            }
        }

        return null;
    }

    /**
     * 七対子を狙えそうか
     */
    private function goingToSevenPairs(Player $me): bool
    {
        // 鳴いてたら無理
        if (count($me->open) > 0) {
            return false;
        }

        // 暗刻の数・対子の数
        $set = array_count_values(array_count_values(array_map(fn (Pai $pai) => $pai->value, $me->allHand())));
        $triplet_count = $set[3] ?? 0;
        $pair_count = $set[2] ?? 0;

        // 刻子が1組以下、対子が5組以上なら狙う
        return $triplet_count <= 1 && $pair_count >= 5;
    }

    /**
     * 対々和を狙えそうか
     */
    private function goingToFourTriplets(Player $me): bool
    {
        // チーしてたら無理
        $chii = array_find($me->open, fn (OpenPais $open) => $open->type === OpenType::CHII);
        if ($chii !== null) {
            return false;
        }

        // ポン（またはカン）の数
        $triplet_count = count(array_filter($me->open, fn (OpenPais $open) => $open->type !== OpenType::CHII));

        // 暗刻の数・対子の数
        $set = array_count_values(array_count_values(array_map(fn (Pai $pai) => $pai->value, $me->allHand())));
        $triplet_count += $set[3] ?? 0;
        $pair_count = $set[2] ?? 0;

        // 刻子が3組以上
        if ($triplet_count >= 3) {
            return true;
        }

        // 刻子が2組以上、対子が2組以上
        if ($triplet_count >= 2 && $pair_count >= 2) {
            return true;
        }

        // 刻子が1組以上、対子が4組以上
        if ($triplet_count >= 1 && $pair_count >= 4) {
            return true;
        }

        return false;
    }

    /**
     * 混一色を狙えそうか
     */
    private function goingToFlush(Player $me, &$target_category = null): bool
    {
        // 牌種ごとに数える
        $counts = array_count_values(array_map(fn (Pai $pai) => $pai->category(), $me->allHand()));
        $count_m = $counts['M'] ?? 0;
        $count_p = $counts['P'] ?? 0;
        $count_s = $counts['S'] ?? 0;
        $count_z = $counts['Z'] ?? 0;
        if ($count_m + $count_z >= 9) {
            $target_category = 'M';
        } elseif ($count_p + $count_z >= 9) {
            $target_category = 'P';
        } elseif ($count_s + $count_z >= 9) {
            $target_category = 'S';
        } else {
            return false;
        }

        // 対象牌種 or 字牌 以外で鳴いてたらアウト
        $out = array_find($me->open, fn (OpenPais $open) => $open->pais[0]->category() !== $target_category && $open->pais[0]->category() !== 'Z');
        if ($out !== null) {
            return false;
        }

        return true;
    }

}
