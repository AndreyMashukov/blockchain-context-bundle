<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

interface BitcoinRpcClientInterface
{
    public function getBlockchainInfo(): BitcoinBlockchainInfo;

    public function getWalletInfo(): BitcoinWalletInfo;

    public function createWallet(string $name, string $passphrase = '', bool $avoidReuse = true): void;

    public function loadWallet(string $name): void;

    public function getNewAddress(string $label = '', string $addressType = 'bech32'): string;

    public function getAddressInfo(string $address): BitcoinAddressInfo;

    public function validateAddress(string $address): BitcoinAddressInfo;

    public function listSinceBlock(?string $blockHash, int $targetConfirmations = 1): BitcoinListSinceBlock;

    /**
     * @param list<string> $addresses
     *
     * @return list<BitcoinUnspentOutput>
     */
    public function listUnspent(int $minimumConfirmations = 1, int $maximumConfirmations = 9999999, array $addresses = []): array;

    /**
     * @return list<BitcoinOutpoint>
     */
    public function listLockUnspent(): array;

    /**
     * @param list<BitcoinOutpoint> $outpoints
     */
    public function lockUnspent(bool $unlock, array $outpoints, bool $persistent = true): bool;

    public function estimateSmartFee(int $confirmationTarget = 6, string $estimateMode = 'conservative'): BitcoinFeeEstimate;

    /**
     * @param list<BitcoinOutpoint>          $inputs
     * @param list<array<string, string>>    $outputs
     * @param array<string, mixed> $options
     */
    public function walletCreateFundedPsbt(array $inputs, array $outputs, array $options): BitcoinFundedPsbt;

    public function walletProcessPsbt(string $psbt, bool $sign = true): BitcoinProcessedPsbt;

    public function finalizePsbt(string $psbt, bool $extract = true): BitcoinFinalizedPsbt;

    /**
     * @return array<string, mixed>
     */
    public function decodePsbt(string $psbt): array;

    /**
     * @return array<string, mixed>
     */
    public function decodeRawTransaction(string $hex): array;

    public function testMempoolAccept(string $hex, string $maximumFeeRate = '0.10'): BitcoinMempoolAcceptance;

    public function sendRawTransaction(string $hex, string $maximumFeeRate = '0.10'): string;

    public function getTransaction(string $txid): BitcoinWalletTransaction;

    /**
     * @return null|array<string, mixed>
     */
    public function getMempoolEntry(string $txid): ?array;

    /**
     * @param array<string, mixed> $options
     */
    public function psbtBumpFee(string $txid, array $options = []): BitcoinBumpFeeResult;

    public function walletPassphrase(string $passphrase, int $timeoutSeconds): void;

    public function walletLock(): void;

    public function backupWallet(string $destination): void;
}
