<?php

declare(strict_types=1);

namespace App;

class LogicAgent implements Agent
{
    public function decideDiscard(Game $game): Action
    {
        $me = $game->currentPlayer();

        $hand = $me->hand;
        if ($me->drawing) {
            $hand[] = $me->drawing;
            usort($hand, fn (Pai $a, Pai $b) => $a->value <=> $b->value);
        }

        ($pai = $this->findSingleHonour($hand))
        || ($pai = $this->findSingleEdge($hand))
        || ($pai = $hand[0]);

        return new Action([
            'command' => Action::DISCARD,
            'target' => $pai->letter()
        ]);
    }

    /**
     * 孤立した字牌を探す
     *
     * @param Pai[] $hand
     */
    private function findSingleHonour(array $hand): Pai|null
    {
        $counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $hand));
        foreach(['Z1', 'Z2', 'Z3', 'Z4', 'Z5', 'Z6', 'Z7'] as $value) {
            if (isset($counts[$value]) && $counts[$value] === 1) {
                return Pai::from($value);
            }
        }

        return null;
    }

    /**
     * 孤立した端牌を探す
     * @param Pai[] $hand
     */
    private function findSingleEdge(array $hand): Pai|null
    {
        $counts = array_count_values(array_map(fn (Pai $pai) => $pai->value, $hand));

        $checker = [
            'M1' => 'M2',
            'M9' => 'M8',
            'P1' => 'P2',
            'P9' => 'P8',
            'S1' => 'S2',
            'S9' => 'S8',
            'M2' => 'M3',
            'M8' => 'M7',
            'P2' => 'P3',
            'P8' => 'P7',
            'S2' => 'S3',
            'S8' => 'S7',
        ];

        foreach ($checker as $value => $target) {
            if (isset($counts[$value]) && $counts[$value] === 1) {
                if (!isset($counts[$target])) {
                    return Pai::from($value);
                }
            }
        }

        return null;
    }

    public function decideCall(Game $game, int $my_player_index): Action
    {
        if ($game->canRon($my_player_index)) {
            return new Action(['command' => Action::RON, 'player' => $my_player_index]);
        } elseif ($game->canPon($my_player_index)) {
            return new Action(['command' => Action::PON, 'player' => $my_player_index]);
        }

        return new Action(['command' => Action::SKIP]);
    }
}
