<?php

declare(strict_types=1);

namespace App;

class Listen
{
    const START = 'start';
    const DISCARD = 'discard';

    public function __construct(
        public string $type,
        public int|null $player = null
    ) {

    }
}
