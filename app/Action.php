<?php

declare(strict_types=1);

namespace App;

use Exception;

class Action
{
    const START = 'start';
    const DISCARD = 'discard';
    const TSUMO = 'tsumo';
    const ANKAN = 'ankan';
    const KAKAN = 'kakan';
    const PON = 'pon';
    const KAN = 'kan';
    const CHII = 'chii';
    const RON = 'ron';
    const SKIP = 'skip';
    const CALCULATE = 'calculate';

    public string $command;

    public Pai $target; // DISCARD, ANKAN, KAKAN 対象牌

    public bool $riichi; // DISCARD リーチの有無

    public int $player; // PON, KAN, CHII 実行プレーヤー

    public array $components; // CHII 組合せ牌

    public array $points; // CALCULATE 得点移動

    public string|null $comment = null; // コメント

    public function __construct(array $attribute)
    {
        $this->command = $attribute['command'] ?? 'none';

        if (in_array($this->command, [self::DISCARD, self::ANKAN, self::KAKAN])) {
            $this->target = isset($attribute['target']) ? Pai::from($attribute['target']) : throw new Exception('parameter target is missing');
        }

        if ($this->command === self::DISCARD) {
            $this->riichi = $attribute['riichi'] ?? false;
        }

        if (in_array($this->command, [self::PON, self::KAN, self::CHII, self::RON])) {
            $this->player = $attribute['player'] ?? throw new Exception('parameter player is missing');
        }

        if ($this->command === self::CHII) {
            if (!isset($attribute['components']) || !is_array($attribute['components']) || count($attribute['components']) !== 2) {
                throw new Exception('parameter components is missing or invalid');
            }

            $this->components = array_map(fn ($value) => Pai::from($value), $attribute['components']);
        }

        if ($this->command === self::CALCULATE) {
            if (!isset($attribute['points']) || !is_array($attribute['points']) || count($attribute['points']) !== 4) {
                throw new Exception('parameter points is missing or invalid');
            }

            $this->points = $attribute['points'];
        }

        $this->comment = $attribute['comment'] ?? null;
    }
}
