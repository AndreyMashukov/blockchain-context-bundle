<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service;

use Amashukov\BlockchainContextBundle\ValueObject\DerivedWallet;
use InvalidArgumentException;
use RuntimeException;

interface DepositWalletDeriverInterface
{
    /**
     * Derive the wallet at the given index from the configured master mnemonic.
     *
     * @param int $index 0-based derivation index
     *
     * @throws InvalidArgumentException if the index is out of the allowed range
     * @throws RuntimeException         if derivation fails (e.g. invalid mnemonic)
     */
    public function derive(int $index): DerivedWallet;
}
