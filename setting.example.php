<?php

declare(strict_types=1);

return [
    'processor' => \App\Processor\AutoProcessor::class,
    'players' => [
        [
            'name' => 'Player 1',
            'agent' => \App\Agent\LogicAgent::class,
        ],
        [
            'name' => 'Player 2',
            'agent' => \App\Agent\LogicAgent::class,
        ],
        [
            'name' => 'Player 3',
            'agent' => \App\Agent\LogicAgent::class,
        ],
        [
            'name' => 'Player 4',
            'agent' => \App\Agent\LogicAgent::class,
        ],
    ]
];
