<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\DependencyInjection;

use Amashukov\BlockchainContextBundle\DependencyInjection\BlockchainContextExtension;
use Amashukov\BlockchainContextBundle\Service\PrivKeyEncrypter;
use Amashukov\BlockchainContextBundle\Service\SignatureVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class BlockchainContextExtensionTest extends TestCase
{
    public function testLoadRegistersSignatureVerifier(): void
    {
        $container = new ContainerBuilder();
        (new BlockchainContextExtension())->load([], $container);

        self::assertTrue($container->hasDefinition(SignatureVerifier::class), 'SignatureVerifier must be wired as an autowired service');
    }

    public function testLoadWiresPrivKeyEncrypterMasterKeyFromEnv(): void
    {
        $container = new ContainerBuilder();
        (new BlockchainContextExtension())->load([], $container);

        self::assertTrue($container->hasDefinition(PrivKeyEncrypter::class));
        self::assertSame(
            '%env(DEPOSIT_WALLET_ENCRYPTION_KEY)%',
            $container->getDefinition(PrivKeyEncrypter::class)->getArgument('$masterKeyB64'),
        );
    }
}
