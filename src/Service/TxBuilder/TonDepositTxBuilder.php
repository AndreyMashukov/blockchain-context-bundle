<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

use Amashukov\TonCell\Boc;
use Amashukov\TonCell\Builder;
use InvalidArgumentException;

final readonly class TonDepositTxBuilder implements DepositTxBuilderInterface
{
    public function supports(string $chain): bool
    {
        return 'ton' === $chain;
    }

    public function build(DepositTxOrderView $order, array $context = []): DepositTxPayload
    {
        $depositAddress = (string) $order->getDepositAddress();
        $fromAmount     = (string) $order->getFromAmount();
        $memo           = (string) $order->getDepositMemo();

        if (!is_numeric($fromAmount)) {
            throw new InvalidArgumentException(sprintf('TonDepositTxBuilder: order.fromAmount must be numeric-string; got "%s".', $fromAmount));
        }
        $amountNano = bcmul($fromAmount, '1000000000', 0);

        $commentCell = (new Builder())
            ->storeUint(0, 32)
            ->storeStringTail($memo)
            ->endCell();

        return new DepositTxPayload('ton-native', [
            'address' => $depositAddress,
            'amount'  => $amountNano,
            'payload' => Boc::encodeBase64($commentCell),
        ]);
    }

    public function nextStep(DepositTxOrderView $order, array $context = []): DepositTxStep
    {
        $payload = $this->build($order, $context);

        return new DepositTxStep(
            kind: 'ton-deposit-native',
            buttonLabel: sprintf('Send %s TON', (string) $order->getFromAmount()),
            tx: $payload->payload,
            done: false,
        );
    }
}
