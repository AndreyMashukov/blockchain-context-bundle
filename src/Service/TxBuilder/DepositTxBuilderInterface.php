<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

interface DepositTxBuilderInterface
{
    public function supports(string $chain): bool;

    /**
     * @param array<string, string> $context
     */
    public function build(DepositTxOrderView $order, array $context = []): DepositTxPayload;

    /**
     * @param array<string, string> $context
     */
    public function nextStep(DepositTxOrderView $order, array $context = []): DepositTxStep;
}
