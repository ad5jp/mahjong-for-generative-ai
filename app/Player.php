<?php

declare(strict_types=1);

namespace App;

use Exception;

class Player
{
    public string $name;

    public int $score;

    /**
     * @var Pai[]
     */
    public array $hand = []; // 手牌（最後がツモ牌）

    /**
     * @var OpenPais[]
     */
    public array $open = []; // 鳴き牌

    /**
     * @var RiverPai[]
     */
    public array $river = []; // 捨牌

    public bool $riichi = false; // リーチ

    public string|null $comment = null; // コメント

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function discard(Pai $target, bool $riichi): void
    {
        $index = array_search($target->value, array_map(fn (Pai $pai) => $pai->value, $this->hand));

        if ($index === false || $this->riichi) {
            array_pop($this->hand);
        } else {
            unset($this->hand[$index]);
        }

        $this->hand = array_values($this->hand);

        $this->sortHand();

        if ($riichi && !$this->riichi) {
            $this->riichi = true;
            $this->score -= 1000;
        }

        $this->river[] = new RiverPai($target, $riichi);
    }

    public function ankan(Pai $target_pai): void
    {
        // 手牌に4枚あるか確認
        $same = array_filter($this->hand, fn (Pai $pai) => $pai === $target_pai);
        if (count($same) !== 4) {
            throw new Exception('4枚持っていないので、カンできません！');
        }

        // 手牌から全て取り除く
        $this->hand = array_values(array_filter($this->hand, fn (Pai $pai) => $pai !== $target_pai));

        // 副露する
        $open = new OpenPais();
        $open->type = OpenType::ANKAN;
        $open->pais = array_fill(0, 4, $target_pai);
        $this->open[] = $open;
    }

    public function sortHand()
    {
        usort($this->hand, fn (Pai $a, Pai $b) => $a->value <=> $b->value);
    }

    public function showHand()
    {
        return join(' ', array_map(fn (Pai $pai) => $pai->value, $this->hand));
    }

    public function showOpen()
    {
        return join(' ', array_map(function (OpenPais $open_pais) {
            return sprintf(
                '%s (%s)',
                $open_pais->type->label(),
                join(' ', array_map(fn (Pai $pai) => $pai->value, $open_pais->pais)),
            );
        }, $this->open));
    }

    public function showRiver()
    {
        return join(' ', array_map(function (RiverPai $river_pai) {
            $str = $river_pai->pai->value;
            if ($river_pai->riichi) {
                $str .= "*";
            }
            if ($river_pai->called) {
                $str = "({$str})";
            }

            return $str;
        }, $this->river));
    }
}
