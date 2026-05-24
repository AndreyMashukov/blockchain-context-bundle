<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('blockchain_context');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('eth_rpc_url')->defaultValue('')->end()
                ->scalarNode('toncenter_api_key')->defaultValue('')->end()
                ->scalarNode('eth_wallet_private_key')->defaultValue('')->end()
                ->integerNode('eth_chain_id')->defaultValue(0)->end()
                ->scalarNode('ton_wallet_mnemonic')->defaultValue('')->end()
                ->scalarNode('deposit_wallet_encryption_key')->defaultValue('')->end()
                ->integerNode('ton_finality_polls')->defaultValue(0)->min(0)->end()
                ->scalarNode('bridge_ton_contract')->defaultValue('')->end()
                ->scalarNode('usdt_token_address')->defaultValue('')->end()
            ->end();

        return $treeBuilder;
    }
}
