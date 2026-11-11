<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfQrScanner;
use LBHurtado\BallotPdf\Domain\Context\ElectionContext;
use LBHurtado\BallotPdf\Domain\Verification\VerificationResult;
use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class BuildElectionReturn
{
    public function __construct(
        private BallotPayloadCodec $codec,
        private ?PdfQrScanner $pdfQrScanner = null,
    ) {}

    /** @param list<VerificationResult> $results @return array{artifact: array<string, mixed>, json: string, sha256: string} */
    public function execute(array $results, ElectionContext $context): array
    {
        $eligible = array_values(array_filter($results, static fn (VerificationResult $result): bool => $result->eligible));
        usort($eligible, static fn (VerificationResult $left, VerificationResult $right): int => [$left->serial, $left->path] <=> [$right->serial, $right->path]);

        $tally = [];
        $ballots = [];
        foreach ($eligible as $result) {
            $payload = $this->payloadFor($result);
            foreach ((array) ($payload['candidate_codes'] ?? []) as $candidateCode) {
                $candidateCode = (string) $candidateCode;
                $tally[$candidateCode] = ($tally[$candidateCode] ?? 0) + 1;
            }
            ksort($tally);
            $ballots[] = [
                'ballot_serial' => $result->serial,
                'payload_sha256' => hash('sha256', CanonicalJson::encode($payload)),
                'source' => basename($result->path),
            ];
        }

        $verificationSummary = array_map(static fn (VerificationResult $result): array => [
            'ballot_serial' => $result->serial,
            'classification' => $result->classification,
            'eligible' => $result->eligible,
            'issues' => $result->issues,
        ], $results);
        $artifact = [
            'artifact_type' => 'election-return',
            'artifact_version' => '1.0',
            'election_context' => $context->toArray(),
            'eligible_ballot_count' => count($ballots),
            'eligible_ballots' => $ballots,
            'tally' => $tally,
            'source_verification_report_sha256' => hash('sha256', CanonicalJson::encode($verificationSummary)),
        ];
        $json = CanonicalJson::encode($artifact);

        return ['artifact' => $artifact, 'json' => $json, 'sha256' => hash('sha256', $json)];
    }

    /** @return array<string, mixed> */
    private function payloadFor(VerificationResult $result): array
    {
        if (str_ends_with(strtolower($result->path), '.json')) {
            $manifest = json_decode((string) file_get_contents($result->path), true, 512, JSON_THROW_ON_ERROR);
            return $this->codec->decode(array_values($manifest['truth_qr_scans']));
        }
        if ($this->pdfQrScanner === null) {
            throw new \RuntimeException('A PDF QR scanner is required for PDF-only Election Returns.');
        }
        return $this->codec->decode($this->pdfQrScanner->scans($result->path));
    }
}
