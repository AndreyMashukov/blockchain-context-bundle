<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Time;

interface SleeperInterface
{
    public function sleep(int $seconds): void;
}
