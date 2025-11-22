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

    public OpenFrom|null $from = null;

    public function html(): string
    {
        return sprintf(
            '<div class="open %s %s">%s</div>',
            $this->type->value,
            $this->from?->value,
            join('', array_map(fn (Pai $pai) => $pai->html(), $this->pais))
        );
    }
}
