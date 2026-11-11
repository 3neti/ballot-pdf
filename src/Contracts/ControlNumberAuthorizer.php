<?php

namespace LBHurtado\BallotPdf\Contracts;

interface ControlNumberAuthorizer
{
    /** @return array{reference: string, ballot_serial: string} */
    public function authorize(string $controlNumber): array;
}
