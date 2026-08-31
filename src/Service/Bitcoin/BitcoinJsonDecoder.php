<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

use JsonException;

final readonly class BitcoinJsonDecoder
{
    /**
     * @return array<string, mixed>
     */
    public function decode(string $json): array
    {
        try {
            $decoded = json_decode($this->quoteNumbers($json), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new BitcoinRpcException('Invalid Bitcoin Core JSON response: ' . $exception->getMessage());
        }

        return BitcoinRpcValue::map($decoded, 'response');
    }

    private function quoteNumbers(string $json): string
    {
        $length   = strlen($json);
        $result   = '';
        $inString = false;
        $escaped  = false;

        for ($offset = 0; $offset < $length; ++$offset) {
            $char = $json[$offset];
            if ($inString) {
                $result .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ('\\' === $char) {
                    $escaped = true;
                } elseif ('"' === $char) {
                    $inString = false;
                }
                continue;
            }

            if ('"' === $char) {
                $inString = true;
                $result  .= $char;
                continue;
            }

            if ('-' === $char || ctype_digit($char)) {
                $end = $offset + 1;
                while ($end < $length && str_contains('0123456789.eE+-', $json[$end])) {
                    ++$end;
                }
                $result .= '"' . substr($json, $offset, $end - $offset) . '"';
                $offset = $end - 1;
                continue;
            }

            $result .= $char;
        }

        return $result;
    }
}
