<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

use InvalidArgumentException;

final readonly class Erc20DepositTxBuilder implements DepositTxBuilderInterface
{
    public function __construct(
        private DepositEncoderInterface $encoder,
    ) {}

    public function supports(string $chain): bool
    {
        return 'usdt_erc20' === $chain;
    }

    public function build(DepositTxOrderView $order): DepositTxPayload
    {
        $vault = $order->getVault()->getAddress();

        return new DepositTxPayload('evm-erc20', [
            'approve' => $this->encoder->erc20Approve($order, $vault),
            'deposit' => $this->encoder->erc20Deposit($order, $vault),
        ]);
    }

    public function nextStep(DepositTxOrderView $order, SignerInterface $signer): DepositTxStep
    {
        $vault      = $order->getVault()->getAddress();
        $fromAmount = (string) $order->getFromAmount();

        if (!$this->encoder->signerHasSufficientBalance($order, $signer)) {
            throw new InvalidArgumentException('Erc20DepositTxBuilder: signer USDT balance is below the deposit amount.');
        }

        if ($this->encoder->allowanceSatisfied($order, $vault, $signer)) {
            return new DepositTxStep(
                kind: 'evm-deposit-erc20',
                buttonLabel: sprintf('Deposit %s USDT to bridge', $fromAmount),
                tx: $this->encoder->erc20Deposit($order, $vault),
                done: false,
            );
        }

        return new DepositTxStep(
            kind: 'evm-approve',
            buttonLabel: sprintf('Approve %s USDT spending', $fromAmount),
            tx: $this->encoder->erc20Approve($order, $vault),
            done: false,
        );
    }
}
