<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Hd;

use Amashukov\BlockchainContextBundle\Service\Hd\TonHdDeriver;
use Amashukov\TonCrypto\KeyPair;
use Amashukov\TonWallet\WalletV4R2;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TonHdDeriver::class)]
final class TonHdDeriverTest extends TestCase
{
    private const string PHRASE = 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about';

    /**
     * @return iterable<string, array{int, int, string, string}>
     */
    public static function referenceWallets(): iterable
    {
        yield "m/44'/607'/0'/0'/0'" => [0, 0, '7a839ff7ae46640a614bd53e6650227f1c1366b05abdb652cabed30f611b8628', 'UQDxAUFadQXDd3EXGa3TLF_EF66gMc9h3_aZ0j0zXNoIYUCc'];
        yield "m/44'/607'/0'/0'/1'" => [0, 1, '16af231d953e9e121d22d7aa4fdb179421970518fb67fcafc17eb22447c0dd89', 'UQBHJG1XpSPMRBaS6Ae-xcufra2eXnAKQadJaEPXfTi8xVWR'];
        yield "m/44'/607'/1'/0'/0'" => [1, 0, '61beca1394399579f01ad156f7a72f38e5bc2647057ff166d277249b7cd43fff', 'UQA8R_Q1gL2Fk8VBy74c4LisEm2YWJLJwPdxVwuUsuQ4XYLB'];
        yield "m/44'/607'/2'/0'/0'" => [2, 0, 'fce54ada77f41dfa010413d93f7568b622228f6890d6b7194bcdb1b83a6d11d0', 'UQDtut231oPlXKI04PbZN5GRFm79t0koqc5WV-U5Q7D7JgTD'];
    }

    #[DataProvider('referenceWallets')]
    public function testDerivesTheSameWalletV4AsTheTonSdk(int $account, int $index, string $privateKey, string $address): void
    {
        $wallet = (new TonHdDeriver(self::PHRASE, $account))->derive($index);

        self::assertSame(TonHdDeriver::CHAIN, $wallet->chain);
        self::assertSame($index, $wallet->derivationIndex);
        self::assertSame($privateKey, bin2hex($wallet->privKey));
        self::assertSame($address, $wallet->address);
    }

    public function testTheWalletSignsWithTheDerivedKeyAndSitsAtTheDerivedAddress(): void
    {
        $deriver = new TonHdDeriver(self::PHRASE, 1);
        $derived = $deriver->derive(5);
        $wallet  = $deriver->wallet(5);

        self::assertSame(bin2hex(KeyPair::fromSeed($derived->privKey)->publicKey), bin2hex($wallet->keys->publicKey));
        self::assertSame($derived->address, $wallet->address()->toString());
    }

    public function testTheWalletIdAndWorkchainAreHonoured(): void
    {
        $wallet = (new TonHdDeriver(self::PHRASE, walletId: 42, workchain: -1))->wallet(0);

        self::assertSame(42, $wallet->walletId);
        self::assertSame(-1, $wallet->workchain);
        self::assertNotSame(WalletV4R2::DEFAULT_WALLET_ID, $wallet->walletId);
    }

    public function testRefusesAnIndexOutsideTheNonHardenedRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TonHdDeriver(self::PHRASE))->derive(-1);
    }
}
