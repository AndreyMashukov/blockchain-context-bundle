<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

use DateTimeInterface;

interface DepositTxView
{
    public function getBlockNumber(): ?string;

    public function getCreatedAt(): DateTimeInterface;

    public function getConfirmationsRequired(): ?int;

    public function getMcSeqno(): ?int;
}
