<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

use Amashukov\AbiEncoder\AbiEncoder;
use Amashukov\BlockchainContextBundle\Service\Numeric\UuidIntCodec;
use Amashukov\EthRpc\EthRpcClientInterface;
use Amashukov\EthRpc\Numeric\HexBig;
use Amashukov\EthRpc\Numeric\HexInt;
use InvalidArgumentException;
use Throwable;

final readonly class Erc20DepositTxBuilder implements DepositTxBuilderInterface
{
    public function __construct(
        private string $usdtTokenAddress,
        private int $chainId,
        private UuidIntCodec $uuidIntCodec,
        private ?EthRpcClientInterface $ethRpc = null,
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
        $payload     = $this->build($order, $context);
        $fromAmount  = (string) $order->getFromAmount();
        $userAddress = $context['userAddress'] ?? '';

        if ('' === $userAddress) {
            throw new InvalidArgumentException('Erc20DepositTxBuilder::nextStep requires context[userAddress] for allowance check.');
        }

        if (!is_numeric($fromAmount)) {
            throw new InvalidArgumentException(sprintf('Erc20DepositTxBuilder::nextStep: order.fromAmount must be numeric-string; got "%s".', $fromAmount));
        }

        $bridge      = strtolower((string) $order->getDepositAddress());
        $token       = strtolower($this->usdtTokenAddress);
        $amountUnits = bcmul($fromAmount, '1000000', 0);

        /** @var array<string, mixed>|null $depositTx */
        $depositTx = $payload->payload['deposit'] ?? null;
        /** @var array<string, mixed>|null $approveTx */
        $approveTx = $payload->payload['approve'] ?? null;

        if ($this->hasSufficientAllowance($token, $userAddress, $bridge, $amountUnits)) {
            return new DepositTxStep(
                kind: 'evm-deposit-erc20',
                buttonLabel: sprintf('Deposit %s USDT to bridge', $fromAmount),
                tx: $depositTx,
                done: false,
            );
        }

        return new DepositTxStep(
            kind: 'evm-approve',
            buttonLabel: sprintf('Approve %s USDT spending', $fromAmount),
            tx: $approveTx,
            done: false,
        );
    }

    private function hasSufficientAllowance(string $token, string $userAddress, string $bridge, string $requiredUnits): bool
    {
        if (null === $this->ethRpc) {
            return false;
        }

        try {
            $callData = AbiEncoder::encodeCall(
                'allowance(address,address)',
                [
                    ['address', strtolower($userAddress)],
                    ['address', $bridge],
                ],
            );
            $result = $this->ethRpc->eth_call([
                'to'   => $token,
                'data' => $callData,
            ], 'latest');
        } catch (Throwable) {
            return false;
        }

        if ('' === $result || '0x' === $result) {
            return false;
        }

        $allowance = HexBig::fromHex($result);

        if (!is_numeric($requiredUnits)) {
            return false;
        }

        return bccomp($allowance, $requiredUnits, 0) >= 0;
    }
}
