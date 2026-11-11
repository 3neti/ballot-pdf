<?php

namespace LBHurtado\BallotPdf\Infrastructure\Crypto;

use LBHurtado\BallotPdf\Contracts\SecretKeyStore;
use LBHurtado\TruthQr\Contracts\SigningKeyProvider;
use LBHurtado\TruthQr\Crypto\SigningKey;

final readonly class KeychainSigningKeyProvider implements SigningKeyProvider
{
    public function __construct(
        private SecretKeyStore $secrets,
        private string $service,
        private string $account,
        private string $keyId,
        private string $purpose,
        private string $electionId = '*',
        private string $precinctId = '*',
    ) {}

    public function forRecord(string $purpose, string $electionId, string $precinctId): SigningKey
    {
        if ($purpose !== $this->purpose || ! $this->matches($this->electionId, $electionId) || ! $this->matches($this->precinctId, $precinctId)) {
            throw new \RuntimeException('OS keychain signing key is not authorized for this context.');
        }
        $secret = base64_decode($this->secrets->get($this->service, $this->account), true);
        if ($secret === false) throw new \RuntimeException('OS keychain secret is not valid base64.');
        return new SigningKey($this->keyId, $secret);
    }

    private function matches(string $expected, string $actual): bool
    {
        return $expected === '*' || $expected === $actual;
    }
}
