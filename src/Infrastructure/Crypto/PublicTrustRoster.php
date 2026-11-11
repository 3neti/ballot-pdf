<?php

namespace LBHurtado\BallotPdf\Infrastructure\Crypto;

use LBHurtado\TruthQr\Contracts\TrustedPublicKeyResolver;

final readonly class PublicTrustRoster implements TrustedPublicKeyResolver
{
    /** @param array<string, mixed> $document */
    public function __construct(private array $document)
    {
        if (! isset($document['roster_version'], $document['keys']) || ! is_array($document['keys'])) {
            throw new \InvalidArgumentException('Public trust roster requires a version and keys array.');
        }
    }

    public static function fromJsonFile(string $path): self
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Public trust roster not found: {$path}");
        }
        return new self(json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
    }

    public function resolve(string $keyId, string $purpose, string $electionId, string $precinctId): ?string
    {
        foreach ($this->document['keys'] as $key) {
            if (! is_array($key) || (string) ($key['key_id'] ?? '') !== $keyId || ($key['status'] ?? 'active') !== 'active') {
                continue;
            }
            if (($key['purpose'] ?? null) !== $purpose || ! $this->matchesScope($key['election_id'] ?? '*', $electionId) || ! $this->matchesScope($key['precinct_id'] ?? '*', $precinctId)) {
                return null;
            }
            $publicKey = base64_decode((string) ($key['public_key'] ?? ''), true);
            return $publicKey === false ? null : $publicKey;
        }
        return null;
    }

    /** @return array<string, mixed> */
    public function document(): array
    {
        return $this->document;
    }

    public function hash(): string
    {
        return hash('sha256', \LBHurtado\BallotPdf\Support\CanonicalJson::encode($this->document));
    }

    private function matchesScope(mixed $expected, string $actual): bool
    {
        return $expected === '*' || $expected === $actual;
    }
}
