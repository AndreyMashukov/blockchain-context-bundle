<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

final readonly class TonDepositTxBuilder implements DepositTxBuilderInterface
{
    public function __construct(
        private DepositEncoderInterface $encoder,
    ) {}

    public function supports(string $chain): bool
    {
        return 'ton' === $chain;
    }

    public function build(DepositTxOrderView $order): DepositTxPayload
    {
        return new DepositTxPayload('ton-native', $this->encoder->tonNativeDeposit($order, $order->getVault()->getAddress()));
    }

    public function nextStep(DepositTxOrderView $order, SignerInterface $signer): DepositTxStep
    {
        return new DepositTxStep(
            kind: 'ton-deposit-native',
            buttonLabel: sprintf('Send %s TON', (string) $order->getFromAmount()),
            tx: $this->build($order)->payload,
            done: false,
        );
    }
}
