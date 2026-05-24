<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

interface FinalityVerifierInterface
{
    /**
     * Re-polls the wallet's recent transaction list N times, separated by
     * a configured interval, to verify the broadcast tx hash stays present.
     * Returns false on the first poll where the hash has disappeared
     * (reorg-orphan). Synchronous — designed for the post-broadcast
     * withdraw-side handler (tight deadline).
     */
    public function verifyDepth(string $walletAddrRaw, string $hash, float $deadline): bool;

    /**
     * One-shot presence check — single `getTypedTransactions(wallet, limit=10)`
     * query, returns true when the hash is present in the most-recent slice.
     * No polling, no sleep — designed for the cron-tick deposit-side
     * `*ConfirmationCheck` reorg defense: each tick is its own poll, and
     * tick frequency is the natural cadence.
     */
    public function checkPresence(string $walletAddrRaw, string $hash): bool;
}
