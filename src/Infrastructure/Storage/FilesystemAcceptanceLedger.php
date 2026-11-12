<?php

namespace LBHurtado\BallotPdf\Infrastructure\Storage;

use LBHurtado\BallotPdf\Contracts\AcceptanceLedger;
use LBHurtado\BallotPdf\Domain\Evidence\AcceptanceRecord;
use LBHurtado\BallotPdf\Domain\Evidence\ElectionCloseCertificate;
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
        if ($this->isClosed()) {
            throw new \RuntimeException('Acceptance ledger is closed.');
        }
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

        $highest = array_reduce($this->records(), static fn (int $carry, AcceptanceRecord $item): int => max($carry, $item->sequence), 0);
        if ($record->sequence <= $highest) {
            throw new \RuntimeException('Acceptance sequence must be strictly increasing.');
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

    public function allocateSequence(): int
    {
        if ($this->isClosed()) {
            throw new \RuntimeException('Acceptance ledger is closed.');
        }
        $path = $this->root.'/.sequence';
        $handle = fopen($path, 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Unable to lock acceptance sequence state.');
        }
        try {
            $contents = stream_get_contents($handle);
            $sequence = max((int) trim((string) $contents), 0) + 1;
            ftruncate($handle, 0);
            rewind($handle);
            if (fwrite($handle, (string) $sequence) === false || ! fflush($handle)) {
                throw new \RuntimeException('Unable to persist acceptance sequence.');
            }
            return $sequence;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return list<AcceptanceRecord> */
    public function records(): array
    {
        return $this->readRecords();
    }

    public function close(ElectionCloseCertificate $certificate): void
    {
        if ($this->isClosed()) {
            $existing = $this->root.'/.closed.json';
            if (is_file($existing) && hash_file('sha256', $existing) === hash('sha256', CanonicalJson::encode($certificate->toArray()))) {
                return;
            }
            throw new \RuntimeException('Acceptance ledger is already closed.');
        }
        $path = $this->root.'/.closed.json';
        if (file_exists($path)) {
            throw new \RuntimeException("Acceptance ledger refuses overwrite: {$path}");
        }
        file_put_contents($path, CanonicalJson::encode($certificate->toArray()), LOCK_EX);
    }

    public function isClosed(): bool
    {
        return is_file($this->root.'/.closed.json');
    }

    /** @return array<string, mixed> */
    public function recoveryReport(): array
    {
        $records = $this->records();
        $sequence = $this->root.'/.sequence';
        $highest = array_reduce($records, static fn (int $carry, AcceptanceRecord $item): int => max($carry, $item->sequence), 0);
        $stored = is_file($sequence) ? (int) trim((string) file_get_contents($sequence)) : 0;
        return [
            'record_count' => count($records),
            'highest_record_sequence' => $highest,
            'stored_sequence' => $stored,
            'sequence_consistent' => $stored >= $highest,
            'closed' => $this->isClosed(),
        ];
    }

    /** @return list<AcceptanceRecord> */
    private function readRecords(): array
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
                (string) ($data['content_fingerprint'] ?? ''),
            );
        }, $files);
    }
}
