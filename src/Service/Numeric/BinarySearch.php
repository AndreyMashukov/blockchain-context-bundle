<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Numeric;

final readonly class BinarySearch
{
    public function search(int $from, int $to, int $step, callable $search): int
    {
        $left  = $from;
        $right = $to;
        $best  = 0;

        while ($left <= $right) {
            $mid    = (int) round(($left + $right) / 2, 0, PHP_ROUND_HALF_DOWN);
            $result = $search($mid);
            if ($result) {
                $left = (int) ceil($mid + $step);
                $best = $mid;
            } else {
                $right = $mid - $step;
            }
        }

        return $best;
    }
}
