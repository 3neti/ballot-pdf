<?php

namespace LBHurtado\BallotPdf\Contracts;

use LBHurtado\BallotPdf\Domain\Lifecycle\LifecycleEvent;

interface AppendOnlyJournal
{
    public function append(LifecycleEvent $event): void;

    /** @return list<LifecycleEvent> */
    public function events(string $serial): array;
}
