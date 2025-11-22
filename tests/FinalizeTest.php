<?php

use App\Finalize;
use App\Pai;

require('vendor/autoload.php');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M1',
    'M3', 'M3',
    'P2', 'P2',
    'P7', 'P7',
    'S4', 'S4',
    'Z1', 'Z1',
    'Z3', 'Z3'
]);
assertTrue(Finalize::verify($hand), '七対子成立');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M1',
    'M3', 'M4',
    'P2', 'P2',
    'P7', 'P7',
    'S4', 'S4',
    'Z1', 'Z1',
    'Z3', 'Z3'
]);
assertFalse(Finalize::verify($hand), '七対子不成立（1組違い）');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M1',
    'M3', 'M3',
    'P2', 'P2', 'P2', 'P2',
    'S4', 'S4',
    'Z1', 'Z1',
    'Z3', 'Z3'
]);
assertFalse(Finalize::verify($hand), '七対子不成立（同種4枚）');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M9', 'M9', 'P1', 'P9', 'S1', 'S9', 'Z1', 'Z2', 'Z3', 'Z4', 'Z5', 'Z6', 'Z7'
]);
assertTrue(Finalize::verify($hand), '国士無双成立');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M9', 'M9', 'P1', 'P9', 'S1', 'S9', 'Z1', 'Z2', 'Z3', 'Z4', 'Z5', 'Z6', 'Z6'
]);
assertFalse(Finalize::verify($hand), '国士無双不立');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M1', 'M1',
    'M3', 'M4', 'M5',
    'P1', 'P1',
    'P4', 'P4',
    'S1', 'S1',
    'S5', 'S5'
]);
assertFalse(Finalize::verify($hand), '和了形ではない');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M1', 'M1',
    'M3', 'M4', 'M5',
    'P1', 'P1', 'P1',
    'P4', 'P4',
    'Z1', 'Z1', 'Z2',
]);
assertFalse(Finalize::verify($hand), '和了形ではない');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M1', 'M1',
    'M3', 'M4', 'M5',
    'P1', 'P1', 'P1',
    'Z1', 'Z1',
    'Z4', 'Z4', 'Z5',
]);
assertFalse(Finalize::verify($hand), '和了形ではない');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M1', 'M1',
    'M3', 'M4', 'M5',
    'P4', 'P4',
    'Z1', 'Z1',
    'Z2', 'Z2',
    'Z3', 'Z3',
]);
assertFalse(Finalize::verify($hand), '和了形ではない');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M1', 'M1',
    'M1', 'M2', 'M3',
    'P1', 'P2', 'P3',
    'P3', 'P3',
    'P4', 'P4', 'P4',
]);
assertTrue(Finalize::verify($hand), '和了形');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M2', 'M3',
    'M2', 'M3', 'M4',
    'M4', 'M4',
    'P1', 'P2', 'P3',
    'P4', 'P4', 'P4',
]);
assertTrue(Finalize::verify($hand), '和了形');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M1', 'M1',
    'M2', 'M2', 'M3',
    'P1', 'P2', 'P3',
    'P3', 'P3',
    'P4', 'P4', 'P4',
]);
assertFalse(Finalize::verify($hand), '和了形ではない');

$hand = array_map(fn (string $code) => Pai::from($code), [
    'M1', 'M2', 'M3',
    'M2', 'M3', 'M5',
    'M4', 'M4',
    'P1', 'P2', 'P3',
    'P4', 'P4', 'P4',
]);
assertFalse(Finalize::verify($hand), '和了形ではない');


function assertTrue(bool $result, string $comment)
{
    if ($result) {
        echo '[OK] ' . $comment . "\n";
    } else {
        echo '[FAILED] ' . $comment . "\n";
    }
}

function assertFalse(bool $result, string $comment)
{
    if (!$result) {
        echo '[OK] ' . $comment . "\n";
    } else {
        echo '[FAILED] ' . $comment . "\n";
    }
}