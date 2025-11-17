<?php

declare(strict_types=1);

namespace App;

enum Pai: string
{
    // 萬子
    case M1 = 'M1';
    case M2 = 'M2';
    case M3 = 'M3';
    case M4 = 'M4';
    case M5 = 'M5';
    case M6 = 'M6';
    case M7 = 'M7';
    case M8 = 'M8';
    case M9 = 'M9';
    // 筒子
    case P1 = 'P1';
    case P2 = 'P2';
    case P3 = 'P3';
    case P4 = 'P4';
    case P5 = 'P5';
    case P6 = 'P6';
    case P7 = 'P7';
    case P8 = 'P8';
    case P9 = 'P9';
    // 索子
    case S1 = 'S1';
    case S2 = 'S2';
    case S3 = 'S3';
    case S4 = 'S4';
    case S5 = 'S5';
    case S6 = 'S6';
    case S7 = 'S7';
    case S8 = 'S8';
    case S9 = 'S9';
    // 字牌
    case Z1 = 'Z1'; // 東
    case Z2 = 'Z2'; // 南
    case Z3 = 'Z3'; // 西
    case Z4 = 'Z4'; // 北
    case Z5 = 'Z5'; // 白
    case Z6 = 'Z6'; // 発
    case Z7 = 'Z7'; // 中

    public function html(): string
    {
        return sprintf('<span class="pai %s"></span>', $this->value);
    }
}
