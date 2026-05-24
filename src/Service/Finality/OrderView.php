<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

use DateTimeInterface;

interface OrderView
{
    public function getCreatedAt(): DateTimeInterface;

    public function getFromChain(): ?string;

    public function getOrderId(): ?string;

    public function getIncomingTxHash(): ?string;
}
