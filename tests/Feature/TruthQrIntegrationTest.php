<?php

use LBHurtado\TruthQr\Contracts\CandidateCodes;
use LBHurtado\TruthQr\Contracts\SigningKeyProvider;
use LBHurtado\TruthQr\Contracts\TrustedPublicKeyResolver;
use LBHurtado\TruthQr\Core\CanonicalJson;
use LBHurtado\TruthQr\Crypto\Ed25519RecordSigner;
use LBHurtado\TruthQr\Crypto\SigningKey;
use LBHurtado\TruthQr\Election\BallotQrPayload;
use LBHurtado\TruthQr\Election\SignedBallotQrPayload;

it('uses the existing aes-election-v2 Ed25519 signed ballot profile', function (): void {
    $keypair = sodium_crypto_sign_keypair();
    $signingKey = new SigningKey('demo-ballot-key', sodium_crypto_sign_secretkey($keypair));
    $publicKey = sodium_crypto_sign_publickey($keypair);
    $signingKeys = new class($signingKey) implements SigningKeyProvider {
        public function __construct(private readonly SigningKey $key) {}
        public function forRecord(string $purpose, string $electionId, string $precinctId): SigningKey
        {
            return $this->key;
        }
    };
    $trustedKeys = new class($publicKey) implements TrustedPublicKeyResolver {
        public function __construct(private readonly string $publicKey) {}
        public function resolve(string $keyId, string $purpose, string $electionId, string $precinctId): ?string
        {
            return $keyId === 'demo-ballot-key' ? $this->publicKey : null;
        }
    };
    $codes = new class implements CandidateCodes {
        public function codesForPayload(array $payload): array { return $payload['candidate_codes']; }
        public function selectionsForCodes(array $codes): array { return ['senator' => $codes]; }
        public function codeTotalsForTally(array $tally): array { return []; }
        public function tallyForCodeTotals(array $codeTotals): array { return []; }
    };
    $codec = new SignedBallotQrPayload(
        new BallotQrPayload(new CanonicalJson, $codes),
        new Ed25519RecordSigner($signingKeys, $trustedKeys),
    );
    $record = [
        'election_id' => 'demo-election',
        'precinct_id' => 'precinct-1',
        'ballot_style_id' => 'style-1',
        'mapping_hash' => 'mapping-1',
        'tabulation_profile' => 'paper-first',
        'paper_ballot_serial' => 'SERIAL-1',
        'document_profile' => [
            'type' => 'official-ballot', 'id' => 'obt-1', 'hash' => 'obt-hash',
            'asset_bundle_id' => 'assets-1', 'asset_bundle_hash' => 'assets-hash',
        ],
        'candidate_codes' => ['C1', 'C2'],
    ];

    $encoded = $codec->encode($record);
    $decoded = $codec->decode($encoded);

    expect($decoded['truth_signature_valid'])->toBeTrue()
        ->and($decoded['paper_ballot_serial'])->toBe('SERIAL-1')
        ->and($decoded['candidate_codes'])->toBe(['C1', 'C2']);
});
