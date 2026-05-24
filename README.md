# blockchain-context-bundle

A Symfony 7 bundle that wires the pure-PHP TON and EVM SDKs
([`amashukov/ton-php`](https://github.com/AndreyMashukov/ton-php) +
[`amashukov/eth-php`](https://github.com/AndreyMashukov/eth-php)) into an
application as autowired services. The bundle is a thin Symfony shell — every
cryptographic and on-chain primitive lives in the underlying `amashukov/*`
packages; the bundle owns the DI wiring plus the few framework-agnostic
application services that sit on top of the stack.

## What the bundle adds

- **`SignatureVerifier`** — verifies EIP-191 (`personal_sign`) signatures via
  secp256k1 ecrecover + Keccak-256, and Ed25519 (TON Connect) signatures via
  libsodium. Returns `bool`, logs malformed input through PSR-3.
- **`PrivKeyEncrypter`** — AES-256-GCM authenticated encryption for private
  keys at rest (`nonce ‖ ciphertext ‖ tag`). Master key from the
  `DEPOSIT_WALLET_ENCRYPTION_KEY` env var (base64-encoded 32 bytes).
- **`DepositWalletDeriverInterface`** — a port for HD deposit-wallet derivation;
  the host application supplies the concrete deriver. Returns a `DerivedWallet`.
- **`DerivedWallet` / `DepositEvidence`** — immutable cross-layer value objects.

All TON / EVM primitives (cell layer, wallet, RPC clients, ABI encoder,
signers, Keccak, secp256k1, RLP) come transitively through the two SDK
meta-packages and are available to autowire.

## Requirements

- PHP 8.3+
- Symfony 7.x (`symfony/framework-bundle`)
- `ext-gmp`, `ext-sodium`, `ext-openssl`

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

Set the encryption key if you use `PrivKeyEncrypter`:

```dotenv
# .env.local
DEPOSIT_WALLET_ENCRYPTION_KEY=<base64 of 32 random bytes>
```

## Usage

```php
use Amashukov\BlockchainContextBundle\Service\SignatureVerifier;

final class WalletController
{
    public function __construct(private readonly SignatureVerifier $verifier) {}

    public function bind(string $message, string $signature, string $address): void
    {
        if (!$this->verifier->verifyEth($message, $signature, $address)) {
            throw new \DomainException('bad signature');
        }
        // ...
    }
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
