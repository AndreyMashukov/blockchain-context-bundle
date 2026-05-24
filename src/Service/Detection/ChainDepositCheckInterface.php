<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Detection;

use Amashukov\BlockchainContextBundle\Service\Finality\OrderView;

interface ChainDepositCheckInterface
{
    public function supports(OrderView $order): bool;

    /**
     * Walk on-chain evidence for `$order`. Return `true` when an
     * incoming deposit was matched and the order's `incomingTxHash`
     * + `fromAddress` were stamped by the impl. The caller commits
     * the status / dispatch transition.
     */
    public function check(OrderView $order): bool;
}
