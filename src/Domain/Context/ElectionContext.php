<?php

namespace LBHurtado\BallotPdf\Domain\Context;

final readonly class ElectionContext
{
    public function __construct(
        public string $electionId,
        public string $precinctId,
        public string $ballotStyleId,
        public string $mappingHash,
        public string $tabulationProfile = 'paper-first',
    ) {
        foreach (['electionId' => $electionId, 'precinctId' => $precinctId, 'ballotStyleId' => $ballotStyleId, 'mappingHash' => $mappingHash] as $name => $value) {
            if (trim($value) === '') {
                throw new \InvalidArgumentException("{$name} must not be empty.");
            }
        }
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'election_id' => $this->electionId,
            'precinct_id' => $this->precinctId,
            'ballot_style_id' => $this->ballotStyleId,
            'mapping_hash' => $this->mappingHash,
            'tabulation_profile' => $this->tabulationProfile,
        ];
    }
}
