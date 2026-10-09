<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Hd;

use Amashukov\BlockchainContextBundle\Service\DepositWalletDeriverInterface;
use Amashukov\BlockchainContextBundle\ValueObject\DerivedWallet;
use Amashukov\TonWallet\WalletV4R2;

final readonly class TonHdDeriver implements DepositWalletDeriverInterface
{
    public const string CHAIN = 'ton';

    private Ed25519HdKey $accountKey;

    public function __construct(
        string $mnemonic,
        int $account = 0,
        string $passphrase = '',
        private int $walletId = WalletV4R2::DEFAULT_WALLET_ID,
        private int $workchain = 0,
    ) {
        HdPath::assertIndex($account);
        $this->accountKey = Ed25519HdKey::fromSeed(Bip39Seed::fromMnemonic($mnemonic, $passphrase))
            ->derivePath(sprintf("m/44'/607'/%d'/0'", $account));
    }

    public function derive(int $index): DerivedWallet
    {
        $key = $this->key($index);

        return new DerivedWallet(
            chain: self::CHAIN,
            derivationIndex: $index,
            address: $this->walletFor($key)->address()->toString(),
            privKey: $key->privateKey,
        );
    }

    public function wallet(int $index): WalletV4R2
    {
        return $this->walletFor($this->key($index));
    }

    private function key(int $index): Ed25519HdKey
    {
        HdPath::assertIndex($index);

        return $this->accountKey->derive($index | HdPath::HARDENED);
    }

    private function walletFor(Ed25519HdKey $key): WalletV4R2
    {
        return new WalletV4R2($key->keyPair(), $this->walletId, $this->workchain);
    }
}
