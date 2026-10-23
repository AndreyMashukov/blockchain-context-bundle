<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Bitcoin;

use Amashukov\BchnRpc\BchnRpcClient;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinAmount;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinCashNodeRpcClient;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinOutpoint;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinRpcException;
use Amashukov\BlockchainContextBundle\Tests\Helper\RecordingHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class BitcoinCashNodeRpcClientTest extends TestCase
{
    private const string TXID_1 = '0000000000000000000000000000000000000000000000000000000000000001';

    private const string TXID_2 = '0000000000000000000000000000000000000000000000000000000000000002';

    private const string BLOCK_HASH = '000000000000000000000000000000000000000000000000000000000000000a';

    private const string ADDRESS = 'bchreg:qzqurn6du0lhs8sr37ug8ns73ckqk39dlsnmh7nq8v';

    private const string TOKEN_CATEGORY = '00000000000000000000000000000000000000000000000000000000000000c1';

    private RecordingHttpClient $http;

    public function testDecodeRawTransactionHasTheCoreShape(): void
    {
        $decoded = $this->client('{"result":{"txid":"' . self::TXID_2 . '","hash":"' . self::TXID_2 . '","version":2,"size":201,"locktime":0,"vin":[{"txid":"' . self::TXID_1 . '","vout":0,"scriptSig":{"asm":"","hex":""},"sequence":4294967295}],"vout":[{"value":1.00000000,"n":0,"scriptPubKey":{"asm":"OP_DUP OP_HASH160 c1 OP_EQUALVERIFY OP_CHECKSIG","hex":"76a914c188ac","reqSigs":1,"type":"pubkeyhash","addresses":["' . self::ADDRESS . '"]}},{"value":0.00000000,"n":1,"scriptPubKey":{"asm":"OP_RETURN 68656c6c6f","hex":"6a0568656c6c6f","type":"nulldata"}}]},"error":null,"id":"decoderawtransaction"}')
            ->decodeRawTransaction('0200');

        self::assertSame(self::TXID_2, $decoded['txid']);
        self::assertSame('201', $decoded['size']);
        self::assertSame('201', $decoded['vsize']);
        self::assertSame('804', $decoded['weight']);
        self::assertSame([['txid' => self::TXID_1, 'vout' => '0', 'sequence' => '4294967295']], $decoded['vin']);
        $outputs = $decoded['vout'];
        self::assertIsArray($outputs);
        self::assertSame(['value' => '1.00000000', 'n' => '0', 'scriptPubKey' => ['asm' => 'OP_DUP OP_HASH160 c1 OP_EQUALVERIFY OP_CHECKSIG', 'hex' => '76a914c188ac', 'type' => 'pubkeyhash', 'address' => self::ADDRESS]], $outputs[0]);
        self::assertSame(['value' => '0.00000000', 'n' => '1', 'scriptPubKey' => ['asm' => 'OP_RETURN 68656c6c6f', 'hex' => '6a0568656c6c6f', 'type' => 'nulldata']], $outputs[1]);
    }

    public function testGetRawTransactionMapsPrevoutsAndFee(): void
    {
        $transaction = $this->client('{"result":{"txid":"' . self::TXID_2 . '","hash":"' . self::TXID_2 . '","version":2,"size":219,"locktime":0,"vin":[{"txid":"' . self::TXID_1 . '","vout":0,"scriptSig":{"asm":"","hex":""},"value":1.50000000,"scriptPubKey":{"asm":"","hex":"76a914aa88ac","type":"pubkeyhash","address":"' . self::ADDRESS . '"},"sequence":4294967295}],"vout":[{"value":1.49990000,"n":0,"scriptPubKey":{"asm":"","hex":"76a914bb88ac","type":"pubkeyhash","addresses":["' . self::ADDRESS . '"]}}],"fee":0.00010000,"blockhash":"' . self::BLOCK_HASH . '","confirmations":1,"time":1760000000,"blocktime":1760000000},"error":null,"id":"getrawtransaction"}')
            ->getRawTransaction(self::TXID_2, self::BLOCK_HASH);

        self::assertSame('150000000', $transaction->inputs[0]->amount?->satoshis);
        self::assertSame(self::ADDRESS, $transaction->inputs[0]->address);
        self::assertSame('149990000', $transaction->outputs[0]->amount->satoshis);
        self::assertSame('10000', $transaction->fee?->satoshis);
        self::assertSame(self::BLOCK_HASH, $transaction->blockHash);
    }

    public function testGetRawTransactionRefusesAnotherTransaction(): void
    {
        $this->expectException(BitcoinRpcException::class);

        $this->client('{"result":{"txid":"' . self::TXID_1 . '","hash":"' . self::TXID_1 . '","version":2,"size":1,"locktime":0,"vin":[],"vout":[]},"error":null,"id":"getrawtransaction"}')
            ->getRawTransaction(self::TXID_2);
    }

    public function testListUnspentAsksForTokensAndMapsTokenData(): void
    {
        $utxos = $this->client('{"result":[{"txid":"' . self::TXID_1 . '","vout":0,"address":"' . self::ADDRESS . '","label":"tok","scriptPubKey":"76a914aa88ac","amount":0.00001000,"tokenData":{"category":"' . self::TOKEN_CATEGORY . '","amount":"1000"},"confirmations":1,"spendable":true,"solvable":true,"safe":true}],"error":null,"id":"listunspent"}')
            ->listUnspent(0, 9999999, [], true);

        self::assertSame([0, 9999999, [], true, ['includeTokens' => true]], $this->sentParams());
        self::assertSame('1000', $utxos[0]->amount->satoshis);
        $token = $utxos[0]->tokenData;
        self::assertNotNull($token);
        self::assertSame(self::TOKEN_CATEGORY, $token->category);
        self::assertSame('1000', $token->amount);
        self::assertNull($token->nftCapability);
    }

    public function testWalletProcessPsbtSignsWithForkId(): void
    {
        $processed = $this->client('{"result":{"psbt":"cHNidP8=","complete":true},"error":null,"id":"walletprocesspsbt"}')->walletProcessPsbt('cHNidP8=');

        self::assertTrue($processed->complete);
        self::assertSame(['cHNidP8=', true, 'ALL|FORKID', false], $this->sentParams());
    }

    public function testCreatePsbtSendsExplicitInputsPaymentAndData(): void
    {
        $psbt = $this->client('{"result":"cHNidP8=","error":null,"id":"createpsbt"}')
            ->createPsbt([new BitcoinOutpoint(self::TXID_1, 0)], [[self::ADDRESS => '1.00000000'], ['data' => '68656c6c6f']]);

        self::assertSame('cHNidP8=', $psbt);
        self::assertSame([[['txid' => self::TXID_1, 'vout' => 0]], [[self::ADDRESS => '1.00000000'], ['data' => '68656c6c6f']], 0], $this->sentParams());
    }

    public function testLockUnspentHasNoPersistentFlag(): void
    {
        self::assertTrue($this->client('{"result":true,"error":null,"id":"lockunspent"}')->lockUnspent(false, [new BitcoinOutpoint(self::TXID_1, 0)]));
        self::assertSame([false, [['txid' => self::TXID_1, 'vout' => 0]]], $this->sentParams());
    }

    public function testEstimateSmartFeeReadsEstimateFee(): void
    {
        $estimate = $this->client('{"result":0.00001000,"error":null,"id":"estimatefee"}')->estimateSmartFee(6);

        self::assertSame(BitcoinAmount::fromSatoshis('1000')->decimal(), $estimate->btcPerKvB);
        self::assertSame(6, $estimate->blocks);
        self::assertSame([], $estimate->errors);
    }

    public function testWalletInfoCarriesTheHdSeed(): void
    {
        $wallet = $this->client('{"result":{"walletname":"hot-wallet","walletversion":160300,"balance":1.50000000,"unconfirmed_balance":0.00000000,"immature_balance":0.00000000,"txcount":2,"keypoololdest":1760000000,"keypoolsize":1000,"keypoolsize_hd_internal":1000,"paytxfee":0.00000000,"hdseedid":"fd374469a7bd4dc91037b79f683d98a64088cb2c","private_keys_enabled":true},"error":null,"id":"getwalletinfo"}')
            ->getWalletInfo();

        self::assertSame('150000000', $wallet->balance->satoshis);
        self::assertSame('fd374469a7bd4dc91037b79f683d98a64088cb2c', $wallet->hdSeedId);
        self::assertFalse($wallet->descriptors);
    }

    public function testValidateAddressIsInvalidOnTheNodeAddressError(): void
    {
        $info = $this->client('{"result":null,"error":{"code":-5,"message":"Invalid address"},"id":"getaddressinfo"}')->validateAddress('nope');

        self::assertFalse($info->isValid);
        self::assertFalse($info->isMine);
    }

    public function testGetMempoolEntryIsNullOutsideTheMempool(): void
    {
        self::assertNull($this->client('{"result":null,"error":{"code":-5,"message":"Transaction not in mempool"},"id":"getmempoolentry"}')->getMempoolEntry(self::TXID_1));
    }

    public function testGetMempoolEntryHasTheCoreShape(): void
    {
        $entry = $this->client('{"result":{"fees":{"base":0.00000219,"modified":0.00000219},"size":219,"time":1760000100,"depends":[],"spentby":[]},"error":null,"id":"getmempoolentry"}')->getMempoolEntry(self::TXID_1);

        self::assertSame(['size' => '219', 'vsize' => '219', 'time' => '1760000100', 'fees' => ['base' => '0.00000219']], $entry);
    }

    public function testNodeErrorKeepsItsCode(): void
    {
        try {
            $this->client('{"result":null,"error":{"code":-26,"message":"min relay fee not met"},"id":"sendrawtransaction"}')->sendRawTransaction('0200');
            self::fail('Expected BitcoinRpcException.');
        } catch (BitcoinRpcException $exception) {
            self::assertSame(-26, $exception->rpcCode);
        }
        self::assertSame(['0200', false], $this->sentParams());
    }

    public function testFeeBumpingIsRefused(): void
    {
        $this->expectException(BitcoinRpcException::class);

        $this->client('{"result":null,"error":null,"id":"x"}')->psbtBumpFee(self::TXID_1);
    }

    public function testFundedPsbtIsRefused(): void
    {
        $this->expectException(BitcoinRpcException::class);

        $this->client('{"result":null,"error":null,"id":"x"}')->walletCreateFundedPsbt([], [], []);
    }

    private function client(string $body): BitcoinCashNodeRpcClient
    {
        $factory    = new Psr17Factory();
        $this->http = new RecordingHttpClient($body);

        return new BitcoinCashNodeRpcClient(new BchnRpcClient($this->http, $factory, $factory, 'http://node:18443/wallet/hot-wallet', 'rpc-user', 'rpc-password'));
    }

    private function sentParams(): mixed
    {
        $payload = json_decode((string) $this->http->request?->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        return is_array($payload) ? $payload['params'] ?? null : null;
    }
}
