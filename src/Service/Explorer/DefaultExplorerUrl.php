<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Explorer;

final readonly class DefaultExplorerUrl implements ExplorerUrlInterface
{
    public function __construct(
        private string $bridgeEthExplorer = 'https://etherscan.io',
        private string $tonExplorer = 'https://tonscan.org',
    ) {}

    public function forTx(string $chain, string $txHashOrLt): ?string
    {
        if ('' === $txHashOrLt) {
            return null;
        }
        $host = $this->hostFor($chain);
        if (null === $host) {
            return null;
        }

        return $host . '/tx/' . $txHashOrLt;
    }

    public function forAddress(string $chain, string $address): ?string
    {
        if ('' === $address) {
            return null;
        }
        $host = $this->hostFor($chain);
        if (null === $host) {
            return null;
        }

        return $host . '/address/' . $address;
    }

    private function hostFor(string $chain): ?string
    {
        return match ($chain) {
            'eth', 'usdt_erc20'   => '' !== $this->bridgeEthExplorer ? $this->bridgeEthExplorer : 'https://etherscan.io',
            'ton', 'usdt_jetton'  => $this->tonExplorer,
            default               => null,
        };
    }
}
