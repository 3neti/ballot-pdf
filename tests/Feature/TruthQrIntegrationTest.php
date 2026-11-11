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
use LBHurtado\BallotPdf\Application\CeremonyApprovalGate;
use LBHurtado\BallotPdf\Application\RosterDistribution;
use LBHurtado\BallotPdf\Infrastructure\Crypto\EncryptedKeyEscrow;
use LBHurtado\BallotPdf\Infrastructure\Crypto\KeychainSigningKeyProvider;
use LBHurtado\BallotPdf\Contracts\SecretKeyStore;

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
    $approvals = [
        ['approver_id' => 'approver-1', 'role' => 'custodian', 'decision' => 'approved', 'approved_at' => '2028-01-01T00:00:00Z'],
        ['approver_id' => 'approver-2', 'role' => 'auditor', 'decision' => 'approved', 'approved_at' => '2028-01-01T00:00:00Z'],
    ];
    $initial = $manager->initialize('key-2028-a', 'election.ballot.v2', 'demo-election', 'precinct-1', ['BEI-1', 'custodian-1'], '2028-01-01T00:00:00Z', $approvals);
    $rotation = $manager->rotate('key-2028-b', 'election.ballot.v2', 'demo-election', 'precinct-1', ['BEI-1', 'custodian-2'], '2028-01-02T00:00:00Z', $approvals);

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

it('requires distinct human approvals and verifies distribution and escrow records', function (): void {
    $approvals = [
        ['approver_id' => 'same-person', 'role' => 'custodian', 'decision' => 'approved', 'approved_at' => '2028-01-01T00:00:00Z'],
        ['approver_id' => 'same-person', 'role' => 'auditor', 'decision' => 'approved', 'approved_at' => '2028-01-01T00:00:00Z'],
    ];
    expect(fn () => (new CeremonyApprovalGate)->requireQuorum($approvals))->toThrow(RuntimeException::class);

    $distribution = (new RosterDistribution)->create('v0002', str_repeat('a', 64), ['CCS-1', 'VERIFIER-1'], 'signed-removable-media', '2028-01-03T00:00:00Z', str_repeat('b', 64));
    expect((new RosterDistribution)->verify($distribution, str_repeat('a', 64)))->toBeTrue();

    $escrow = new EncryptedKeyEscrow;
    $wrappingKey = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    $secretKey = random_bytes(SODIUM_CRYPTO_SIGN_SECRETKEYBYTES);
    $record = $escrow->seal('key-2028-b', $secretKey, $wrappingKey, '2028-01-03T00:00:00Z');
    expect($escrow->recover($record, $wrappingKey))->toBe($secretKey);
    $record['ciphertext'] = base64_encode(random_bytes(32));
    expect(fn () => $escrow->recover($record, $wrappingKey))->toThrow(RuntimeException::class);

    $keypair = sodium_crypto_sign_keypair();
    $keychain = new class(sodium_crypto_sign_secretkey($keypair)) implements SecretKeyStore {
        public function __construct(private readonly string $secret) {}
        public function get(string $service, string $account): string { return base64_encode($this->secret); }
    };
    $provider = new KeychainSigningKeyProvider($keychain, 'aes-demo', 'ballot-key', 'keychain-key', 'election.ballot.v2', 'demo-election', 'precinct-1');
    expect($provider->forRecord('election.ballot.v2', 'demo-election', 'precinct-1')->keyId)->toBe('keychain-key')
        ->and(fn () => $provider->forRecord('election.ballot.v2', 'other-election', 'precinct-1'))->toThrow(RuntimeException::class);
});
