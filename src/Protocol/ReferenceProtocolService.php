<?php

namespace LBHurtado\BallotPdf\Protocol;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final class ReferenceProtocolService
{
    /** @var array<string, array<string, mixed>> */
    private array $sessions = [];

    /** @param callable(array<string, mixed>): array<string, mixed> $openSession @param callable(array<string, mixed>): array<string, mixed> $finalize @param callable(array<string, mixed>): array<string, mixed> $print @param (callable(array<string, mixed>): array<string, mixed>)|null $verify @param (callable(array<string, mixed>): array<string, mixed>)|null $electionReturn @param (callable(array<string, mixed>): array<string, mixed>)|null $accept @param (callable(array<string, mixed>): array<string, mixed>)|null $close */
    public function __construct(
        private readonly mixed $openSession,
        private readonly mixed $finalize,
        private readonly mixed $print,
        private readonly mixed $verify = null,
        private readonly mixed $electionReturn = null,
        private readonly mixed $accept = null,
        private readonly mixed $close = null,
    ) {}

    /** @param array<string, mixed> $envelope @return array<string, mixed> */
    public function handle(array $envelope): array
    {
        $type = (string) ($envelope['message_type'] ?? '');
        $this->assertEnvelope($envelope, $type);
        $result = match ($type) {
            'session.open' => ($this->openSession)($envelope),
            'session.update' => $this->update($envelope),
            'ballot.finalize' => ($this->finalize)($envelope),
            'acceptance.commit' => $this->accept === null ? throw new \InvalidArgumentException('Acceptance commit is unavailable.') : ($this->accept)($envelope),
            'ballot.print' => ($this->print)($envelope),
            'evidence.verify' => $this->verify === null ? throw new \InvalidArgumentException('Evidence verification is unavailable.') : ($this->verify)($envelope),
            'election.return' => $this->electionReturn === null ? throw new \InvalidArgumentException('Election Return generation is unavailable.') : ($this->electionReturn)($envelope),
            'election.close' => $this->close === null ? throw new \InvalidArgumentException('Election close is unavailable.') : ($this->close)($envelope),
            'evidence.commit' => ['status' => 'evidence_acknowledged'],
            'ceremony.status' => ['status' => 'ready'],
            default => throw new \InvalidArgumentException("Unsupported protocol message {$type}."),
        };
        return [
            'protocol' => '3neti.ballot-pdf',
            'version' => $envelope['version'],
            'message_type' => $type,
            'request_id' => $envelope['request_id'],
            'nonce' => $envelope['nonce'],
            ...$result,
        ];
    }

    /** @param array<string, mixed> $envelope @return array<string, mixed> */
    private function update(array $envelope): array
    {
        $sessionId = (string) ($envelope['session_id'] ?? '');
        if ($sessionId === '') {
            throw new \InvalidArgumentException('session_id is required.');
        }
        $this->sessions[$sessionId] = $envelope;
        return ['status' => 'session_updated', 'state_hash' => hash('sha256', CanonicalJson::encode($envelope))];
    }

    /** @param array<string, mixed> $envelope */
    private function assertEnvelope(array $envelope, string $type): void
    {
        foreach (['protocol', 'version', 'message_type', 'request_id', 'nonce'] as $field) {
            if (! isset($envelope[$field]) || trim((string) $envelope[$field]) === '') {
                throw new \InvalidArgumentException("{$field} is required.");
            }
        }
        if ($envelope['protocol'] !== '3neti.ballot-pdf' || ! in_array($envelope['version'], ['1.0', '1.1'], true) || $type !== $envelope['message_type']) {
            throw new \InvalidArgumentException('Protocol envelope is invalid.');
        }
    }
}
