<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

use Amashukov\AbiEncoder\AbiEncoder;
use Amashukov\EthRpc\Numeric\HexInt;
use Amashukov\BlockchainContextBundle\Service\Numeric\UuidIntCodec;
use InvalidArgumentException;

final readonly class Erc20DepositTxBuilder implements DepositTxBuilderInterface
{
    public function __construct(
        private string $usdtTokenAddress,
        private int $chainId,
        private UuidIntCodec $uuidIntCodec,
    ) {}

    public function supports(string $chain): bool
    {
        return 'usdt_erc20' === $chain;
    }

    public function build(DepositTxOrderView $order, array $context = []): DepositTxPayload
    {
        $bridge     = strtolower((string) $order->getDepositAddress());
        $token      = strtolower($this->usdtTokenAddress);
        $orderId    = $this->uuidIntCodec->encode((string) $order->getOrderId());
        $fromAmount = (string) $order->getFromAmount();
        if (!is_numeric($fromAmount)) {
            throw new InvalidArgumentException(sprintf('Erc20DepositTxBuilder: order.fromAmount must be numeric-string; got "%s".', $fromAmount));
        }
        $amountUnits = bcmul($fromAmount, '1000000', 0);

        $approveData = AbiEncoder::encodeCall(
            'approve(address,uint256)',
            [
                ['address', $bridge],
                ['uint256', $amountUnits],
            ],
        );

        $depositData = AbiEncoder::encodeCall(
            'depositTokenForBridge(address,uint256,uint256)',
            [
                ['address', $token],
                ['uint256', $amountUnits],
                ['uint256', $orderId],
            ],
        );

        $chainIdHex = HexInt::toHex($this->chainId);

        return new DepositTxPayload('evm-erc20', [
            'approve' => [
                'to'      => $token,
                'data'    => $approveData,
                'value'   => '0x0',
                'chainId' => $chainIdHex,
            ],
            'deposit' => [
                'to'      => $bridge,
                'data'    => $depositData,
                'value'   => '0x0',
                'chainId' => $chainIdHex,
            ],
        ]);
    }


    public function nextStep(DepositTxOrderView $order, array $context = []): DepositTxStep
    {
        $payload    = $this->build($order, $context);
        $fromAmount = (string) $order->getFromAmount();
        $approveTx  = $payload->payload['approve'] ?? null;

        if (is_array($approveTx)) {
            return new DepositTxStep(
                kind: 'evm-approve',
                buttonLabel: sprintf('Approve %s USDT spending', $fromAmount),
                tx: $approveTx,
                done: false,
            );
        }

        return new DepositTxStep(
            kind: 'evm-deposit-erc20',
            buttonLabel: sprintf('Deposit %s USDT to bridge', $fromAmount),
            tx: $payload->payload['deposit'] ?? null,
            done: false,
        );
    }
}
