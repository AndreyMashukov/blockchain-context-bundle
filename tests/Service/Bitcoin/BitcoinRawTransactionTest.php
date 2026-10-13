<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Bitcoin;

use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinJsonDecoder;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinRawTransaction;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinRpcClient;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinRpcException;
use Amashukov\BlockchainContextBundle\Tests\Helper\RecordingHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BitcoinRawTransactionTest extends TestCase
{
    public function testCompletePreviousOutputsProveTheExactFeeAndAddresses(): void
    {
        $tx = BitcoinRawTransaction::fromArray($this->row());
        self::assertSame('2', $tx->fee?->satoshis);
        self::assertSame('sender', $tx->inputs[0]->address);
        self::assertSame('100000003', $tx->inputs[0]->amount?->satoshis);
        self::assertSame('recipient', $tx->outputs[0]->address);
        self::assertSame('100000001', $tx->outputs[0]->amount->satoshis);
        self::assertTrue($tx->inActiveChain);
    }

    public function testMissingPreviousOutputIsUnknownEvenWhenFeeIsReported(): void
    {
        $row = $this->row();
        unset($row['vin'][0]['prevout']);
        $tx = BitcoinRawTransaction::fromArray($row);
        self::assertNull($tx->fee);
        self::assertNull($tx->inputs[0]->amount);
        self::assertNull($tx->inputs[0]->address);
    }

    public function testExactZeroIsDifferentFromUnavailableFee(): void
    {
        $row = $this->row();
        $row['vin'][0]['prevout']['value'] = '1.00000001';
        $row['fee'] = '0';
        self::assertSame('0', BitcoinRawTransaction::fromArray($row)->fee?->satoshis);
    }

    public function testCompleteValuesCanProveTheFeeWithoutTheOptionalFeeField(): void
    {
        $row = $this->row();
        unset($row['fee']);
        self::assertSame('2', BitcoinRawTransaction::fromArray($row)->fee?->satoshis);
    }

    public function testAnInconsistentReportedFeeIsRejected(): void
    {
        $row = $this->row();
        $row['fee'] = '0.01';
        $this->expectException(BitcoinRpcException::class);
        BitcoinRawTransaction::fromArray($row);
    }

    public function testVerbosityTwoAndExactBlockAreRequestedWithoutRequiringAnIndex(): void
    {
        $http = new RecordingHttpClient(json_encode(['result' => $this->row(), 'error' => null], JSON_THROW_ON_ERROR));
        $factory = new Psr17Factory();
        $client = new BitcoinRpcClient($http, $factory, $factory, 'http://core/wallet/custody', '', '', new BitcoinJsonDecoder());
        $tx = $client->getRawTransaction('tx', 'block');
        self::assertSame('2', $tx->fee?->satoshis);
        self::assertStringContainsString('"method":"getrawtransaction"', (string) $http->request?->getBody());
        self::assertStringContainsString('"params":["tx",2,"block"]', (string) $http->request?->getBody());
    }

    #[DataProvider('mismatches')]
    public function testWrongTransactionOrBlockCannotBecomeEvidence(string $txid, string $block): void
    {
        $http = new RecordingHttpClient(json_encode(['result' => $this->row(), 'error' => null], JSON_THROW_ON_ERROR));
        $factory = new Psr17Factory();
        $client = new BitcoinRpcClient($http, $factory, $factory, 'http://core', '', '', new BitcoinJsonDecoder());
        $this->expectException(BitcoinRpcException::class);
        $client->getRawTransaction($txid, $block);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mismatches(): iterable
    {
        yield 'wrong transaction' => ['another', 'block'];
        yield 'wrong block' => ['tx', 'another'];
    }

    /**
     * @return array{txid: string, blockhash: string, in_active_chain: bool, fee?: string, vin: list<array{txid: string, vout: string, prevout?: array{value: string, scriptPubKey: array{hex: string, address: string}}}>, vout: list<array{n: string, value: string, scriptPubKey: array{hex: string, address: string}}>}
     */
    private function row(): array
    {
        return ['txid' => 'tx', 'blockhash' => 'block', 'in_active_chain' => true, 'fee' => '0.00000002',
            'vin' => [['txid' => 'previous', 'vout' => '0', 'prevout' => ['value' => '1.00000003', 'scriptPubKey' => ['hex' => '0014aa', 'address' => 'sender']]]],
            'vout' => [['n' => '0', 'value' => '1.00000001', 'scriptPubKey' => ['hex' => '0014bb', 'address' => 'recipient']]]];
    }
}
