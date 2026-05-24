<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Finality;

use Amashukov\BlockchainContextBundle\Tests\Helper\TypedRpcStubFactory;
use Amashukov\BlockchainContextBundle\Service\Finality\DepthPollingFinalityVerifier;
use Amashukov\BlockchainContextBundle\Service\Time\SleeperInterface;
use Amashukov\Toncenter\TonRpcException;
use Amashukov\Toncenter\ToncenterClientInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DepthPollingFinalityVerifier::class)]
final class DepthPollingFinalityVerifierTest extends TestCase
{
    private const string WALLET = '0:abcd';

    private const string HASH   = 'targetHash=';

    private ToncenterClientInterface&Stub $rpc;

    private SleeperInterface&Stub $sleeper;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->rpc     = $this->createStub(ToncenterClientInterface::class);
        $this->sleeper = $this->createStub(SleeperInterface::class);
        $this->clock   = new MockClock();
    }

    public function testVerifyDepthReturnsTrueWhenPollsIsZero(): void
    {
        $rpc = $this->createMock(ToncenterClientInterface::class);
        $rpc->expects(self::never())->method('getTypedTransactions');
        $verifier = new DepthPollingFinalityVerifier($rpc, $this->sleeper, $this->clock, new NullLogger(), 0);

        self::assertTrue($verifier->verifyDepth(self::WALLET, self::HASH, $this->futureDeadline()));
    }

    public function testVerifyDepthReturnsTrueWhenHashPresentOnEveryRepoll(): void
    {
        $this->rpc->method('getTypedTransactions')->willReturn(TypedRpcStubFactory::tonRows(self::WALLET, [
            ['transaction_id' => ['lt' => '100', 'hash' => self::HASH]],
        ]));
        $verifier = $this->makeVerifier(2);

        self::assertTrue($verifier->verifyDepth(self::WALLET, self::HASH, $this->futureDeadline()));
    }

    public function testVerifyDepthReturnsFalseWhenHashDisappearsOnRepoll(): void
    {
        $this->rpc->method('getTypedTransactions')->willReturn(TypedRpcStubFactory::tonRows(self::WALLET, [
            ['transaction_id' => ['lt' => '101', 'hash' => 'differentHash=']],
        ]));
        $verifier = $this->makeVerifier(2);

        self::assertFalse($verifier->verifyDepth(self::WALLET, self::HASH, $this->futureDeadline()));
    }

    public function testVerifyDepthReturnsTrueWhenDeadlineExceededBeforeAnyPoll(): void
    {
        $rpc = $this->createMock(ToncenterClientInterface::class);
        $rpc->expects(self::never())->method('getTypedTransactions');
        $verifier     = new DepthPollingFinalityVerifier($rpc, $this->sleeper, $this->clock, new NullLogger(), 3);
        $pastDeadline = (float) $this->clock->now()->format('U.u') - 10.0;

        self::assertTrue($verifier->verifyDepth(self::WALLET, self::HASH, $pastDeadline));
    }

    public function testVerifyDepthToleratesTonRpcExceptionAndKeepsPolling(): void
    {
        $calls = 0;
        $this->rpc->method('getTypedTransactions')->willReturnCallback(function () use (&$calls): array {
            ++$calls;
            if (1 === $calls) {
                throw new TonRpcException('toncenter blip', 503);
            }

            return TypedRpcStubFactory::tonRows(self::WALLET, [['transaction_id' => ['lt' => '102', 'hash' => self::HASH]]]);
        });
        $verifier = $this->makeVerifier(2);

        self::assertTrue($verifier->verifyDepth(self::WALLET, self::HASH, $this->futureDeadline()));
        self::assertSame(2, $calls);
    }

    public function testVerifyDepthSkipsMalformedTransactionEntries(): void
    {
        $this->rpc->method('getTypedTransactions')->willReturn(TypedRpcStubFactory::tonRows(self::WALLET, [
            ['transaction_id' => null],
            ['transaction_id' => ['hash' => '']],
            ['transaction_id' => ['lt' => '103', 'hash' => self::HASH]],
        ]));
        $verifier = $this->makeVerifier(1);

        self::assertTrue($verifier->verifyDepth(self::WALLET, self::HASH, $this->futureDeadline()));
    }

    public function testCheckPresenceReturnsTrueWhenHashFound(): void
    {
        $this->rpc->method('getTypedTransactions')->willReturn(TypedRpcStubFactory::tonRows(self::WALLET, [
            ['transaction_id' => ['lt' => '104', 'hash' => self::HASH]],
        ]));
        $verifier = $this->makeVerifier(0);

        self::assertTrue($verifier->checkPresence(self::WALLET, self::HASH));
    }

    public function testCheckPresenceReturnsFalseWhenHashAbsent(): void
    {
        $this->rpc->method('getTypedTransactions')->willReturn(TypedRpcStubFactory::tonRows(self::WALLET, [
            ['transaction_id' => ['lt' => '105', 'hash' => 'otherHash=']],
        ]));
        $verifier = $this->makeVerifier(0);

        self::assertFalse($verifier->checkPresence(self::WALLET, self::HASH));
    }

    public function testCheckPresenceReturnsTrueOnTonRpcException(): void
    {
        $this->rpc->method('getTypedTransactions')->willThrowException(new TonRpcException('toncenter blip', 503));
        $verifier = $this->makeVerifier(0);

        self::assertTrue($verifier->checkPresence(self::WALLET, self::HASH), 'on RPC blip checkPresence treats as present to avoid false-positive orphan');
    }

    private function makeVerifier(int $polls): DepthPollingFinalityVerifier
    {
        return new DepthPollingFinalityVerifier($this->rpc, $this->sleeper, $this->clock, new NullLogger(), $polls);
    }

    private function futureDeadline(): float
    {
        return (float) $this->clock->now()->format('U.u') + 3600.0;
    }
}
