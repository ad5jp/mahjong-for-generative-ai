<?php

declare(strict_types=1);

namespace App;

enum OpenFrom: string
{
    case LEFT = 'LEFT';
    case ACROSS = 'ACROSS';
    case RIGHT = 'RIGHT';
}
