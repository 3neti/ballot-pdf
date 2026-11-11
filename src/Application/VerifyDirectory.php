<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfQrScanner;
use LBHurtado\BallotPdf\Domain\Context\ElectionContext;
use LBHurtado\BallotPdf\Domain\Verification\VerificationResult;

final readonly class VerifyDirectory
{
    public function __construct(private BallotPayloadCodec $codec, private ?PdfQrScanner $pdfQrScanner = null) {}

    /** @return list<VerificationResult> */
    public function execute(string $root, ElectionContext $context): array
    {
        $files = [];
        if (is_dir($root)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.json')) {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files, SORT_STRING);
        $results = [];
        $bySerial = [];
        $payloadHashes = [];
        $manifestSerials = [];
        $pdfBySerial = [];
        foreach ($this->pdfFiles($root) as $pdfPath) {
            $pdfBySerial[pathinfo($pdfPath, PATHINFO_FILENAME)][] = $pdfPath;
        }

        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            if (! isset($data['truth_qr_scans'], $data['ballot_serial'])) {
                continue;
            }
            $serial = (string) $data['ballot_serial'];
            $manifestSerials[$serial] = true;
            try {
                $declaredStatus = (string) ($data['status'] ?? 'finalized');
                if (in_array($declaredStatus, ['cancelled', 'spoiled', 'unused'], true)) {
                    $results[] = new VerificationResult($file, $serial, $declaredStatus, false);
                    $bySerial[$serial][] = count($results) - 1;
                    continue;
                }
                $payload = $this->codec->decode(array_values($data['truth_qr_scans']));
                $issues = [];
                foreach ($context->toArray() as $key => $expected) {
                    if (($payload[$key] ?? null) !== $expected) {
                        $issues[] = "context mismatch: {$key}";
                    }
                }
                if (($payload['paper_ballot_serial'] ?? null) !== $serial) {
                    $issues[] = 'serial mismatch';
                }
                if (isset($data['pdf_sha256'])) {
                    $pdfPaths = $pdfBySerial[$serial] ?? [];
                    if ($pdfPaths === []) {
                        $issues[] = 'PDF evidence not found';
                    } elseif (! in_array((string) $data['pdf_sha256'], array_map(static fn (string $path): string => hash_file('sha256', $path), $pdfPaths), true)) {
                        $issues[] = 'PDF hash mismatch';
                    }
                }
                $classification = $issues === [] ? 'accepted' : 'invalid_context';
                $results[] = new VerificationResult($file, $serial, $classification, $classification === 'accepted', $issues);
                $bySerial[$serial][] = count($results) - 1;
                $payloadHashes[$serial][count($results) - 1] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            } catch (\Throwable $exception) {
                $results[] = new VerificationResult($file, $serial, 'invalid_signature', false, [$exception->getMessage()]);
                $bySerial[$serial][] = count($results) - 1;
            }
        }

        if ($this->pdfQrScanner !== null) {
            foreach ($this->pdfFiles($root) as $pdfPath) {
                $scans = $this->pdfQrScanner->scans($pdfPath);
                if ($scans === []) continue;
                try {
                    $payload = $this->codec->decode($scans);
                    $serial = (string) ($payload['paper_ballot_serial'] ?? basename($pdfPath, '.pdf'));
                    if (isset($manifestSerials[$serial])) continue;
                    $issues = [];
                    foreach ($context->toArray() as $key => $expected) {
                        if (($payload[$key] ?? null) !== $expected) $issues[] = "context mismatch: {$key}";
                    }
                    $results[] = new \LBHurtado\BallotPdf\Domain\Verification\VerificationResult($pdfPath, $serial, $issues === [] ? 'accepted' : 'invalid_context', $issues === [], $issues);
                    $bySerial[$serial][] = count($results) - 1;
                    $payloadHashes[$serial][count($results) - 1] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
                } catch (\Throwable $exception) {
                    $serial = basename($pdfPath, '.pdf');
                    $results[] = new \LBHurtado\BallotPdf\Domain\Verification\VerificationResult($pdfPath, $serial, 'invalid_signature', false, [$exception->getMessage()]);
                    $bySerial[$serial][] = count($results) - 1;
                }
            }
        }

        foreach ($bySerial as $indexes) {
            $eligible = array_values(array_filter($indexes, fn (int $index): bool => $results[$index]->eligible));
            if (count($eligible) > 1) {
                $distinctPayloads = array_unique(array_map(static fn (int $index): string => $payloadHashes[$results[$index]->serial][$index] ?? '', $eligible));
                $classification = count($distinctPayloads) > 1 ? 'duplicate_conflicting' : 'duplicate_exact';
                foreach (array_slice($eligible, 1) as $index) {
                    $results[$index] = new VerificationResult($results[$index]->path, $results[$index]->serial, $classification, false, ['serial already represented by another eligible artifact']);
                }
            }
        }
        return $results;
    }

    /** @return list<string> */
    private function pdfFiles(string $root): array
    {
        $files = [];
        if (! is_dir($root)) return $files;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') $files[] = $file->getPathname();
        }
        sort($files, SORT_STRING);
        return $files;
    }
}
