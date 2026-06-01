<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

interface DepositEncoderInterface
{
    /**
     * @return array<string, mixed> EVM tx shape {to, data, value, chainId}
     */
    public function evmNativeDeposit(DepositTxOrderView $order, string $vault): array;

    /**
     * @return array<string, mixed> EVM tx shape {to, data, value, chainId}
     */
    public function erc20Approve(DepositTxOrderView $order, string $vault): array;

    /**
     * @return array<string, mixed> EVM tx shape {to, data, value, chainId}
     */
    public function erc20Deposit(DepositTxOrderView $order, string $vault): array;

    /**
     * @return array<string, mixed> TonConnect message shape {address, amount, payload}
     */
    public function tonNativeDeposit(DepositTxOrderView $order, string $vault): array;

    /**
     * @return array<string, mixed> TonConnect message shape {address, amount, payload}
     */
    public function tonJettonDeposit(DepositTxOrderView $order, string $vault): array;

    public function signerHasSufficientBalance(DepositTxOrderView $order, SignerInterface $signer): bool;

    public function allowanceSatisfied(DepositTxOrderView $order, string $vault, SignerInterface $signer): bool;
}
