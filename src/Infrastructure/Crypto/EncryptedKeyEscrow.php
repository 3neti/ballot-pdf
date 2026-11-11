<?php

namespace LBHurtado\BallotPdf\Infrastructure\Crypto;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class EncryptedKeyEscrow
{
    public function seal(string $keyId, string $secretKey, string $wrappingKey, string $escrowedAt): array
    {
        $this->assertWrappingKey($wrappingKey);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($secretKey, $nonce, $wrappingKey);
        $record = [
            'artifact_type' => 'encrypted-key-escrow',
            'artifact_version' => '1.0',
            'key_id' => $keyId,
            'algorithm' => 'XSalsa20-Poly1305',
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
            'escrowed_at' => $escrowedAt,
        ];
        return [...$record, 'record_sha256' => hash('sha256', CanonicalJson::encode($record))];
    }

    /** @param array<string, mixed> $record */
    public function recover(array $record, string $wrappingKey): string
    {
        $this->assertWrappingKey($wrappingKey);
        $unsigned = $record;
        unset($unsigned['record_sha256']);
        if (($record['record_sha256'] ?? '') !== hash('sha256', CanonicalJson::encode($unsigned))) {
            throw new \RuntimeException('Escrow record hash mismatch.');
        }
        $nonce = base64_decode((string) ($record['nonce'] ?? ''), true);
        $ciphertext = base64_decode((string) ($record['ciphertext'] ?? ''), true);
        if ($nonce === false || $ciphertext === false) throw new \RuntimeException('Escrow record encoding is invalid.');
        $secret = sodium_crypto_secretbox_open($ciphertext, $nonce, $wrappingKey);
        if ($secret === false) throw new \RuntimeException('Escrow recovery failed.');
        return $secret;
    }

    private function assertWrappingKey(string $wrappingKey): void
    {
        if (strlen($wrappingKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new \InvalidArgumentException('Escrow wrapping key must be 32 bytes.');
    }
}
