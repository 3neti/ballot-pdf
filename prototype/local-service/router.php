<?php

declare(strict_types=1);

header('Access-Control-Allow-Origin: http://127.0.0.1:5173');
header('Access-Control-Allow-Headers: content-type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require dirname(__DIR__, 2).'/vendor/autoload.php';

use LBHurtado\BallotPdf\Protocol\ReferenceProtocolService;
use LBHurtado\BallotPdf\Application\FinalizeBallot;
use LBHurtado\BallotPdf\Application\BuildReports;
use LBHurtado\BallotPdf\Application\VerifyDirectory;
use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfMaterializer;
use LBHurtado\BallotPdf\Domain\Ballot\BallotSession;
use LBHurtado\BallotPdf\Domain\Context\ContestDefinition;
use LBHurtado\BallotPdf\Domain\Context\ElectionContext;
use LBHurtado\BallotPdf\Domain\Evidence\EvidenceManifest;
use LBHurtado\BallotPdf\Domain\Stock\BallotSerial;
use LBHurtado\BallotPdf\Domain\Stock\BallotStock;
use LBHurtado\BallotPdf\Infrastructure\Storage\FilesystemWormStore;
use LBHurtado\BallotPdf\Infrastructure\Verification\ZbarPdfQrScanner;
use LBHurtado\TruthQr\Contracts\CandidateCodes;
use LBHurtado\TruthQr\Contracts\SigningKeyProvider;
use LBHurtado\TruthQr\Contracts\TrustedPublicKeyResolver;
use LBHurtado\TruthQr\Core\CanonicalJson;
use LBHurtado\TruthQr\Crypto\Ed25519RecordSigner;
use LBHurtado\TruthQr\Crypto\SigningKey;
use LBHurtado\TruthQr\Election\BallotQrPayload;
use LBHurtado\TruthQr\Election\SignedBallotPayloadEnvelope;
use LBHurtado\TruthQr\Election\SignedBallotQrPayload;
use LBHurtado\TruthQr\Image\StandardQrCode;
use LBHurtado\TruthQr\Image\ZbarPngQrCodeDecoder;

final class DemoQrPdfMaterializer implements PdfMaterializer
{
    public function __construct(private readonly string $pdfBytes, private readonly string $password, private readonly StandardQrCode $qr) {}

    public function materialize(array $payload, array $truthQrScans): string
    {
        $directory = sys_get_temp_dir().'/ballot-pdf-materializer-'.bin2hex(random_bytes(4));
        mkdir($directory, 0700, true);
        $input = $directory.'/input.pdf';
        $decrypted = $directory.'/decrypted.pdf';
        $output = $directory.'/output.pdf';
        $qrPath = $directory.'/truth-qr.png';
        file_put_contents($input, $this->pdfBytes);
        file_put_contents($qrPath, $this->qr->renderPng($truthQrScans[0], true));
        $decryptCommand = sprintf('qpdf --password=%s --decrypt %s %s 2>&1',
            escapeshellarg($this->password), escapeshellarg($input), escapeshellarg($decrypted),
        );
        exec($decryptCommand, $decryptLines, $decryptExitCode);
        if ($decryptExitCode !== 0 || ! is_file($decrypted)) {
            throw new RuntimeException('Unable to decrypt exported ballot PDF: '.implode("\n", $decryptLines));
        }
        $command = sprintf('node %s %s %s %s 2>&1',
            escapeshellarg(__DIR__.'/materialize-finalized-pdf.mjs'),
            escapeshellarg($decrypted), escapeshellarg($output), escapeshellarg($qrPath),
        );
        exec($command, $outputLines, $exitCode);
        if ($exitCode !== 0 || ! is_file($output)) {
            throw new RuntimeException('Unable to materialize finalized PDF: '.implode("\n", $outputLines));
        }
        $bytes = file_get_contents($output);
        array_map(static fn (string $path): bool => @unlink($path), [$input, $decrypted, $output, $qrPath]);
        @rmdir($directory);
        return $bytes === false ? throw new RuntimeException('Unable to read finalized PDF.') : $bytes;
    }
}

$demoKeyPath = sys_get_temp_dir().'/ballot-pdf-protocol/demo-signing-key.json';
$signedCodec = static function () use ($demoKeyPath): BallotPayloadCodec {
    if (! is_file($demoKeyPath)) {
        if (! is_dir(dirname($demoKeyPath))) mkdir(dirname($demoKeyPath), 0700, true);
        $keypair = sodium_crypto_sign_keypair();
        file_put_contents($demoKeyPath, json_encode([
            'secret_key' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
            'public_key' => base64_encode(sodium_crypto_sign_publickey($keypair)),
        ], JSON_THROW_ON_ERROR), LOCK_EX);
        chmod($demoKeyPath, 0600);
    }
    $keyMaterial = json_decode((string) file_get_contents($demoKeyPath), true, 512, JSON_THROW_ON_ERROR);
    $signingKey = new SigningKey('demo-ballot-key', base64_decode((string) $keyMaterial['secret_key'], true));
    $publicKey = base64_decode((string) $keyMaterial['public_key'], true);
    $signingKeys = new class($signingKey) implements SigningKeyProvider {
        public function __construct(private readonly SigningKey $key) {}
        public function forRecord(string $purpose, string $electionId, string $precinctId): SigningKey { return $this->key; }
    };
    $trustedKeys = new class($publicKey) implements TrustedPublicKeyResolver {
        public function __construct(private readonly string $publicKey) {}
        public function resolve(string $keyId, string $purpose, string $electionId, string $precinctId): ?string { return $keyId === 'demo-ballot-key' ? $this->publicKey : null; }
    };
    $codes = new class implements CandidateCodes {
        public function codesForPayload(array $payload): array { return $payload['candidate_codes'] ?? []; }
        public function selectionsForCodes(array $codes): array { return ['senator' => $codes]; }
        public function codeTotalsForTally(array $tally): array { return []; }
        public function tallyForCodeTotals(array $codeTotals): array { return []; }
    };
    $payload = new SignedBallotQrPayload(new BallotQrPayload(new CanonicalJson, $codes), new Ed25519RecordSigner($signingKeys, $trustedKeys));
    $envelope = new SignedBallotPayloadEnvelope;
    return new class($payload, $envelope) implements BallotPayloadCodec {
        public function __construct(private readonly SignedBallotQrPayload $payload, private readonly SignedBallotPayloadEnvelope $envelope) {}
        public function encode(array $record): array { return [$this->envelope->wrap($this->payload->encode($record))]; }
        public function decode(array $scans): array { return $this->payload->decode($this->envelope->unwrap($scans[0])); }
    };
};

function demoSerial(string $sessionId): string
{
    $suffix = str_pad((string) (hexdec(substr(hash('sha256', $sessionId), 0, 8)) % 1000000), 6, '0', STR_PAD_LEFT);
    return 'PRECINCT-1-'.$suffix;
}

$messageType = basename(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '');
$body = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
$service = new ReferenceProtocolService(
    static fn (array $message): array => [
        'status' => 'session_opened',
        'session_id' => $message['session_id'] ?? 'SESSION-DEMO',
        'ballot_serial' => $message['ballot_serial'] ?? demoSerial((string) ($message['session_id'] ?? 'SESSION-DEMO')),
    ],
    static function (array $message) use ($signedCodec): array {
        $context = new ElectionContext(
            (string) ($message['election_id'] ?? 'DEMO-2028'),
            (string) ($message['precinct_id'] ?? 'DEMO-PRECINCT-001'),
            (string) ($message['ballot_style_id'] ?? 'DEMO-BS-001'),
            (string) ($message['mapping_hash'] ?? 'demo-mapping-hash'),
        );
        $stock = new BallotStock(new BallotSerial((string) ($message['ballot_serial'] ?? demoSerial((string) ($message['session_id'] ?? 'SESSION-DEMO')))), $context, 'obt-synthetic', (string) ($message['obt_hash'] ?? 'synthetic-obt-hash'), 'assets-demo', 'assets-demo-hash');
        $session = new BallotSession((string) ($message['session_id'] ?? 'SESSION-DEMO'), $stock, [
            'senator' => new ContestDefinition('senator', 2, ['C1', 'C2', 'C3']),
        ], 'demo-authorization');
        foreach ((array) ($message['selections'] ?? []) as $contest => $codes) {
            $session->select((string) $contest, array_values($codes));
        }
        $pdfBytes = base64_decode((string) ($message['pdf_base64'] ?? ''), true);
        if ($pdfBytes === false) {
            throw new InvalidArgumentException('pdf_base64 is invalid.');
        }
        $materializer = new DemoQrPdfMaterializer($pdfBytes, (string) ($message['control_number'] ?? 'DEMO-000001'), new StandardQrCode(new ZbarPngQrCodeDecoder));
        $final = (new FinalizeBallot($signedCodec(), $materializer, new FilesystemWormStore(sys_get_temp_dir().'/ballot-pdf-protocol')))->execute($session);
        $evidence = $final->evidence();
        return [
            'status' => 'finalized',
            'session_id' => $session->sessionId,
            'ballot_serial' => $final->serial,
            'pdf_sha256' => $final->pdfSha256,
            'pdf_base64' => base64_encode($final->pdfBytes),
            'evidence' => $evidence,
            'evidence_manifest_sha256' => (new EvidenceManifest($evidence))->hash(),
            'truth_qr_scans' => $final->truthQrScans,
        ];
    },
    static fn (array $message): array => [
        'status' => 'awaiting-local-printer-adapter',
        'session_id' => $message['session_id'] ?? null,
    ],
    static function (array $message) use ($signedCodec): array {
        $context = new ElectionContext(
            (string) ($message['election_id'] ?? 'DEMO-2028'),
            (string) ($message['precinct_id'] ?? 'DEMO-PRECINCT-001'),
            (string) ($message['ballot_style_id'] ?? 'DEMO-BS-001'),
            (string) ($message['mapping_hash'] ?? 'demo-mapping-hash'),
        );
        $results = (new VerifyDirectory($signedCodec(), new ZbarPdfQrScanner))->execute(sys_get_temp_dir().'/ballot-pdf-protocol', $context);
        $report = (new BuildReports)->json($results);
        $targetSerial = (string) ($message['ballot_serial'] ?? '');
        $target = array_values(array_filter($results, static fn ($result): bool => $targetSerial === '' || $result->serial === $targetSerial));
        return [
            'status' => 'verified',
            'session_id' => $message['session_id'] ?? null,
            'ballot_serial' => $targetSerial,
            'target_results' => array_map(static fn ($result): array => $result->toArray(), $target),
            'recursive_result_count' => count($results),
            'accepted_count' => count(array_filter($results, static fn ($result): bool => $result->eligible)),
            'report_sha256' => hash('sha256', $report),
        ];
    },
);

try {
    $response = $service->handle([...$body, 'message_type' => $body['message_type'] ?? $messageType]);
    http_response_code(200);
} catch (Throwable $exception) {
    $response = [
        'protocol' => '3neti.ballot-pdf',
        'version' => '1.0',
        'message_type' => $body['message_type'] ?? $messageType,
        'status' => 'error',
        'error_code' => 'invalid_request',
        'error_message' => $exception->getMessage(),
    ];
    http_response_code(422);
}

header('content-type: application/json');
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
