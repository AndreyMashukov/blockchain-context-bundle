<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Hd;

use Amashukov\BlockchainContextBundle\Service\DepositWalletDeriverInterface;
use Amashukov\BlockchainContextBundle\ValueObject\DerivedWallet;
use Amashukov\Keccak\Keccak;

final readonly class EvmHdDeriver implements DepositWalletDeriverInterface
{
    public const string CHAIN = 'evm';

    private Secp256k1HdKey $externalChain;

    public function __construct(string $mnemonic, int $account = 0, string $passphrase = '')
    {
        HdPath::assertIndex($account);
        $this->externalChain = Secp256k1HdKey::fromSeed(Bip39Seed::fromMnemonic($mnemonic, $passphrase))
            ->derivePath(sprintf("m/44'/60'/%d'/0", $account));
    }

    public function derive(int $index): DerivedWallet
    {
        HdPath::assertIndex($index);
        $key = $this->externalChain->derive($index);

        return new DerivedWallet(
            chain: self::CHAIN,
            derivationIndex: $index,
            address: '0x' . substr(Keccak::hash($key->uncompressedPublicKey(), 256), -40),
            privKey: $key->privateKey,
        );
    }
}
