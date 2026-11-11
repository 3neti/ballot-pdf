<?php

namespace LBHurtado\BallotPdf\Infrastructure\Verification;

use LBHurtado\BallotPdf\Contracts\PdfQrScanner;

final class ZbarPdfQrScanner implements PdfQrScanner
{
    public function __construct(private readonly string $pdftoppm = 'pdftoppm', private readonly string $zbarimg = 'zbarimg') {}

    public function scans(string $pdfPath): array
    {
        $directory = sys_get_temp_dir().'/ballot-pdf-scan-'.bin2hex(random_bytes(4));
        mkdir($directory, 0700, true);
        $prefix = $directory.'/page';
        $render = sprintf('%s -png -r 180 %s %s 2>&1', escapeshellarg($this->pdftoppm), escapeshellarg($pdfPath), escapeshellarg($prefix));
        exec($render, $renderOutput, $renderCode);
        if ($renderCode !== 0) {
            $this->cleanup($directory);
            throw new \RuntimeException('Unable to render PDF for QR read-back: '.implode("\n", $renderOutput));
        }
        $scans = [];
        foreach (glob($prefix.'-*.png') ?: [] as $png) {
            $command = sprintf('%s --quiet --raw %s 2>/dev/null', escapeshellarg($this->zbarimg), escapeshellarg($png));
            exec($command, $output, $exitCode);
            if ($exitCode === 0) {
                foreach ($output as $scan) {
                    if (trim($scan) !== '') $scans[] = trim($scan);
                }
            }
            $output = [];
        }
        $this->cleanup($directory);
        return array_values(array_unique($scans));
    }

    private function cleanup(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $path) @unlink($path);
        @rmdir($directory);
    }
}
