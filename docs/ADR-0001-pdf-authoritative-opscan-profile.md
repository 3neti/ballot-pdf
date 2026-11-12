# ADR-0001: PDF-Authoritative OpScan with DRE Profile

## Problem

The ballot-journey documentation was written for a paper-first workflow in
which printing and physical deposit controlled casting. The intended OpScan
with DRE workflow has changed: the finalized ballot PDF is authoritative and
paper output is optional.

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
- optional paper output for inspection or supplementary evidence.

The OMR path remains paper-first and is outside this profile decision.

## Proposed Change

For OpScan with DRE only:

1. The finalized signed ballot PDF is the authoritative ballot record.
2. Durable acceptance of the finalized PDF and evidence manifest is the
   casting event.
3. Printing, paper inspection, physical retention, and paper deposit are
   optional supplementary steps.
4. Post-close verification and tally operate on accepted ballot PDFs and their
   evidence manifests.
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

### Keep paper authoritative for all profiles

Rejected for the OpScan with DRE design because it makes the PDF a preparation
interface rather than the intended electronic ballot record.

### Make paper optional without separating OMR and DRE

Rejected because it would create ambiguous authority rules and could cause an
OMR workflow to be interpreted as PDF-authoritative.

### Treat the Truth QR as the only ballot record

Rejected. The signed QR is the machine-verifiable encoding of the canonical
ballot data, while the complete finalized PDF remains the authoritative ballot
artifact and evidence container.

## Consequences

Positive:

- the voter journey matches the intended PDF-centered design;
- casting does not depend on printer availability;
- tallying can operate from accepted PDFs and evidence packages;
- optional paper output remains available for inspection and demonstrations;
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
- obtain legal and procurement confirmation for the PDF-authoritative profile.

## Recommendation

Adopt this decision for the R&D OpScan with DRE profile. Keep the paper-first
model for OMR and any deployment whose legal or procurement documents require
paper as the controlling record. Do not silently apply the PDF-authoritative
rule outside the explicitly selected profile.
