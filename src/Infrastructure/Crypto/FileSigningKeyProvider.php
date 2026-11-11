<?php

namespace LBHurtado\BallotPdf\Infrastructure\Crypto;

use LBHurtado\TruthQr\Contracts\SigningKeyProvider;
use LBHurtado\TruthQr\Crypto\SigningKey;

final readonly class FileSigningKeyProvider implements SigningKeyProvider
{
    /** @param array<string, mixed> $document */
    public function __construct(private array $document)
    {
        if (! isset($document['key_id'], $document['secret_key'])) {
            throw new \InvalidArgumentException('Signing key custody record requires key_id and secret_key.');
        }
    }

    public static function fromJsonFile(string $path): self
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Signing key custody record not found: {$path}");
        }
        $permissions = fileperms($path);
        if ($permissions !== false && (($permissions & 0077) !== 0)) {
            throw new \RuntimeException('Signing key custody record must not be group- or world-readable.');
        }
        return new self(json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
    }

    public function forRecord(string $purpose, string $electionId, string $precinctId): SigningKey
    {
        if (($this->document['status'] ?? 'active') !== 'active'
            || ($this->document['purpose'] ?? null) !== $purpose
            || ! $this->matchesScope($this->document['election_id'] ?? '*', $electionId)
            || ! $this->matchesScope($this->document['precinct_id'] ?? '*', $precinctId)) {
            throw new \RuntimeException('Signing key custody record is not authorized for this context.');
        }
        $secretKey = base64_decode((string) $this->document['secret_key'], true);
        if ($secretKey === false) {
            throw new \RuntimeException('Signing key custody record is not valid base64.');
        }
        return new SigningKey((string) $this->document['key_id'], $secretKey);
    }

    private function matchesScope(mixed $expected, string $actual): bool
    {
        return $expected === '*' || $expected === $actual;
    }
}
