<?php

declare(strict_types=1);

namespace App;

class OpenPais
{
    public OpenType $type;

    /**
     * @var Pai[]
     */
    public array $pais;

    public int|null $from = null; // 1:上家 2:対面 3:下家

    public function html(): string
    {
        return sprintf(
            '<div class="open %s %s">%s</div>',
            $this->type->value,
            match ($this->from) {
                1 => 'left',
                2 => 'center',
                3 => 'right',
                default => '',
            },
            join('', array_map(fn (Pai $pai) => $pai->html(), $this->pais))
        );
    }
}
