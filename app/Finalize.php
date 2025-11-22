<?php

declare(strict_types=1);

namespace App;

class Finalize
{
    /**
     * 和了系になっているか判定
     *
     * 計算量重視で、極力早期リターンが発生しやすく作ってる
     *
     * @param Pai[] $hand 手牌（副露牌を含まない）
     */
    public static function verify(array $hand): bool
    {
        // 面前なら、七対子、国士無双を先にチェックする
        if (count($hand) === 14) {
            if (self::verifySevenPairs($hand)) {
                // echo "- 七対子成立\n";
                return true;
            }
            if (self::verifyThirteenOrphans($hand)) {
                // echo "- 国士無双成立\n";
                return true;
            }
        }

        // 牌種ごとに分ける
        $chunked = [
            'M' => array_values(array_filter($hand, fn (Pai $p) => $p->isM())),
            'P' => array_values(array_filter($hand, fn (Pai $p) => $p->isP())),
            'S' => array_values(array_filter($hand, fn (Pai $p) => $p->isS())),
            'Z' => array_values(array_filter($hand, fn (Pai $p) => $p->isZ())),
        ];

        // 牌種ごとの枚数をチェック
        // 基本全て3の倍数。余り2が1つだけあるはず。
        $count_pair = 0;
        foreach ($chunked as $chunk) {
            if (count($chunk) % 3 === 1) {
                // echo "- 端数の牌種あり\n";
                return false; // いずれかの牌種が 1,4,7,10,13 枚だったら絶対和了形ではない
            } elseif (count($chunk) % 3 === 2) {
                $count_pair++;
            }
        }
        if ($count_pair !== 1) {
            // echo "- 頭含みの牌種が複数あり\n";
            return false;
        }

        // まず字牌をチェック
        $z_counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $chunked['Z']));
        $count_pair = 0;
        foreach ($z_counts as $z_count) {
            if ($z_count === 1 || $z_count > 3) {
                // echo "- 半端な字牌あり\n";
                return false; // いずれかの字牌が 1枚 または 4枚(以上)だったら絶対和了形ではない
            } elseif ($z_count === 2) {
                $count_pair++;
            }
        }
        // 2枚以上の字牌が2組以上あったら和了形ではない
        if ($count_pair > 1) {
            // echo "- 2枚の字牌が複数種あり\n";
            return false;
        }

        // 続いて数牌をそれぞれチェック
        unset($chunked['Z']);
        foreach ($chunked as $chunk) {
            // 処理しやすいように数値に変換
            $numbers = array_map(fn (Pai $pai) => $pai->number(), $chunk);
            sort($numbers);
            if (!self::verifyMenz($numbers, (count($numbers) % 3 === 2))) {
                // echo "- 面子じゃない\n";
                return false;
            }
        }

        // echo "- 最終\n";
        return true;
    }

    /**
     * 七対子判定
     *
     * @param Pai[] $hand
     */
    private static function verifySevenPairs(array $hand): bool
    {
        $counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $hand));

        if (count($counts) !== 7) {
            return false;
        }

        foreach ($counts as $count) {
            if ($count !== 2) {
                return false;
            }
        }

        return true;
    }

    /**
     * 国士無双判定
     *
     * @param Pai[] $hand
     */
    private static function verifyThirteenOrphans(array $hand): bool
    {
        $orphans = [
            Pai::M1,
            Pai::M9,
            Pai::P1,
            Pai::P9,
            Pai::S1,
            Pai::S9,
            Pai::Z1,
            Pai::Z2,
            Pai::Z3,
            Pai::Z4,
            Pai::Z5,
            Pai::Z6,
            Pai::Z7,
        ];

        foreach ($orphans as $orphan) {
            if (!in_array($orphan, $hand)) {
                return false;
            }
        }

        return true;
    }

    /**
     * メンツ判定
     *
     * ソート済の数値が渡ってくる想定
     * 再起呼び出しされる
     *
     * @param int[] $numbers
     */
    private static function verifyMenz(array $numbers, bool $has_head): bool
    {
        if (count($numbers) === 0) {
            return true;
        }

        return self::verifyMenzStartWithTriplet($numbers, $has_head)
            || self::verifyMenzStartWithSequence($numbers, $has_head)
            || ($has_head && self::verifyMenzStartWithPair($numbers));
    }

    /**
     * メンツ判定のサブルーチン
     *
     * 先頭の数字が刻子の一部であると仮定してチェックする
     *
     * @param int[] $numbers
     */
    private static function verifyMenzStartWithTriplet(array $numbers, bool $has_head): bool
    {
        if (count($numbers) < 3) {
            return false;
        }

        if ($numbers[0] !== $numbers[1] || $numbers[0] !== $numbers[2]) {
            return false;
        }

        // 最初の3つの数字が同じだったので、残りの部分で再チェックをかける
        array_splice($numbers, 0, 3);
        return self::verifyMenz($numbers, $has_head);
    }

    /**
     * メンツ判定のサブルーチン
     *
     * 先頭の数字が順子の一部であると仮定してチェックする
     *
     * @param int[] $numbers
     */
    private static function verifyMenzStartWithSequence(array $numbers, bool $has_head): bool
    {
        if (count($numbers) < 3) {
            return false;
        }

        // 1つめを取り出す
        $first = array_shift($numbers);

        // 次の数字を探す
        $index = array_search($first + 1, $numbers);
        if ($index === false) {
            return false;
        }
        unset($numbers[$index]);

        // 次の次の数字を探す
        $index = array_search($first + 2, $numbers);
        if ($index === false) {
            return false;
        }
        unset($numbers[$index]);

        // 最初の3つの数字が同じだったので、残りの部分で再チェックをかける
        $numbers = array_values($numbers);
        return self::verifyMenz($numbers, $has_head);
    }

    /**
     * メンツ判定のサブルーチン
     *
     * 先頭の数字が雀頭の一部であると仮定してチェックする
     *
     * @param int[] $numbers
     */
    private static function verifyMenzStartWithPair(array $numbers): bool
    {
        if (count($numbers) < 2) {
            return false;
        }

        if ($numbers[0] !== $numbers[1]) {
            return false;
        }

        // 最初の2つの数字が同じだったので、残りの部分で再チェックをかける
        array_splice($numbers, 0, 2);
        return self::verifyMenz($numbers, false); // 雀頭はもうない
    }
}
