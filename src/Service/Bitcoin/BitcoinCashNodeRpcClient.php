<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

use Amashukov\BchnRpc\Vo\BlockchainInfo;
use Amashukov\BchnRpc\Vo\WalletInfo;
use Amashukov\BchnRpc\Vo\WalletLoadResult;
use Amashukov\BchnRpc\Vo\AddressInfo;
use Amashukov\BchnRpc\Vo\ListSinceBlock;
use Amashukov\BchnRpc\Vo\ProcessedPsbt;
use Amashukov\BchnRpc\Vo\FinalizedPsbt;
use Amashukov\BchnRpc\Vo\DecodedPsbt;
use Amashukov\BchnRpc\Vo\MempoolAcceptance;
use Amashukov\BchnRpc\Vo\WalletTransaction;
use Amashukov\BchnRpc\Vo\TxOut;
use Amashukov\BchnRpc\Vo\MempoolEntry;
use Amashukov\BchnRpc\BchnRpcClientInterface;
use Amashukov\BchnRpc\BchnRpcException;
use Amashukov\BchnRpc\Vo\BchAmount;
use Amashukov\BchnRpc\Vo\Outpoint;
use Amashukov\BchnRpc\Vo\PsbtOutput;
use Amashukov\BchnRpc\Vo\ScriptPubKey;
use Amashukov\BchnRpc\Vo\TokenData;
use Amashukov\BchnRpc\Vo\Transaction;
use Amashukov\BchnRpc\Vo\WalletTransactionEntry;

