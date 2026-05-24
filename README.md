# blockchain-context-bundle

A Symfony 7 bundle that turns the pure-PHP TON + EVM SDKs
([`amashukov/ton-php`](https://github.com/AndreyMashukov/ton-php) +
[`amashukov/eth-php`](https://github.com/AndreyMashukov/eth-php)) into a ready,
autowired blockchain surface for an application — typed JSON-RPC clients,
deposit-detection / finality / gas / deposit-tx-builder services, signature
verification, and private-key encryption — all configured from one place.

## What you get (autowired services)

- **RPC clients** — `Amashukov\EthRpc\JsonRpcProvider` / `EthRpcClient` (ethers.js-style
  EVM JSON-RPC) and `Amashukov\Toncenter\ToncenterClient` (typed toncenter v2),
  wired over a PSR-18 transport (`amashukov/http-client-php` cURL client; toncenter
  gets an `X-Api-Key` + `429/5xx/542`-retry middleware pipeline).
- **Signing & wallet** — `Amashukov\Eip1559TxSigner\Eip1559Signer` (EIP-1559 offline
  signer) and `Amashukov\TonWallet\WalletV4R2` (built from a mnemonic via the bundle
  factory); `ToncenterWalletRpc` implements the wallet's RPC port.
- **Domain services** (host-app-agnostic, owning ports the host implements):
  `Detection\ChainDepositCheckChain`, `Finality\{ConfirmationCheckChain,ConfirmationCounterRegistry,DepthPollingFinalityVerifier}`,
  `Gas\{EthGasFetcher,TonGasFetcher,ZeroGasFetcher}`, `TxBuilder\DepositTxBuilderChain`
  (+ per-chain TON / TON-Jetton / ETH / USDT-ERC20 builders), `Explorer\DefaultExplorerUrl`,
  `Numeric\{BcDecimal,UuidIntCodec,UsdtJettonDecimals}`, `Time\RealSleeper`.
- **`SignatureVerifier`** — EIP-191 (`personal_sign`, secp256k1 ecrecover + Keccak-256)
  + Ed25519 (TON Connect) verification.
- **`PrivKeyEncrypter`** — AES-256-GCM authenticated encryption for keys at rest.
- **`DepositWalletDeriverInterface`** port + `DerivedWallet` / `DepositEvidence` VOs.

The tagged-iterator chains (`ChainDepositCheckChain`, `ConfirmationCheckChain`,
`ConfirmationCounterRegistry`, `DepositTxBuilderChain`) auto-collect their members
via bundle-namespaced tags (`blockchain_context.*`) — drop a new
`ChainDepositCheckInterface` / `ConfirmationCounterInterface` / `DepositTxBuilderInterface`
impl and it joins the chain with no DI edits.

## Requirements

- PHP 8.3+
- Symfony 7.x (`symfony/framework-bundle`)
- `ext-gmp`, `ext-sodium`, `ext-openssl`, `ext-bcmath`

## Installation

```bash
composer require amashukov/blockchain-context-bundle
```

Register the bundle (Symfony Flex does this automatically):

```php
// config/bundles.php
return [
    // ...
    Amashukov\BlockchainContextBundle\BlockchainContextBundle::class => ['all' => true],
];
```

## Configuration

The bundle is **env-agnostic**: it exposes a config tree and reads only
`%blockchain_context.*%` parameters internally. The host maps them to its own
environment (use `%env(...)%`, with `default:` processors as you like):

```yaml
# config/packages/blockchain_context.yaml
blockchain_context:
    eth_rpc_url:                   '%env(ETH_RPC_URL)%'                 # EVM JSON-RPC endpoint
    toncenter_api_key:             '%env(TONCENTER_API_KEY)%'           # optional — lifts toncenter rate limit
    eth_wallet_private_key:        '%env(BRIDGE_ETH_WALLET_PRIVATE_KEY)%'
    eth_chain_id:                  '%env(int:BRIDGE_ETH_CHAIN_ID)%'
    ton_wallet_mnemonic:           '%env(BRIDGE_TON_WALLET_MNEMONIC)%'
    deposit_wallet_encryption_key: '%env(DEPOSIT_WALLET_ENCRYPTION_KEY)%' # base64 of 32 bytes
    ton_finality_polls:            '%env(int:TON_FINALITY_POLLS)%'       # 0 = skip depth re-poll
    bridge_ton_contract:           '%env(BRIDGE_TON_CONTRACT)%'          # only for USDT-Jetton deposits
    usdt_token_address:            '%env(USDT_TOKEN_ADDRESS)%'           # only for USDT-ERC20 deposits
```

Every key is optional (sensible empty / `0` defaults), so a host that uses only
one chain still boots.

## Usage

```php
use Amashukov\EthRpc\JsonRpcProviderInterface;
use Amashukov\Toncenter\ToncenterClientInterface;
use Amashukov\BlockchainContextBundle\Service\SignatureVerifier;

final class SomeService
{
    public function __construct(
        private JsonRpcProviderInterface $eth,
        private ToncenterClientInterface $ton,
        private SignatureVerifier $verifier,
    ) {}
}
```

## Testing

```bash
composer install
composer test     # PHPUnit
composer stan     # PHPStan (level 9)
composer cs       # php-cs-fixer (dry-run)
composer rector   # Rector (dry-run)
```

## License

MIT — see [LICENSE](LICENSE).
