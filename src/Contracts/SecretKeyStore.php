<?php

namespace LBHurtado\BallotPdf\Contracts;

interface SecretKeyStore
{
    public function get(string $service, string $account): string;
}
