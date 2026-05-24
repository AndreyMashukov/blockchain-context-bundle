<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Helper;

use Amashukov\EthRpc\Vo\EthereumBlock;
use Amashukov\EthRpc\Vo\EthereumFeeData;
use Amashukov\EthRpc\Vo\EthereumFeeHistory;
use Amashukov\EthRpc\Vo\EthereumTransaction;
use Amashukov\EthRpc\Vo\EthereumTransactionLog;
use Amashukov\EthRpc\Vo\EthereumTransactionReceipt;
use Amashukov\EthRpc\Vo\EthereumTxBundle;
use Amashukov\Toncenter\Vo\TonAccountInfo;
use Amashukov\Toncenter\Vo\TonRunMethodResult;
use Amashukov\Toncenter\Vo\TonSendBocResult;
use Amashukov\Toncenter\Vo\TonTransaction;

final readonly class TypedRpcStubFactory
{
    /**
     * @param list<array<string, mixed>> $rows raw toncenter row shape
     *
     * @return list<TonTransaction>
     */
    public static function tonRows(string $address, array $rows): array
    {
        return array_map(static fn(array $row): TonTransaction => TonTransaction::fromToncenter($row, $address), $rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<TonTransaction>
     */
    public static function tonRowsAny(array $rows): array
    {
        return self::tonRows('', $rows);
    }

    /**
     * @param null|array<string, mixed> $receipt raw eth_getTransactionReceipt shape
     * @param null|array<string, mixed> $tx      raw eth_getTransactionByHash shape
     */
    public static function ethBundle(string $hash, ?array $receipt, ?array $tx = null): EthereumTxBundle
    {
        return new EthereumTxBundle(
            transaction: EthereumTransaction::fromArray($hash, $tx ?? ['value' => '0x0']),
            receipt: EthereumTransactionReceipt::fromArray($hash, $receipt),
        );
    }

    /**
     * @param array<string, mixed> $row raw /getAddressInformation result shape
     */
    public static function tonAccountInfo(string $address, array $row): TonAccountInfo
    {
        return TonAccountInfo::fromArray($address, $row);
    }

    /**
     * @param array<string, mixed> $envelope raw /runGetMethod envelope (ok/gas_used/exit_code/stack)
     */
    public static function tonRunMethod(array $envelope): TonRunMethodResult
    {
        return TonRunMethodResult::fromToncenter($envelope);
    }

    public static function tonSendBoc(string $hash = ''): TonSendBocResult
    {
        return new TonSendBocResult(hash: $hash);
    }

    /**
     * @param null|numeric-string $gasPrice
     * @param numeric-string      $maxFeePerGas
     * @param numeric-string      $maxPriorityFeePerGas
     */
    public static function ethFeeData(?string $gasPrice, string $maxFeePerGas, string $maxPriorityFeePerGas): EthereumFeeData
    {
        return new EthereumFeeData(
            gasPrice: $gasPrice,
            maxFeePerGas: $maxFeePerGas,
            maxPriorityFeePerGas: $maxPriorityFeePerGas,
        );
    }

    /**
     * @param null|array<string, mixed> $row raw eth_getBlockByNumber shape
     */
    public static function ethBlock(?array $row): ?EthereumBlock
    {
        return EthereumBlock::fromArray($row);
    }

    /**
     * @param array{oldestBlock?: string, baseFeePerGas?: list<string>, gasUsedRatio?: list<float>, reward?: list<list<string>>} $envelope
     */
    public static function ethFeeHistory(array $envelope): EthereumFeeHistory
    {
        return EthereumFeeHistory::fromArray($envelope);
    }

    /**
     * @param list<array<string, mixed>> $rows raw eth_getLogs result rows
     *
     * @return list<EthereumTransactionLog>
     */
    public static function ethTypedLogs(array $rows): array
    {
        return array_map(
            EthereumTransactionLog::fromArray(...),
            $rows,
        );
    }
}
