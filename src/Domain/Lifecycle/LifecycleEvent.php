<?php

namespace LBHurtado\BallotPdf\Domain\Lifecycle;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class LifecycleEvent
{
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $serial,
        public string $contextHash,
        public int $sequence,
        public string $actorRole,
        public ?string $artifactHash = null,
        public ?string $previousEventHash = null,
    ) {}

    public function hash(): string
    {
        return hash('sha256', CanonicalJson::encode($this->toArray()));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
