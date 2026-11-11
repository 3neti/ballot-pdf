<?php

namespace LBHurtado\BallotPdf\Infrastructure\Crypto;

use LBHurtado\BallotPdf\Contracts\SecretKeyStore;

final readonly class MacOsKeychainSecretStore implements SecretKeyStore
{
    public function __construct(private string $securityBinary = '/usr/bin/security') {}

    public function get(string $service, string $account): string
    {
        $command = sprintf('%s find-generic-password -s %s -a %s -w 2>/dev/null', $this->securityBinary, escapeshellarg($service), escapeshellarg($account));
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || $output === []) {
            throw new \RuntimeException('OS keychain secret was not available.');
        }
        return trim(implode("\n", $output));
    }
}
