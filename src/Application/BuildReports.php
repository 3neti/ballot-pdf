<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Domain\Verification\VerificationResult;
use LBHurtado\BallotPdf\Support\CanonicalJson;

final class BuildReports
{
    /** @param list<VerificationResult> $results */
    public function json(array $results): string
    {
        return CanonicalJson::encode([
            'report_type' => 'ballot-pdf-verification',
            'results' => array_map(static fn (VerificationResult $result): array => $result->toArray(), $results),
            'accepted_count' => count(array_filter($results, static fn (VerificationResult $result): bool => $result->eligible)),
        ]);
    }

    /** @param list<VerificationResult> $results */
    public function html(array $results): string
    {
        $rows = implode('', array_map(static fn (VerificationResult $result): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            htmlspecialchars($result->serial, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($result->classification, ENT_QUOTES, 'UTF-8'),
            $result->eligible ? 'yes' : 'no',
            htmlspecialchars(implode('; ', $result->issues), ENT_QUOTES, 'UTF-8'),
        ), $results));
        return '<!doctype html><meta charset="utf-8"><title>Ballot PDF Verification</title><table><thead><tr><th>Serial</th><th>Classification</th><th>Eligible</th><th>Issues</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }
}
