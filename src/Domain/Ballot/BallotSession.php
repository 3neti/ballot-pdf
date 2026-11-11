<?php

namespace LBHurtado\BallotPdf\Domain\Ballot;

use LBHurtado\BallotPdf\Domain\Context\ContestDefinition;
use LBHurtado\BallotPdf\Domain\Stock\BallotStock;

final class BallotSession
{
    /** @var array<string, list<string>> */
    private array $selections = [];

    public function __construct(
        public readonly string $sessionId,
        public readonly BallotStock $stock,
        /** @var array<string, ContestDefinition> */
        private readonly array $contests,
        public readonly string $authorizationReference,
    ) {
        if (trim($sessionId) === '' || trim($authorizationReference) === '') {
            throw new \InvalidArgumentException('Session references must not be empty.');
        }
    }

    /** @param list<string> $candidateCodes */
    public function select(string $contestId, array $candidateCodes): void
    {
        $contest = $this->contests[$contestId] ?? throw new \DomainException("Unknown contest {$contestId}.");
        $contest->validate($candidateCodes);
        $this->selections[$contestId] = array_values($candidateCodes);
    }

    public function clear(string $contestId): void
    {
        if (! isset($this->contests[$contestId])) {
            throw new \DomainException("Unknown contest {$contestId}.");
        }
        unset($this->selections[$contestId]);
    }

    /** @return array<string, list<string>> */
    public function selections(): array
    {
        $selections = $this->selections;
        ksort($selections);
        return $selections;
    }

    public function validate(): void
    {
        foreach ($this->selections() as $contestId => $codes) {
            ($this->contests[$contestId] ?? throw new \DomainException("Unknown contest {$contestId}."))->validate($codes);
        }
    }
}
