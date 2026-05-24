<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Time;

final readonly class RealSleeper implements SleeperInterface
{
    public function sleep(int $seconds): void
    {
        \sleep($seconds);
    }
}
