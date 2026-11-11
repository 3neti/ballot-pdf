<?php

namespace LBHurtado\BallotPdf\Domain\Context;

final readonly class ContestDefinition
{
    /** @param list<string> $candidateCodes */
    public function __construct(
        public string $contestId,
        public int $maximumSelections,
        public array $candidateCodes,
    ) {
        if ($this->maximumSelections < 0 || count(array_unique($this->candidateCodes)) !== count($this->candidateCodes)) {
            throw new \InvalidArgumentException('Contest definition is invalid.');
        }
        if ($this->maximumSelections > count($this->candidateCodes)) {
            throw new \InvalidArgumentException('Contest limit exceeds candidate count.');
        }
    }

    /** @param list<string> $selectedCodes */
    public function validate(array $selectedCodes): void
    {
        if (count($selectedCodes) > $this->maximumSelections) {
            throw new \DomainException("Contest {$this->contestId} exceeds its selection limit.");
        }
        if (array_diff($selectedCodes, $this->candidateCodes) !== []) {
            throw new \DomainException("Contest {$this->contestId} contains an unknown candidate.");
        }
        if (count(array_unique($selectedCodes)) !== count($selectedCodes)) {
            throw new \DomainException("Contest {$this->contestId} contains duplicate selections.");
        }
    }
}
