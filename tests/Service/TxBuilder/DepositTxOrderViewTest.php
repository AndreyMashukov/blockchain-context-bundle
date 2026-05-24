<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxOrderView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

#[CoversClass(DepositTxOrderView::class)]
final class DepositTxOrderViewTest extends TestCase
{
    public function testInterfaceShapeIsTheMinimalReadOnlyOrderView(): void
    {
        $reflection = new ReflectionClass(DepositTxOrderView::class);

        $methods = array_map(static fn(ReflectionMethod $m): string => $m->getName(), $reflection->getMethods());
        sort($methods);

        self::assertSame(
            [
                'getDepositAddress',
                'getDepositMemo',
                'getFromAmount',
                'getFromChain',
                'getId',
                'getOrderId',
            ],
            $methods,
            'DepositTxOrderView is the contract every host-app Order implements for tx assembly — adding a method ripples through every entity that implements it. Keep this list pinned.',
        );

        self::assertTrue($reflection->isInterface());
    }
}
