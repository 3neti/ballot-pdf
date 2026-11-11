<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class KeyCeremonyManager
{
    public function __construct(private string $root) {}

    /** @param list<string> $participants @param list<array{approver_id: string, role: string, decision: string, approved_at: string}> $approvals @return array<string, mixed> */
    public function initialize(string $keyId, string $purpose, string $electionId, string $precinctId, array $participants, string $recordedAt, array $approvals): array
    {
        return $this->record('initialize', null, $keyId, $purpose, $electionId, $precinctId, $participants, $recordedAt, 1, $approvals);
    }

    /** @param list<string> $participants @param list<array{approver_id: string, role: string, decision: string, approved_at: string}> $approvals @return array<string, mixed> */
    public function rotate(string $keyId, string $purpose, string $electionId, string $precinctId, array $participants, string $recordedAt, array $approvals): array
    {
        $current = $this->latestRoster();
        $oldKeyId = (string) ($current['active_key_id'] ?? '');
        $version = ((int) ($current['roster_version'] ?? 0)) + 1;
        return $this->record('rotate', $oldKeyId, $keyId, $purpose, $electionId, $precinctId, $participants, $recordedAt, $version, $approvals);
    }

    /** @return array<string, mixed> */
    private function record(string $action, ?string $oldKeyId, string $keyId, string $purpose, string $electionId, string $precinctId, array $participants, string $recordedAt, int $version, array $approvals): array
    {
        $approvalHash = (new CeremonyApprovalGate)->requireQuorum($approvals);
        $this->ensureDirectories();
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $roster = $this->latestRoster();
        $keys = [];
        foreach ((array) ($roster['keys'] ?? []) as $existing) {
            if (is_array($existing)) $keys[] = [...$existing, 'status' => 'retired'];
        }
        $keys[] = [
            'key_id' => $keyId,
            'purpose' => $purpose,
            'election_id' => $electionId,
            'precinct_id' => $precinctId,
            'status' => 'active',
            'public_key' => base64_encode($publicKey),
        ];
        $rosterDocument = ['roster_version' => $version, 'active_key_id' => $keyId, 'keys' => $keys];
        $rosterJson = CanonicalJson::encode($rosterDocument);
        $rosterPath = $this->root.'/rosters/v'.str_pad((string) $version, 4, '0', STR_PAD_LEFT).'.json';
        $privatePath = $this->root.'/private/'.$keyId.'.json';
        $ceremonyId = 'KEY-CEREMONY-'.str_pad((string) $version, 4, '0', STR_PAD_LEFT);
        $record = [
            'ceremony_id' => $ceremonyId,
            'action' => $action,
            'roster_version' => $version,
            'old_key_id' => $oldKeyId,
            'new_key_id' => $keyId,
            'purpose' => $purpose,
            'election_id' => $electionId,
            'precinct_id' => $precinctId,
            'participants' => array_values($participants),
            'approval_sha256' => $approvalHash,
            'recorded_at' => $recordedAt,
            'public_roster_sha256' => hash('sha256', $rosterJson),
            'private_key_sha256' => hash('sha256', $secretKey),
        ];
        $record['record_sha256'] = hash('sha256', CanonicalJson::encode($record));
        $this->writeNew($rosterPath, $rosterJson);
        $this->writeNew($privatePath, CanonicalJson::encode([
            'key_id' => $keyId, 'purpose' => $purpose, 'election_id' => $electionId,
            'precinct_id' => $precinctId, 'status' => 'active', 'secret_key' => base64_encode($secretKey),
        ]));
        chmod($privatePath, 0600);
        $this->writeNew($this->root.'/ceremonies/'.$ceremonyId.'.json', CanonicalJson::encode($record));
        file_put_contents($this->root.'/rotation-ledger.jsonl', CanonicalJson::encode($record)."\n", FILE_APPEND | LOCK_EX);
        return [...$record, 'roster_path' => $rosterPath, 'private_key_path' => $privatePath];
    }

    /** @return array<string, mixed> */
    private function latestRoster(): array
    {
        $files = glob($this->root.'/rosters/v*.json') ?: [];
        if ($files === []) return [];
        sort($files, SORT_STRING);
        return json_decode((string) file_get_contents(end($files)), true, 512, JSON_THROW_ON_ERROR);
    }

    private function ensureDirectories(): void
    {
        foreach (['rosters', 'private', 'ceremonies'] as $directory) {
            $path = $this->root.'/'.$directory;
            if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) throw new \RuntimeException("Unable to create {$path}.");
        }
    }

    private function writeNew(string $path, string $contents): void
    {
        if (file_exists($path)) throw new \RuntimeException("Key ceremony evidence refuses overwrite: {$path}");
        if (file_put_contents($path, $contents, LOCK_EX) === false) throw new \RuntimeException("Unable to write {$path}.");
    }
}
