<?php

namespace LBHurtado\BallotPdf\Infrastructure\Crypto;

use LBHurtado\TruthQr\Contracts\SigningKeyProvider;
use LBHurtado\TruthQr\Contracts\TrustedPublicKeyResolver;
use LBHurtado\TruthQr\Crypto\SigningKey;

final readonly class PersistentTrustRoster implements SigningKeyProvider, TrustedPublicKeyResolver
{
    /** @param array<string, mixed> $document */
    public function __construct(private array $document)
    {
        if (! isset($document['keys']) || ! is_array($document['keys'])) {
            throw new \InvalidArgumentException('Trust roster requires a keys array.');
        }
    }

    public static function fromJsonFile(string $path): self
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Trust roster not found: {$path}");
        }
        return new self(json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
    }

    public function forRecord(string $purpose, string $electionId, string $precinctId): SigningKey
    {
        $activeKeyId = (string) ($this->document['active_key_id'] ?? '');
        foreach ($this->document['keys'] as $key) {
            if (! is_array($key) || (string) ($key['key_id'] ?? '') !== $activeKeyId || ($key['status'] ?? 'active') !== 'active') {
                continue;
            }
            if (! $this->matches($key, $purpose, $electionId, $precinctId)) {
                continue;
            }
            $secret = base64_decode((string) ($key['secret_key'] ?? ''), true);
            if ($secret === false) {
                throw new \RuntimeException('Trust roster secret key is not valid base64.');
            }
            return new SigningKey($activeKeyId, $secret);
        }
        throw new \RuntimeException('Trust roster has no active signing key for this context.');
    }

    public function resolve(string $keyId, string $purpose, string $electionId, string $precinctId): ?string
    {
        foreach ($this->document['keys'] as $key) {
            if (! is_array($key) || (string) ($key['key_id'] ?? '') !== $keyId || ($key['status'] ?? 'active') !== 'active') {
                continue;
            }
            if (! $this->matches($key, $purpose, $electionId, $precinctId)) {
                return null;
            }
            $publicKey = base64_decode((string) ($key['public_key'] ?? ''), true);
            return $publicKey === false ? null : $publicKey;
        }
        return null;
    }

    /** @param array<string, mixed> $key */
    private function matches(array $key, string $purpose, string $electionId, string $precinctId): bool
    {
        return ($key['purpose'] ?? $purpose) === $purpose
            && $this->matchesScope($key['election_id'] ?? '*', $electionId)
            && $this->matchesScope($key['precinct_id'] ?? '*', $precinctId);
    }

    private function matchesScope(mixed $expected, string $actual): bool
    {
        return $expected === '*' || $expected === $actual;
    }
}
