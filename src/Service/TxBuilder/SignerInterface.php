<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

interface SignerInterface
{
    public function getAddress(): string;
}
