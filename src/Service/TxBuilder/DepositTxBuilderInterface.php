<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

interface DepositTxBuilderInterface
{
    public function supports(string $chain): bool;

    public function build(DepositTxOrderView $order): DepositTxPayload;

    public function nextStep(DepositTxOrderView $order, SignerInterface $signer): DepositTxStep;
}
