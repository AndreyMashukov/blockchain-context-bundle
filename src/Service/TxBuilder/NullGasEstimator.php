<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

final readonly class NullGasEstimator implements GasEstimatorInterface
{
    public function estimateForTx(array $tx, string $from): ?string
    {
        return null;
    }
}
