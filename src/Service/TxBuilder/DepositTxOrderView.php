<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

interface DepositTxOrderView
{
    public function getId(): ?int;

    public function getOrderId(): ?string;

    public function getFromChain(): ?string;

    public function getDepositAddress(): ?string;

    public function getFromAmount(): ?string;

    public function getDepositMemo(): ?string;
}