final readonly class BitcoinCashNodeRpcClient implements BitcoinRpcClientInterface
{
    private const int RPC_INVALID_ADDRESS_OR_KEY = -5;

    private const int SATOSHIS_PER_BYTE_TO_PER_KILOBYTE = 1000;

    private const int WEIGHT_UNITS_PER_BYTE = 4;

    public function __construct(private BchnRpcClientInterface $node) {}

    public function getBlockchainInfo(): BitcoinBlockchainInfo
    {
        $info = $this->guard(fn(): BlockchainInfo => $this->node->getBlockchainInfo());

        return new BitcoinBlockchainInfo($info->chain, $info->blocks, $info->headers, $info->bestBlockHash, $info->initialBlockDownload, $info->pruned);
    }

    public function getWalletInfo(): BitcoinWalletInfo
    {
        $wallet = $this->guard(fn(): WalletInfo => $this->node->getWalletInfo());

        return new BitcoinWalletInfo(
            $wallet->walletName,
            $wallet->walletVersion,
            $this->amount($wallet->balance),
            $this->amount($wallet->unconfirmedBalance),
            $wallet->txCount,
            $wallet->privateKeysEnabled,
            false,
            $wallet->unlockedUntil,
            $wallet->hdSeedId,
        );
    }

    public function createWallet(string $name, string $passphrase = '', bool $avoidReuse = true): void
    {
        $this->guard(fn(): WalletLoadResult => $this->node->createWallet($name, false, false, $passphrase));
    }

    public function loadWallet(string $name): void
    {
        $this->guard(fn(): WalletLoadResult => $this->node->loadWallet($name));
    }

    public function getNewAddress(string $label = '', string $addressType = 'bech32'): string
    {
        return $this->guard(fn(): string => $this->node->getNewAddress($label));
    }

    public function getAddressInfo(string $address): BitcoinAddressInfo
    {
        $info = $this->guard(fn(): AddressInfo => $this->node->getAddressInfo($address));

        return new BitcoinAddressInfo(true, $info->isMine, $info->isWatchOnly, $info->scriptPubKey, null, $info->hdKeyPath);
    }

    public function validateAddress(string $address): BitcoinAddressInfo
    {
        try {
            return $this->getAddressInfo($address);
        } catch (BitcoinRpcException $exception) {
            if (self::RPC_INVALID_ADDRESS_OR_KEY === $exception->rpcCode) {
                return new BitcoinAddressInfo(false, false, false, null, null);
            }

            throw $exception;
        }
    }

    public function listSinceBlock(?string $blockHash, int $targetConfirmations = 1): BitcoinListSinceBlock
    {
        $result = $this->guard(fn(): ListSinceBlock => $this->node->listSinceBlock($blockHash, $targetConfirmations));

        return new BitcoinListSinceBlock(
            array_map($this->walletEntry(...), $result->transactions),
            array_map($this->walletEntry(...), $result->removed),
            $result->lastBlock,
        );
    }

    public function listUnspent(int $minimumConfirmations = 1, int $maximumConfirmations = 9999999, array $addresses = [], bool $includeTokens = false): array
    {
        $utxos = [];
        foreach ($this->guard(fn(): array => $this->node->listUnspent($minimumConfirmations, $maximumConfirmations, $addresses, true, $includeTokens)) as $utxo) {
            if (null === $utxo->address) {
                throw new BitcoinRpcException(sprintf('Bitcoin Cash Node listunspent entry %s:%d has no address.', $utxo->outpoint->txid, $utxo->outpoint->vout));
            }
            $utxos[] = new BitcoinUnspentOutput(
                new BitcoinOutpoint($utxo->outpoint->txid, $utxo->outpoint->vout),
                $utxo->address,
                $this->amount($utxo->amount),
                $utxo->confirmations,
                $utxo->spendable,
                $utxo->solvable,
                $utxo->safe,
                $this->token($utxo->tokenData),
            );
        }

        return $utxos;
    }

    public function listLockUnspent(): array
    {
        return array_map(static fn(Outpoint $outpoint): BitcoinOutpoint => new BitcoinOutpoint($outpoint->txid, $outpoint->vout), $this->guard(fn(): array => $this->node->listLockUnspent()));
    }

    public function lockUnspent(bool $unlock, array $outpoints, bool $persistent = true): bool
    {
        return $this->guard(fn(): bool => $this->node->lockUnspent($unlock, array_map(static fn(BitcoinOutpoint $outpoint): Outpoint => new Outpoint($outpoint->txid, $outpoint->vout), $outpoints)));
    }

    public function estimateSmartFee(int $confirmationTarget = 6, string $estimateMode = 'conservative'): BitcoinFeeEstimate
    {
        $satoshisPerByte = $this->guard(fn(): int => $this->node->estimateFee());

        return new BitcoinFeeEstimate(BitcoinAmount::fromSatoshis((string) ($satoshisPerByte * self::SATOSHIS_PER_BYTE_TO_PER_KILOBYTE))->decimal(), $confirmationTarget, []);
    }

    public function walletCreateFundedPsbt(array $inputs, array $outputs, array $options): BitcoinFundedPsbt
    {
        throw new BitcoinRpcException('Bitcoin Cash Node funds no PSBT for this client: build the transaction with createPsbt from explicit inputs and change.');
    }

    public function createPsbt(array $inputs, array $outputs): string
    {
        $psbtOutputs = [];
        foreach ($outputs as $output) {
            foreach ($output as $key => $value) {
                $psbtOutputs[] = 'data' === $key ? PsbtOutput::data($value) : PsbtOutput::payTo($key, BchAmount::fromDecimal($value));
            }
        }

        return $this->guard(fn(): string => $this->node->createPsbt(array_map(static fn(BitcoinOutpoint $outpoint): Outpoint => new Outpoint($outpoint->txid, $outpoint->vout), $inputs), $psbtOutputs));
    }

    public function walletProcessPsbt(string $psbt, bool $sign = true): BitcoinProcessedPsbt
    {
        $processed = $this->guard(fn(): ProcessedPsbt => $this->node->walletProcessPsbt($psbt, $sign));

        return new BitcoinProcessedPsbt($processed->psbt, $processed->complete);
    }

    public function finalizePsbt(string $psbt, bool $extract = true): BitcoinFinalizedPsbt
    {
        $finalized = $this->guard(fn(): FinalizedPsbt => $this->node->finalizePsbt($psbt, $extract));

        return new BitcoinFinalizedPsbt($finalized->hex, $finalized->complete);
    }

    public function decodePsbt(string $psbt): array
    {
        $decoded = $this->guard(fn(): DecodedPsbt => $this->node->decodePsbt($psbt));

        return ['tx' => $this->transactionArray($decoded->tx), 'fee' => $decoded->fee?->toDecimal()];
    }

    public function decodeRawTransaction(string $hex): array
    {
        return $this->transactionArray($this->guard(fn(): Transaction => $this->node->decodeRawTransaction($hex)));
    }

    public function testMempoolAccept(string $hex, string $maximumFeeRate = '0.10'): BitcoinMempoolAcceptance
    {
        $acceptance = $this->guard(fn(): MempoolAcceptance => $this->node->testMempoolAccept($hex));

        return new BitcoinMempoolAcceptance(
            $acceptance->txid,
            $acceptance->allowed,
            $acceptance->rejectReason,
            $acceptance->size,
            null === $acceptance->baseFee ? null : $this->amount($acceptance->baseFee),
        );
    }

    public function sendRawTransaction(string $hex, string $maximumFeeRate = '0.10'): string
    {
        return $this->guard(fn(): string => $this->node->sendRawTransaction($hex));
    }

    public function getTransaction(string $txid): BitcoinWalletTransaction
    {
        $transaction = $this->guard(fn(): WalletTransaction => $this->node->getTransaction($txid, true));

        return new BitcoinWalletTransaction(
            $transaction->txid,
            $this->amount($transaction->amount),
            null === $transaction->fee ? BitcoinAmount::fromSatoshis('0') : $this->amount($transaction->fee),
            $transaction->confirmations,
            $transaction->blockHash,
            $transaction->hex,
            null,
            null,
        );
    }

    public function getRawTransaction(string $txid, ?string $blockHash = null): BitcoinRawTransaction
    {
        $transaction = $this->guard(fn(): Transaction => $this->node->getRawTransaction($txid, $blockHash));
        if (strtolower($transaction->txid) !== strtolower($txid) || (null !== $blockHash && $transaction->blockHash !== $blockHash)) {
            throw new BitcoinRpcException('Bitcoin Cash Node returned another transaction or block for the requested fee evidence.');
        }

        $inputs = [];
        foreach ($transaction->inputs as $input) {
            $inputs[] = new BitcoinRawInput(
                null === $input->outpoint ? null : new BitcoinOutpoint($input->outpoint->txid, $input->outpoint->vout),
                null === $input->prevout ? null : $this->amount($input->prevout->value),
                $input->prevout?->scriptPubKey->address,
                $input->prevout?->scriptPubKey->hex,
            );
        }
        $outputs = [];
        foreach ($transaction->outputs as $output) {
            $outputs[] = new BitcoinRawOutput($output->n, $this->amount($output->value), $output->scriptPubKey->address, $output->scriptPubKey->hex);
        }
        $fee = $transaction->fee();

        return new BitcoinRawTransaction($transaction->txid, $transaction->blockHash, null, $inputs, $outputs, null === $fee ? null : $this->amount($fee));
    }

    public function getTxOut(string $txid, int $vout, bool $includeMempool = true): ?BitcoinTxOut
    {
        $output = $this->guard(fn(): ?TxOut => $this->node->getTxOut($txid, $vout, $includeMempool));
        if (null === $output) {
            return null;
        }

        return new BitcoinTxOut($output->confirmations, $this->amount($output->value), $output->scriptPubKey->address, $output->scriptPubKey->hex, $this->token($output->tokenData), $output->coinbase);
    }

    public function getMempoolEntry(string $txid): ?array
    {
        $entry = $this->guard(fn(): ?MempoolEntry => $this->node->getMempoolEntry($txid));

        return null === $entry ? null : ['size' => (string) $entry->size, 'vsize' => (string) $entry->size, 'time' => (string) $entry->time, 'fees' => ['base' => $entry->baseFee->toDecimal()]];
    }

    public function psbtBumpFee(string $txid, array $options = []): BitcoinBumpFeeResult
    {
        throw new BitcoinRpcException('Bitcoin Cash Node has no fee bumping: the network keeps the first-seen transaction.');
    }

    public function walletPassphrase(string $passphrase, int $timeoutSeconds): void
    {
        $this->guard(fn() => $this->node->walletPassphrase($passphrase, $timeoutSeconds));
    }

    public function walletLock(): void
    {
        $this->guard(fn() => $this->node->walletLock());
    }

    public function backupWallet(string $destination): void
    {
        $this->guard(fn() => $this->node->backupWallet($destination));
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionArray(Transaction $transaction): array
    {
        $inputs = [];
        foreach ($transaction->inputs as $input) {
            $inputs[] = null === $input->outpoint
                ? ['coinbase' => $input->coinbase, 'sequence' => (string) $input->sequence]
                : ['txid' => $input->outpoint->txid, 'vout' => (string) $input->outpoint->vout, 'sequence' => (string) $input->sequence];
        }
        $outputs = [];
        foreach ($transaction->outputs as $output) {
            $outputs[] = ['value' => $output->value->toDecimal(), 'n' => (string) $output->n, 'scriptPubKey' => $this->scriptArray($output->scriptPubKey)];
        }

        return [
            'txid'     => $transaction->txid,
            'hash'     => $transaction->hash,
            'version'  => (string) $transaction->version,
            'size'     => (string) $transaction->size,
            'vsize'    => (string) $transaction->size,
            'weight'   => (string) ($transaction->size * self::WEIGHT_UNITS_PER_BYTE),
            'locktime' => (string) $transaction->locktime,
            'vin'      => $inputs,
            'vout'     => $outputs,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function scriptArray(ScriptPubKey $script): array
    {
        $row = ['asm' => $script->asm, 'hex' => $script->hex, 'type' => $script->type];
        if (null !== $script->address) {
            $row['address'] = $script->address;
        }

        return $row;
    }

    private function walletEntry(WalletTransactionEntry $entry): BitcoinWalletTransactionEntry
    {
        return new BitcoinWalletTransactionEntry(
            $entry->txid,
            $entry->vout,
            $entry->category,
            $entry->address,
            $this->amount($entry->amount),
            $entry->confirmations,
            $entry->blockHash,
            $entry->blockTime,
            $entry->timeReceived,
        );
    }

    private function token(?TokenData $token): ?BitcoinTokenData
    {
        return $token instanceof TokenData ? new BitcoinTokenData($token->category, $token->amount, $token->nft?->capability->value, $token->nft?->commitment) : null;
    }

    private function amount(BchAmount $amount): BitcoinAmount
    {
        return BitcoinAmount::fromSatoshis((string) $amount->satoshis);
    }

    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    private function guard(callable $call): mixed
    {
        try {
            return $call();
        } catch (BchnRpcException $exception) {
            throw new BitcoinRpcException($exception->getMessage(), $exception->rpcCode);
        }
    }
}
