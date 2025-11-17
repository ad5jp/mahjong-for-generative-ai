<?php

declare(strict_types=1);

namespace App;

class RiverPai
{
    public function __construct(
        public Pai $pai,
        public bool $riichi = false,
        public bool $called = false,
    ) {

    }

    public function html(): string
    {
        $classes = ['pai'];
        $classes[] = $this->pai->value;
        if ($this->riichi) {
            $classes[] = 'riichi';
        }
        if ($this->called) {
            $classes[] = 'called';
        }

        return sprintf('<span class="%s"></span>', join(' ', $classes));
    }
}
