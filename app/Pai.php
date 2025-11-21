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

    public function letter(): string
    {
        return match($this) {
            self::M1 => '1萬',
            self::M2 => '2萬',
            self::M3 => '3萬',
            self::M4 => '4萬',
            self::M5 => '5萬',
            self::M6 => '6萬',
            self::M7 => '7萬',
            self::M8 => '8萬',
            self::M9 => '9萬',
            self::P1 => '1筒',
            self::P2 => '2筒',
            self::P3 => '3筒',
            self::P4 => '4筒',
            self::P5 => '5筒',
            self::P6 => '6筒',
            self::P7 => '7筒',
            self::P8 => '8筒',
            self::P9 => '9筒',
            self::S1 => '1索',
            self::S2 => '2索',
            self::S3 => '3索',
            self::S4 => '4索',
            self::S5 => '5索',
            self::S6 => '6索',
            self::S7 => '7索',
            self::S8 => '8索',
            self::S9 => '9索',
            self::Z1 => '東',
            self::Z2 => '南',
            self::Z3 => '西',
            self::Z4 => '北',
            self::Z5 => '白',
            self::Z6 => '発',
            self::Z7 => '中',
        };
    }

    public static function fromLetter(string $letter): self
    {
        return match($letter) {
            '1萬' => self::M1,
            '2萬' => self::M2,
            '3萬' => self::M3,
            '4萬' => self::M4,
            '5萬' => self::M5,
            '6萬' => self::M6,
            '7萬' => self::M7,
            '8萬' => self::M8,
            '9萬' => self::M9,
            '1筒' => self::P1,
            '2筒' => self::P2,
            '3筒' => self::P3,
            '4筒' => self::P4,
            '5筒' => self::P5,
            '6筒' => self::P6,
            '7筒' => self::P7,
            '8筒' => self::P8,
            '9筒' => self::P9,
            '1索' => self::S1,
            '2索' => self::S2,
            '3索' => self::S3,
            '4索' => self::S4,
            '5索' => self::S5,
            '6索' => self::S6,
            '7索' => self::S7,
            '8索' => self::S8,
            '9索' => self::S9,
            '東' => self::Z1,
            '南' => self::Z2,
            '西' => self::Z3,
            '北' => self::Z4,
            '白' => self::Z5,
            '発' => self::Z6,
            '中' => self::Z7,
        };
    }
}
