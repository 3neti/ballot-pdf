<?php

namespace LBHurtado\BallotPdf\Infrastructure\Storage;

use LBHurtado\BallotPdf\Contracts\AcceptanceLedger;
use LBHurtado\BallotPdf\Domain\Evidence\AcceptanceRecord;
use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class FilesystemAcceptanceLedger implements AcceptanceLedger
{
    public function __construct(private string $root)
    {
        if (! is_dir($root) && ! mkdir($root, 0770, true) && ! is_dir($root)) {
            throw new \RuntimeException("Unable to create acceptance ledger {$root}.");
        }
    }

    public function accept(AcceptanceRecord $record): void
    {
        $existing = $this->byIdempotency($record->idempotencyKey);
        if ($existing !== null) {
            if ($existing->hash() !== $record->hash()) {
                throw new \RuntimeException('Acceptance idempotency conflict.');
            }
            return;
        }

        $serialRecord = $this->bySerial($record->serial);
        if ($serialRecord !== null && $serialRecord->hash() !== $record->hash()) {
            throw new \RuntimeException('Ballot serial has already been accepted with different content.');
        }

        $path = $this->root.'/'.$record->acceptanceId.'.json';
        if (file_exists($path)) {
            throw new \RuntimeException("Acceptance ledger refuses overwrite: {$path}");
        }
        file_put_contents($path, CanonicalJson::encode($record->toArray()), LOCK_EX);
    }

    public function byIdempotency(string $idempotencyKey): ?AcceptanceRecord
    {
        foreach ($this->records() as $record) {
            if ($record->idempotencyKey === $idempotencyKey) {
                return $record;
            }
        }
        return null;
    }

    public function bySerial(string $serial): ?AcceptanceRecord
    {
        foreach ($this->records() as $record) {
            if ($record->serial === $serial) {
                return $record;
            }
        }
        return null;
    }

    /** @return list<AcceptanceRecord> */
    private function records(): array
    {
        $files = glob($this->root.'/*.json') ?: [];
        sort($files, SORT_STRING);
        return array_map(static function (string $file): AcceptanceRecord {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            return new AcceptanceRecord(
                (string) $data['acceptance_id'],
                (string) $data['idempotency_key'],
                (string) $data['ballot_serial'],
                (string) $data['pdf_sha256'],
                (string) $data['payload_sha256'],
                (string) $data['manifest_sha256'],
                (string) $data['accepted_at'],
                (int) $data['acceptance_sequence'],
                (array) $data['context'],
                (string) ($data['status'] ?? 'accepted'),
            );
        }, $files);
    }
}
