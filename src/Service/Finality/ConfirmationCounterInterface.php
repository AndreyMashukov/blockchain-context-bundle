<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

interface ConfirmationCounterInterface
{
    public function supports(string $chain): bool;

    public function count(DepositTxView $depositTx, OrderView $order): int;
}
