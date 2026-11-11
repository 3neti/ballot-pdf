<?php

namespace LBHurtado\BallotPdf\Infrastructure\Storage;

use LBHurtado\BallotPdf\Contracts\WormStore;
use LBHurtado\BallotPdf\Domain\Evidence\EvidenceManifest;
use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class FilesystemWormStore implements WormStore
{
    public function __construct(private string $root)
    {
        if (! is_dir($root) && ! mkdir($root, 0770, true) && ! is_dir($root)) {
            throw new \RuntimeException("Unable to create evidence root {$root}.");
        }
    }

    public function commitPdf(string $serial, string $pdfBytes): string
    {
        return $this->write($this->path('pdf', $serial.'.pdf'), $pdfBytes);
    }

    public function commitManifest(string $serial, EvidenceManifest $manifest): string
    {
        return $this->write($this->path('manifest', $serial.'.json'), CanonicalJson::encode($manifest->toArray()));
    }

    public function pdfs(): iterable
    {
        $directory = $this->root.'/pdf';
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                yield $file->getPathname();
            }
        }
    }

    private function path(string $directory, string $filename): string
    {
        $directoryPath = $this->root.'/'.$directory;
        if (! is_dir($directoryPath) && ! mkdir($directoryPath, 0770, true) && ! is_dir($directoryPath)) {
            throw new \RuntimeException("Unable to create {$directoryPath}.");
        }
        return $directoryPath.'/'.$filename;
    }

    private function write(string $path, string $bytes): string
    {
        if (file_exists($path)) {
            throw new \RuntimeException("WORM store refuses overwrite: {$path}");
        }
        if (file_put_contents($path, $bytes, LOCK_EX) === false) {
            throw new \RuntimeException("Unable to commit {$path}.");
        }
        return hash('sha256', $bytes);
    }
}
