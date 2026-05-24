<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

interface DepositTxBuilderInterface
{
    public function supports(string $chain): bool;

    /**
     * Build the deposit-tx payload for `$order`. `$context` carries chain-
     * specific extras the builder cannot derive from the Order alone:
     *
     *   - `userAddress`      : connected wallet address (required for
     *                          USDT-Jetton — encoded as the
     *                          `response_destination` field in the TEP-74
     *                          body).
     *   - `userJettonWallet` : user's TEP-74 jetton-wallet address (required
     *                          for USDT-Jetton — the outer
     *                          TonConnect `messages[0].address`). Resolving
     *                          this BE-side via `master.get_wallet_address`
     *                          is deferred to a follow-up that adds a
     *                          `Boc::deserialize` primitive.
     *
     * Other chains accept an empty `$context` and ignore the field.
     *
     * @param array<string, string> $context
     */
    public function build(DepositTxOrderView $order, array $context = []): DepositTxPayload;
}
