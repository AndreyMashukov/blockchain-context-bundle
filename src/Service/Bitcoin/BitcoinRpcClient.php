<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

final readonly class BitcoinRpcClient implements BitcoinRpcClientInterface
{
    public function __construct(
        private ClientInterface $http,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private string $rpcUrl,
        private string $rpcUser,
        private string $rpcPassword,
        private BitcoinJsonDecoder $decoder,
    ) {}

    public function getBlockchainInfo(): BitcoinBlockchainInfo
    {
        return BitcoinBlockchainInfo::fromArray($this->objectResult('getblockchaininfo'));
    }

    public function getWalletInfo(): BitcoinWalletInfo
    {
        return BitcoinWalletInfo::fromArray($this->objectResult('getwalletinfo'));
    }

    public function createWallet(string $name, string $passphrase = '', bool $avoidReuse = true): void
    {
        $this->call('createwallet', [$name, false, false, $passphrase, $avoidReuse, true]);
    }

    public function loadWallet(string $name): void
    {
        $this->call('loadwallet', [$name]);
    }

    public function getNewAddress(string $label = '', string $addressType = 'bech32'): string
    {
        return $this->stringResult('getnewaddress', [$label, $addressType]);
    }

    public function getAddressInfo(string $address): BitcoinAddressInfo
    {
        return BitcoinAddressInfo::fromArray($this->objectResult('getaddressinfo', [$address]));
    }

    public function validateAddress(string $address): BitcoinAddressInfo
    {
        return BitcoinAddressInfo::fromArray($this->objectResult('validateaddress', [$address]));
    }

    public function listSinceBlock(?string $blockHash, int $targetConfirmations = 1): BitcoinListSinceBlock
    {
        return BitcoinListSinceBlock::fromArray($this->objectResult('listsinceblock', [$blockHash, $targetConfirmations, false, true, false]));
    }

    public function listUnspent(int $minimumConfirmations = 1, int $maximumConfirmations = 9999999, array $addresses = []): array
    {
        $result = $this->listResult('listunspent', [$minimumConfirmations, $maximumConfirmations, $addresses, true]);
        $utxos  = [];
        foreach ($result as $row) {
            if (!is_array($row)) {
                throw new BitcoinRpcException('Bitcoin Core listunspent result contains an invalid entry.');
            }
            $utxos[] = BitcoinUnspentOutput::fromArray(BitcoinRpcValue::map($row, 'listunspent entry'));
        }

        return $utxos;
    }

    public function listLockUnspent(): array
    {
        $outpoints = [];
        foreach ($this->listResult('listlockunspent') as $row) {
            if (!is_array($row)) {
                throw new BitcoinRpcException('Bitcoin Core listlockunspent result contains an invalid entry.');
            }
            $outpoints[] = BitcoinOutpoint::fromArray(BitcoinRpcValue::map($row, 'listlockunspent entry'));
        }

        return $outpoints;
    }

    public function lockUnspent(bool $unlock, array $outpoints, bool $persistent = true): bool
    {
        $result = $this->call('lockunspent', [$unlock, array_map(static fn(BitcoinOutpoint $outpoint): array => $outpoint->toArray(), $outpoints), $persistent]);
        if (!is_bool($result)) {
            throw new BitcoinRpcException('Bitcoin Core lockunspent result must be a boolean.');
        }

        return $result;
    }

    public function estimateSmartFee(int $confirmationTarget = 6, string $estimateMode = 'conservative'): BitcoinFeeEstimate
    {
        return BitcoinFeeEstimate::fromArray($this->objectResult('estimatesmartfee', [$confirmationTarget, $estimateMode]));
    }

    public function walletCreateFundedPsbt(array $inputs, array $outputs, array $options): BitcoinFundedPsbt
    {
        $inputRows = array_map(static fn(BitcoinOutpoint $outpoint): array => $outpoint->toArray(), $inputs);

        return BitcoinFundedPsbt::fromArray($this->objectResult('walletcreatefundedpsbt', [$inputRows, $outputs, 0, $options, true]));
    }

    public function walletProcessPsbt(string $psbt, bool $sign = true): BitcoinProcessedPsbt
    {
        return BitcoinProcessedPsbt::fromArray($this->objectResult('walletprocesspsbt', [$psbt, $sign, 'ALL', true]));
    }

    public function finalizePsbt(string $psbt, bool $extract = true): BitcoinFinalizedPsbt
    {
        return BitcoinFinalizedPsbt::fromArray($this->objectResult('finalizepsbt', [$psbt, $extract]));
    }

    public function decodePsbt(string $psbt): array
    {
        return $this->objectResult('decodepsbt', [$psbt]);
    }

    public function decodeRawTransaction(string $hex): array
    {
        return $this->objectResult('decoderawtransaction', [$hex]);
    }

    public function testMempoolAccept(string $hex, string $maximumFeeRate = '0.10'): BitcoinMempoolAcceptance
    {
        $rows = $this->listResult('testmempoolaccept', [[$hex], $maximumFeeRate]);
        $row  = $rows[0] ?? null;
        if (!is_array($row)) {
            throw new BitcoinRpcException('Bitcoin Core testmempoolaccept returned no result.');
        }

        return BitcoinMempoolAcceptance::fromArray(BitcoinRpcValue::map($row, 'testmempoolaccept entry'));
    }

    public function sendRawTransaction(string $hex, string $maximumFeeRate = '0.10'): string
    {
        return $this->stringResult('sendrawtransaction', [$hex, $maximumFeeRate]);
    }

    public function getTransaction(string $txid): BitcoinWalletTransaction
    {
        return BitcoinWalletTransaction::fromArray($this->objectResult('gettransaction', [$txid, true, true]));
    }

    public function getMempoolEntry(string $txid): ?array
    {
        try {
            return $this->objectResult('getmempoolentry', [$txid]);
        } catch (BitcoinRpcException $exception) {
            if (-5 === $exception->rpcCode) {
                return null;
            }

            throw $exception;
        }
    }

    public function psbtBumpFee(string $txid, array $options = []): BitcoinBumpFeeResult
    {
        return BitcoinBumpFeeResult::fromArray($this->objectResult('psbtbumpfee', [$txid, $options]));
    }

    public function walletPassphrase(string $passphrase, int $timeoutSeconds): void
    {
        $this->call('walletpassphrase', [$passphrase, $timeoutSeconds]);
    }

    public function walletLock(): void
    {
        $this->call('walletlock');
    }

    public function backupWallet(string $destination): void
    {
        $this->call('backupwallet', [$destination]);
    }

    /**
     * @param list<mixed> $params
     */
    private function stringResult(string $method, array $params = []): string
    {
        $result = $this->call($method, $params);
        if (!is_string($result)) {
            throw new BitcoinRpcException(sprintf('Bitcoin Core method "%s" must return a string.', $method));
        }

        return $result;
    }

    /**
     * @param list<mixed> $params
     *
     * @return array<string, mixed>
     */
    private function objectResult(string $method, array $params = []): array
    {
        $result = $this->call($method, $params);
        if (!is_array($result)) {
            throw new BitcoinRpcException(sprintf('Bitcoin Core method "%s" must return an object.', $method));
        }

        return BitcoinRpcValue::map($result, $method . ' result');
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<mixed>
     */
    private function listResult(string $method, array $params = []): array
    {
        $result = $this->call($method, $params);
        if (!is_array($result) || !array_is_list($result)) {
            throw new BitcoinRpcException(sprintf('Bitcoin Core method "%s" must return a list.', $method));
        }

        return $result;
    }

    /**
     * @param list<mixed> $params
     */
    private function call(string $method, array $params = []): mixed
    {
        if ('' === $this->rpcUrl) {
            throw new BitcoinRpcException('Bitcoin Core RPC URL is not configured.');
        }

        try {
            $payload = json_encode(['jsonrpc' => '2.0', 'id' => 'bitcoin-core', 'method' => $method, 'params' => $params], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $request = $this->requestFactory->createRequest('POST', $this->rpcUrl)
                ->withHeader('Accept', 'application/json')
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Authorization', 'Basic ' . base64_encode($this->rpcUser . ':' . $this->rpcPassword))
                ->withBody($this->streamFactory->createStream($payload));
            $response = $this->http->sendRequest($request);
        } catch (JsonException $exception) {
            throw new BitcoinRpcException('Unable to encode Bitcoin Core request: ' . $exception->getMessage());
        } catch (Throwable $exception) {
            throw new BitcoinRpcException('Bitcoin Core request failed: ' . $exception->getMessage());
        }

        $envelope = $this->decoder->decode((string) $response->getBody());
        $error    = $envelope['error'] ?? null;
        if (is_array($error)) {
            $code    = isset($error['code']) && is_string($error['code']) ? (int) $error['code'] : null;
            $message = isset($error['message']) && is_string($error['message']) ? $error['message'] : 'Unknown Bitcoin Core RPC error.';
            throw new BitcoinRpcException($message, $code);
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new BitcoinRpcException(sprintf('Bitcoin Core returned HTTP %d.', $response->getStatusCode()));
        }
        if (!array_key_exists('result', $envelope)) {
            throw new BitcoinRpcException('Bitcoin Core response has no result field.');
        }

        return $envelope['result'];
    }
}
