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

    public function ankan(Pai $target_pai): void
    {
        // ツモ牌があれば手牌に統合
        if ($this->drawing) {
            $this->hand[] = $this->drawing;
            $this->drawing = null;
        }

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

    public function kakan(Pai $target_pai): void
    {
        // ポンしてるか確認
        $pon = array_find($this->open, fn (OpenPais $open) => $open->type === OpenType::PON && $open->pais[0] === $target_pai);
        if ($pon === null) {
            throw new Exception('ポンしていないので、加槓できません');
        }

        // ポンにツモ牌を加えてカンにする
        $pon->type = OpenType::KAKAN;
        $pon->pais[] = $this->drawing;
        $this->drawing = null;
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
        if ($this->drawing) {
            $string .= sprintf(' (ツモ牌: %s)', $this->drawing->letter());
        }

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
