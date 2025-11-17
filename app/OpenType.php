<?php

declare(strict_types=1);

namespace App;

enum OpenType: string
{
    case PON = 'PON';
    case KAN = 'KAN';
    case CHII = 'CHII';
    case ANKAN = 'ANKAN';
    case KAKAN = 'KAKAN';

    public function label(): string
    {
        return match ($this) {
            self::PON => 'ポン',
            self::KAN => '明槓',
            self::CHII => 'チー',
            self::ANKAN => '暗槓',
            self::KAKAN => '加槓',
        };
    }
}
