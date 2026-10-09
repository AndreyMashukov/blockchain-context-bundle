<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Hd;

use Amashukov\BlockchainContextBundle\Service\Hd\EvmHdDeriver;
use Amashukov\Eip1559TxSigner\Eip1559Signer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EvmHdDeriver::class)]
final class EvmHdDeriverTest extends TestCase
{
    private const string PHRASE = 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about';

    /**
     * @return iterable<string, array{int, int, string, string}>
     */
    public static function referenceWallets(): iterable
    {
        yield "m/44'/60'/0'/0/0" => [0, 0, '1ab42cc412b618bdea3a599e3c9bae199ebf030895b039e9db1e30dafb12b727', '0x9858effd232b4033e47d90003d41ec34ecaeda94'];
        yield "m/44'/60'/0'/0/1" => [0, 1, '9a983cb3d832fbde5ab49d692b7a8bf5b5d232479c99333d0fc8e1d21f1b55b6', '0x6fac4d18c912343bf86fa7049364dd4e424ab9c0'];
        yield "m/44'/60'/1'/0/0" => [1, 0, '318470c858f622e48a80120a1fc3c8460d67a7bf31b3273a6d27d4c013f2f8d3', '0x78839f6054d7ed13918bae0473ba31b1ca9d7265'];
        yield "m/44'/60'/2'/0/0" => [2, 0, '4b9aad43cd664b970876f6cf635fe1526819234dec28c4219fc4300a0312de8c', '0x07b5fdfeb4e11826d233403fe8db0611ccf4c231'];
    }

    #[DataProvider('referenceWallets')]
    public function testDerivesTheSameWalletAsEthers(int $account, int $index, string $privateKey, string $address): void
    {
        $wallet = (new EvmHdDeriver(self::PHRASE, $account))->derive($index);

        self::assertSame(EvmHdDeriver::CHAIN, $wallet->chain);
        self::assertSame($index, $wallet->derivationIndex);
        self::assertSame($privateKey, bin2hex($wallet->privKey));
        self::assertSame($address, $wallet->address);
    }

    public function testTheAddressBelongsToThePrivateKey(): void
    {
        $wallet = (new EvmHdDeriver(self::PHRASE, 3))->derive(41);

        self::assertSame($wallet->address, (new Eip1559Signer(bin2hex($wallet->privKey), 1))->address());
    }

    public function testAPassphraseYieldsADifferentWallet(): void
    {
        self::assertNotSame(
            (new EvmHdDeriver(self::PHRASE))->derive(0)->address,
            (new EvmHdDeriver(self::PHRASE, passphrase: 'extra'))->derive(0)->address,
        );
    }

    public function testRefusesAHardenedRangeIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new EvmHdDeriver(self::PHRASE))->derive(0x80000000);
    }

    public function testRefusesANegativeAccount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EvmHdDeriver(self::PHRASE, -1);
    }
}
