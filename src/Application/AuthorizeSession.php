<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Contracts\BallotStockRepository;
use LBHurtado\BallotPdf\Contracts\ControlNumberAuthorizer;
use LBHurtado\BallotPdf\Domain\Ballot\BallotSession;
use LBHurtado\BallotPdf\Domain\Context\ContestDefinition;

final readonly class AuthorizeSession
{
    /** @param array<string, ContestDefinition> $contests */
    public function __construct(
        private ControlNumberAuthorizer $authorizer,
        private BallotStockRepository $stocks,
        private array $contests,
    ) {}

    public function execute(string $sessionId, string $controlNumber): BallotSession
    {
        $authorization = $this->authorizer->authorize($controlNumber);
        $stock = $this->stocks->reserve($authorization['ballot_serial']);
        return new BallotSession($sessionId, $stock, $this->contests, $authorization['reference']);
    }
}
