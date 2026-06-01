<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

interface GasEstimatorInterface
{
    /**
     * @param array<string, mixed> $tx EVM tx shape {to, data, value, chainId}
     *
     * @return null|numeric-string hex gas limit, or null when unestimable (caller falls back)
     */
    public function estimateForTx(array $tx, string $from): ?string;
}
