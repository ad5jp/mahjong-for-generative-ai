<?php

declare(strict_types=1);

namespace App;

use Exception;

class Player
{
    public string $name;

    public Agent $agent;

    public int $score;

    /**
     * @var Pai[]
     */
    public array $hand = []; // 手牌

    public Pai|null $drawing = null; // ツモ牌

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

    public function __construct(string $name, Agent $agent)
    {
        $this->name = $name;
        $this->agent = $agent;
    }

    public function draw(Pai $pai)
    {
        $this->drawing = $pai;
    }

    public function discard(Pai $target, bool $riichi): void
    {
        // ツモ牌があれば手牌に統合
        if ($this->drawing) {
            $this->hand[] = $this->drawing;
            $this->drawing = null;
        }

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

    public function canRiichi(): bool
    {
        // TODO テンパイ形の判定もしたい

        // 既にリーチしてたらNG
        if ($this->riichi) {
            return false;
        }

        // 鳴いてなければOK
        if (empty($this->open)) {
            return true;
        }

        // 副露牌があっても暗槓のみならOK
        return count(array_filter($this->open, fn (OpenPais $open) => $open->type !== OpenType::ANKAN)) === 0;
    }

    public function canAnkan(Pai|null $target_pai = null, bool $throw = false): bool
    {
        $hand = $this->hand;
        if ($this->drawing) {
            $hand[] = $this->drawing;
        }

        if ($target_pai) {
            // 牌が指定された場合、手牌に4枚あるか確認
            $same = array_filter($this->hand, fn (Pai $pai) => $pai === $target_pai);
            if (count($same) !== 4) {
                return $throw ? throw new Exception($target_pai->letter() . 'を4枚持っていないので、カンできません！') : false;
            }
        } else {
            // 未指定の場合、手配に何かしらが4枚あるか確認
            if (max(array_count_values(array_map(fn (Pai $pai) => $pai->value, $hand))) < 4) {
                return $throw ? throw new Exception('4枚持っている牌がないので、カンできません！') : false;
            }
        }

        return true;
    }

    public function ankan(Pai $target_pai): void
    {
        $this->canAnkan($target_pai, true);

        // ツモ牌があれば手牌に統合
        if ($this->drawing) {
            $this->hand[] = $this->drawing;
            $this->drawing = null;
        }

        // 手牌から全て取り除く
        $this->hand = array_values(array_filter($this->hand, fn (Pai $pai) => $pai !== $target_pai));

        // 副露する
        $open = new OpenPais();
        $open->type = OpenType::ANKAN;
        $open->pais = array_fill(0, 4, $target_pai);
        $this->open[] = $open;
    }

    public function canKakan(Pai $target_pai, bool $throw = false): bool
    {
        // ポンしてるか確認
        $pon = array_find($this->open, fn (OpenPais $open) => $open->type === OpenType::PON && $open->pais[0] === $target_pai);
        if ($pon === null) {
            return $throw ? throw new Exception('ポンしていないので、加槓できません') : false;
        }

        return true;
    }

    public function kakan(Pai $target_pai): void
    {
        $this->canKakan($target_pai, true);

        // ポンにツモ牌を加えてカンにする
        $pon = array_find($this->open, fn (OpenPais $open) => $open->type === OpenType::PON && $open->pais[0] === $target_pai);
        $pon->type = OpenType::KAKAN;
        $pon->pais[] = $this->drawing;
        $this->drawing = null;
    }

    public function canTsumo(bool $throw = false): bool
    {
        $hand = $this->hand;
        if ($this->drawing) {
            $hand[] = $this->drawing;
        }

        if (!Finalize::verify($hand)) {
            return $throw ? throw new Exception('和了形ではないのでツモできません') : false;
        }

        return true;
    }

    public function sortHand()
    {
        usort($this->hand, fn (Pai $a, Pai $b) => $a->value <=> $b->value);
    }

    public function showHand()
    {
        $hand = $this->hand;
        if ($this->drawing) {
            $hand[] = $this->drawing;
            usort($hand, fn (Pai $a, Pai $b) => $a->value <=> $b->value);
        }
        $string = join(' ', array_map(fn (Pai $pai) => $pai->letter(), $hand));

        return $string;
    }

    public function showOpen()
    {
        return join(' ', array_map(function (OpenPais $open_pais) {
            return sprintf(
                '%s (%s)',
                $open_pais->type->label(),
                join(' ', array_map(fn (Pai $pai) => $pai->letter(), $open_pais->pais)),
            );
        }, $this->open));
    }

    public function showRiver()
    {
        return join(' ', array_map(function (RiverPai $river_pai) {
            $str = $river_pai->pai->letter();
            if ($river_pai->riichi) {
                $str .= "(リーチ)";
            }
            if ($river_pai->called) {
                $str .= "(鳴)";
            }

            return $str;
        }, $this->river));
    }
}
