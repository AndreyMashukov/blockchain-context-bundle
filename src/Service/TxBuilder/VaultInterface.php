<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

interface VaultInterface
{
    public function getAddress(): string;
}
