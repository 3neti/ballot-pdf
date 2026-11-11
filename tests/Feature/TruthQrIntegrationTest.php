<?php

use LBHurtado\TruthQr\Contracts\CandidateCodes;
use LBHurtado\TruthQr\Contracts\SigningKeyProvider;
use LBHurtado\TruthQr\Contracts\TrustedPublicKeyResolver;
use LBHurtado\TruthQr\Core\CanonicalJson;
use LBHurtado\TruthQr\Crypto\Ed25519RecordSigner;
use LBHurtado\TruthQr\Crypto\SigningKey;
use LBHurtado\TruthQr\Election\BallotQrPayload;
use LBHurtado\TruthQr\Election\SignedBallotQrPayload;
use LBHurtado\BallotPdf\Infrastructure\Crypto\PersistentTrustRoster;
use LBHurtado\BallotPdf\Infrastructure\Crypto\FileSigningKeyProvider;
use LBHurtado\BallotPdf\Infrastructure\Crypto\PublicTrustRoster;
use LBHurtado\BallotPdf\Application\KeyCeremonyManager;

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

it('loads an injectable persistent trust roster and rejects an out-of-scope key', function (): void {
    $keypair = sodium_crypto_sign_keypair();
    $path = sys_get_temp_dir().'/ballot-pdf-roster-'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($path, json_encode([
        'active_key_id' => 'roster-key-1',
        'keys' => [[
            'key_id' => 'roster-key-1',
            'purpose' => 'election.ballot.v2',
            'election_id' => 'demo-election',
            'precinct_id' => 'precinct-1',
            'status' => 'active',
            'secret_key' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
            'public_key' => base64_encode(sodium_crypto_sign_publickey($keypair)),
        ]],
    ], JSON_THROW_ON_ERROR));

    $roster = PersistentTrustRoster::fromJsonFile($path);
    $key = $roster->forRecord('election.ballot.v2', 'demo-election', 'precinct-1');

    expect($key->keyId)->toBe('roster-key-1')
        ->and(strlen((string) $roster->resolve('roster-key-1', 'election.ballot.v2', 'demo-election', 'precinct-1')))->toBe(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)
        ->and($roster->resolve('roster-key-1', 'election.ballot.v2', 'other-election', 'precinct-1'))->toBeNull();
});

it('records key ceremony, custody, roster distribution, and rotation evidence', function (): void {
    $root = sys_get_temp_dir().'/ballot-pdf-key-ceremony-'.bin2hex(random_bytes(4));
    $manager = new KeyCeremonyManager($root);
    $initial = $manager->initialize('key-2028-a', 'election.ballot.v2', 'demo-election', 'precinct-1', ['BEI-1', 'custodian-1'], '2028-01-01T00:00:00Z');
    $rotation = $manager->rotate('key-2028-b', 'election.ballot.v2', 'demo-election', 'precinct-1', ['BEI-1', 'custodian-2'], '2028-01-02T00:00:00Z');

    $roster = PublicTrustRoster::fromJsonFile($rotation['roster_path']);
    $signingKey = FileSigningKeyProvider::fromJsonFile($rotation['private_key_path'])->forRecord('election.ballot.v2', 'demo-election', 'precinct-1');
    $publicRosterJson = file_get_contents($rotation['roster_path']);
    $ledgerLines = file($root.'/rotation-ledger.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    expect($initial['action'])->toBe('initialize')
        ->and($rotation['action'])->toBe('rotate')
        ->and($rotation['old_key_id'])->toBe('key-2028-a')
        ->and($signingKey->keyId)->toBe('key-2028-b')
        ->and($roster->resolve('key-2028-a', 'election.ballot.v2', 'demo-election', 'precinct-1'))->toBeNull()
        ->and(strlen((string) $roster->resolve('key-2028-b', 'election.ballot.v2', 'demo-election', 'precinct-1')))->toBe(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)
        ->and($publicRosterJson)->not->toContain('secret_key')
        ->and($ledgerLines)->toHaveCount(2)
        ->and($rotation['record_sha256'])->toHaveLength(64);
});
