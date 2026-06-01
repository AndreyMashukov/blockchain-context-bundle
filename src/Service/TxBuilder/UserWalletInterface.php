<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

interface UserWalletInterface
{
    public function userAddress(): ?string;

    public function userJettonWallet(): ?string;
}
