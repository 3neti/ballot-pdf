# ADR-0001: Canonical Electronic Record for OpScan with DRE Profile

## Problem

The ballot-journey documentation was written for a paper-first workflow in
which printing and physical deposit controlled casting. The intended OpScan
with DRE workflow has changed: the finalized ballot PDF is the proposed
canonical electronic DRE voting record and tally input. Its legal designation
and the applicable paper/VVPAT requirements remain subject to COMELEC approval.
See [the legal-position note](LEGAL_POSITION_DRE_CANONICAL_ELECTRONIC_RECORD.md).
For the corresponding bid, contract, and acceptance-test wording, see that
note's [bid and contract guidance](LEGAL_POSITION_DRE_CANONICAL_ELECTRONIC_RECORD.md#bid-and-contract-guidance).

## Context

The system has two distinct ballot profiles:

- OMR, where physical marks on deposited paper are interpreted; and
- OpScan with DRE, where the voter selects candidate codes in a PDF/DRE
  interface and the finalized signed PDF is verified through its Truth QR.

The previous documentation mixed these profiles and required paper printing,
physical deposit, and post-close paper scanning for the DRE path.

## Current Architecture

The OpScan with DRE path uses:

- a PDF ballot as the voter-facing and accepted ballot artifact;
- a canonical selection record;
- a signed Truth QR payload as the machine-verifiable representation;
- an append-only evidence store;
- deterministic recursive verification; and
- an offered A4 human-readable voter-verification/audit path with the corresponding authenticated Truth QR; COMELEC and the procurement documents must designate the printed artifact's legal role and approve its procedure.

The OMR path remains paper-first and is outside this profile decision.

## Proposed Change

For OpScan with DRE only:

1. The finalized signed ballot PDF is the canonical electronic DRE voting
   record and tally input in this proposed system profile. This is an
   engineering designation, not a claim that it is the statutory “official
   ballot” or that paper/VVPAT requirements are waived.
2. Durable acceptance of the finalized PDF and evidence manifest is the
   casting event.
3. The offered system includes A4 printing, private voter inspection,
   print/read-back verification, mismatch handling, custody, sealing,
   retention, and audit access under the approved voter-verification and
   custody rules. COMELEC and the procurement documents designate whether the
   artifact is the statutory official ballot, a VVPAT, a voter receipt, an
   audit record, or another defined artifact. Prototype optional-print
   behavior is not evidence that statutory VVPAT requirements are met.
4. The proposed post-close electronic tally operates on accepted ballot PDFs
   and their evidence manifests, subject to the approved counting rule and
   discrepancy process.
5. The Truth QR authenticates the canonical ballot data represented by the PDF.
6. A conflict among PDF content, canonical data, and QR payload is flagged and
   adjudicated; it is never silently normalized.
7. OMR retains its separate paper-first authority model.
8. Complete-artifact authentication uses both a PDF-native signature and a
   signed detached manifest containing the complete-PDF hash.
9. The local acceptance ledger is the authoritative cast-event ledger. WORM
   storage is committed before the ledger record; an artifact without an
   acceptance record is excluded from the accepted cast set.
10. Election closure uses a signed certificate containing a cast-set
    commitment, acceptance sequence, tally-rules hash, trust-roster hash, and
    reconciliation totals. A timestamp alone is insufficient.

## Alternatives Considered

### Use paper as the system tally input for all profiles

Not selected as a universal software rule. OMR remains paper-first, while the
proposed DRE profile uses an electronic record as its machine tally input.
That technical distinction does not decide the legal status of each artifact.

### Treat paper as universally optional

Rejected because OMR remains paper-first and the offered DRE system includes an
A4 voter-verification/audit path. The DRE electronic cast boundary is distinct
from paper production, but that does not make the paper path irrelevant or
settle its legal designation.

### Treat the Truth QR as the only voting record

Rejected. The signed QR is the machine-verifiable encoding of the canonical
ballot data, while the complete finalized PDF is the canonical electronic DRE
record and evidence container.

## Consequences

Positive:

- the voter journey matches the intended PDF-centered design;
- casting does not depend on printer availability;
- tallying can operate from accepted PDFs and evidence packages;
- the offered A4 voter-verification/audit path is explicit, while its legal designation and operating rules remain subject to approval;
- OMR and OpScan authority models are explicit and non-conflicting.
- complete-PDF authentication detects transplantation of a valid QR into a
  different PDF;
- acceptance retries and lost acknowledgments have an explicit authority; and
- independent tally reproduction is tied to a signed closed cast set.

Required follow-up:

- update implementation state machines and acceptance tests;
- define PDF/canonical-data/QR conflict handling;
- define privacy controls for copied or exported ballot PDFs;
- define operational retention and publication rules; and
- obtain written legal and procurement confirmation for the proposed DRE
  record/tally rule and paper/VVPAT treatment.

## Recommendation

Adopt this technical decision for the R&D OpScan with DRE profile: the
finalized, authenticated ballot PDF is the canonical electronic DRE voting
record and tally input. Keep the paper-first model for OMR. Before official
deployment, obtain COMELEC's written determination of the DRE artifact
designations, VVPAT procedure, counting rule, and discrepancy treatment. Do not
represent this engineering decision as legal approval or silently apply it
outside the explicitly approved profile.
