# TOR v34 Compliance Delta and SDK Criteria

## 1. Purpose

This document identifies the functional requirements that `ballot-pdf` must add or adjust to support the OpScan-with-DRE pathway in `TOR_FASTrAC_2028_NLE_v34.pdf`.

The target bid model is the TOR-native **Official Ballot Template (OBT) mode**:

```text
Signed OBT PDF
    -> authorized ACM import
    -> voter session and selection enforcement
    -> one serial allocated from an approved range
    -> finalized per-voter Ballot PDF
    -> signed QR payload
    -> precinct laser printing
    -> print-time read-back and validation
    -> paper ballot deposit and reconciliation
```

Pre-generating one PDF for every estimated ballot is not treated as a mandatory TOR requirement unless COMELEC clarifies it separately.

The bid should separately and explicitly propose the role of the finalized
ballot PDF as the canonical electronic DRE record/tally input and the role of
the human-readable A4/Truth QR print as voter-verification and audit evidence.
Do not imply that either role is accepted merely because the package can
generate a PDF or QR. Use the contract/acceptance guidance in the
[legal-position note](LEGAL_POSITION_DRE_CANONICAL_ELECTRONIC_RECORD.md).

## 2. Functional Specification Changes

### 2.1 Replace the generic stock model with two explicit artifact types

The package must distinguish:

1. **Official Ballot Template (OBT)**
   - one approved PDF master per ballot-style scope;
   - digitally signed;
   - hash-locked;
   - pre-numbered or associated with approved serial ranges;
   - delivered only to authorized ACM devices; and
   - treated as controlled election material.

2. **Finalized Ballot PDF**
   - produced for one voter session from the OBT;
   - assigned one unused serial;
   - contains confirmed selections;
   - contains the signed machine-readable payload;
   - is printed at the precinct; and
   - is retained as electronic evidence after finalization.

The package must not imply that the OBT itself contains voter selections.

### 2.2 Add OBT approval and custody

The package must support:

- deterministic OBT generation;
- COMELEC-approved ballot-style identity;
- OBT hash computation;
- OBT digital signature;
- witnessed or controlled generation metadata;
- one-time generation and hash lock;
- authorized-device and precinct scope;
- signed OBT manifest;
- serial-range manifest;
- encrypted transfer package;
- import verification;
- custody events; and
- rejection of modified, stale, wrong-scope, or unknown OBTs.

### 2.3 Add serial-range issuance

The package must allocate one serial per authorized voting session.

Serial states must include at least:

- available;
- reserved;
- issued;
- printed;
- spoiled;
- cancelled;
- cast/deposited;
- scanned;
- accepted;
- rejected;
- voided; and
- unused at close.

No serial may be reused or reissued. A reprint receives a new serial and the prior serial remains accounted for.

### 2.4 Add per-voter PDF materialization

The package must materialize a final PDF from the OBT after the voter confirms selections. It must:

- preserve the approved OBT layout;
- render the human-readable selections;
- render the ballot serial and required context;
- render the complete signed QR payload;
- preserve print margins and QR density;
- compute the final PDF hash;
- commit the final PDF to the configured evidence store; and
- emit a print authorization only after successful finalization.

### 2.5 Add print-time verification

The package must support:

- immediate read-back of the printed QR payload;
- signature and context validation;
- serial and duplicate validation;
- print-fault classification;
- sample verification on separate imagers before deployment;
- paper and payload agreement evidence; and
- print-status reconciliation.

The print-time read-back must be distinguishable from the official post-close tally event. The package must not create a second tally contribution when the same final ballot is later scanned for counting.

### 2.6 Add BITS-compatible status reporting

The package must export per-precinct and aggregate reports for:

- OBTs imported;
- serials available;
- serials issued;
- PDFs materialized;
- ballots printed;
- print failures;
- spoiled ballots;
- cancelled ballots;
- reprints;
- QR read-back results;
- ballots deposited;
- ballots scanned;
- accepted ballots;
- rejected ballots;
- unresolved ballots;
- voter receipts printed and deposited, where required; and
- unused or voided serials.

### 2.7 Preserve contest-limit enforcement

The voter terminal must enforce:

- contest selection ceilings;
- intentional undervoting;
- correction before confirmation;
- invalid selection rejection at finalization; and
- independent revalidation during verification.

The PDF renderer or SDK may present the ballot, but the domain layer remains authoritative.

### 2.8 Add required ACM integration facts

The package must expose integration contracts for:

- at least ten simultaneously usable voter terminals;
- full-page laser printing;
- voter receipt printing where required by the issued TOR;
- two-dimensional optical imaging;
- local operator/EB control;
- local evidence storage;
- controlled local connectivity;
- 14-hour complete-package operation testing; and
- offline operation after commissioning.

The package does not own hardware drivers, battery management, or physical custody, but it must accept their results as signed or journaled lifecycle events.

### 2.9 Add portable-media package handling

The package must support controlled import/export packages containing:

- OBT files;
- serial ranges;
- signed manifests;
- trust-roster references;
- mapping identity;
- precinct scope;
- device scope;
- file hashes;
- package hash;
- creation and custody events; and
- import verification results.

### 2.10 Preserve independent verification

The recursive verifier must validate:

- OBT signature and hash;
- finalized Ballot PDF payload;
- PDF and payload relationship;
- ballot serial uniqueness;
- election and precinct context;
- mapping hash;
- signature trust status;
- lifecycle status;
- cancellation/spoilage status;
- exact copies and conflicting serials; and
- tally eligibility.

## 3. Requirements That Must Stay Outside the Package

The following remain consuming-application or COMELEC responsibilities:

- voter eligibility and paper-ledger admission;
- official Electoral Board ceremonies;
- the legally controlling record;
- physical ballot-box custody;
- final adjudication of paper/payload disagreement;
- COMELEC form layout and official ER policy;
- final transmission policy; and
- public disclosure of ballot-level evidence.

## 4. SDK Selection Criteria

The SDK must be evaluated against the following functional requirements, in this order:

### 4.1 Deterministic output

- identical OBT input produces reproducible PDF bytes or a reproducible canonical content hash;
- embedded fonts are controlled;
- page dimensions and margins are stable;
- QR placement and density are stable;
- output is printable on the offered laser printer; and
- PDF metadata does not carry uncontrolled mutable election meaning.

### 4.2 Offline and appliance deployment

- no cloud call required during voting or printing;
- on-premise or self-contained deployment;
- local license validation or offline license file;
- no mandatory analytics or telemetry connection;
- operation on the target appliance OS;
- reproducible installation package; and
- documented long-term version support.

### 4.3 Browser/table integration

- high-fidelity rendering in the controlled browser runtime;
- reliable page navigation and zoom;
- form or overlay support if used;
- keyboard and accessibility support;
- controlled print invocation;
- local file/blob loading; and
- predictable behavior across the supported browser version.

### 4.4 PDF generation and finalization

- OBT loading;
- per-session field or overlay materialization;
- flattened final PDF generation;
- QR/image placement;
- metadata control;
- encryption;
- read-only/finalized output;
- incremental or detached signature support; and
- error reporting without silent document mutation.

### 4.5 Cryptographic integration

The SDK must not replace the package's canonical signed payload layer. It must permit:

- signed Truth QR payload insertion;
- PDF hash computation after final rendering;
- required PDF-native signature adapter boundary;
- independent verification outside the SDK; and
- preservation of signature and payload bytes after printing.

The vendor must document the approved PDF-native signature profile and its
validation behavior. The OpScan with DRE profile requires both the PDF-native
signature and a signed detached manifest containing the complete-PDF hash. The
Truth QR payload remains the authenticated canonical-selection encoding.

### 4.6 High-volume generation

The SDK must be tested for:

- batch OBT generation;
- stream-oriented output;
- memory boundedness;
- parallel workers;
- deterministic results across workers;
- crash recovery;
- resumable batch runs;
- manifest checkpoints; and
- output validation at scale.

The first production model does not require generating 83,510,241 PDFs in advance, but the SDK must support the estimated 83,510,241 printed-ballot workload and the required test/demo/training quantities.

### 4.7 Verification and interoperability

- open finalized PDFs in Acrobat, Preview, PDF.js, and the approved WAES viewer;
- print consistently from the approved runtime;
- retain the QR payload after reopening;
- reject or visibly identify modifications;
- expose predictable text and metadata extraction; and
- permit independent verifier operation without the SDK.

## 5. SDK Candidates

### 5.1 Adobe Acrobat-PDFL SDK

**Strengths:** strong PDF generation, rendering, forms, encryption, and digital-signature capabilities; suitable for embedded/on-premise applications.  
**Risks:** licensing and deployment complexity; Acrobat-specific JavaScript should not be the only ballot-runtime behavior; exact Ed25519 PDF-signature support must be confirmed.  
**Fit:** strong candidate for a native appliance service or production PDF engine.

### 5.2 Nutrient Web SDK

**Strengths:** client-side browser processing, forms, signatures, encryption, WebAssembly deployment, and customizable UI.  
**Risks:** commercial licensing; needs offline licensing and large-volume generation validation.  
**Fit:** strong candidate for the controlled browser/table runtime.

### 5.3 Apryse WebViewer/PDF SDK

**Strengths:** browser, desktop, server, forms, signatures, custom UI, and on-premise deployment options.  
**Risks:** commercial licensing and a larger integration surface.  
**Fit:** strong full-stack candidate if one vendor must cover browser and native/service workloads.

### 5.4 Foxit Web/Desktop SDK

**Strengths:** browser WebAssembly viewer, native desktop SDK, forms, signatures, encryption, and custom viewer capabilities.  
**Risks:** commercial licensing; verify offline activation and PHP/Node integration boundary.  
**Fit:** strong alternative to Adobe for a controlled appliance runtime.

### 5.5 MuPDF.js or MuPDF SDK

**Strengths:** fast embedded rendering and browser/native deployment; suitable for controlled appliance work.  
**Risks:** more custom UI, form, signing, and workflow engineering.  
**Fit:** good engine when WAES wants maximum runtime control.

### 5.6 PDF.js plus a generation/signing library

**Strengths:** open browser renderer and no vendor viewer license.  
**Risks:** more engineering, more compatibility responsibility, and separate PDF generation/signature components.  
**Fit:** good fallback if the final voter interface remains WAES-native and PDF is primarily an artifact format.

## 6. Recommended Evaluation Path

Do not choose the SDK from feature lists alone. Run the same proof-of-concept against Adobe-PDFL, Nutrient or Apryse, and Foxit, with MuPDF/PDF.js as a control.

The proof-of-concept must:

1. Generate one signed OBT.
2. Open it in the controlled browser runtime.
3. Run ten concurrent voter-terminal sessions.
4. Apply contest limits and intentional undervoting.
5. Materialize a finalized per-voter PDF.
6. Render the signed QR payload.
7. Print the PDF using the proposed laser printer.
8. Read back the printed payload.
9. Reopen the final PDF in Acrobat, Preview, PDF.js, and the WAES viewer.
10. Detect a modified PDF or altered payload.
11. Produce a deterministic PDF hash and evidence manifest.
12. Run the recursive independent verifier without the SDK.
13. Repeat the batch under power interruption and process restart.
14. Measure generation time, memory, PDF size, print time, and validation time.

## 7. Provisional Recommendation

Until the proof-of-concept is complete:

- keep the `ballot-pdf` package independent of every vendor SDK;
- define `PdfRenderer`, `PdfGenerator`, `PdfInspector`, `PdfSigner`, and `PdfPrinterAdapter` ports;
- keep `truth-qr` authoritative for ballot payload signing and verification;
- use the controlled WAES voter UI for contest-limit enforcement;
- use the SDK for PDF rendering, materialization, optional printing, and the commissioned PDF-native signing adapter; and
- keep standard PDF viewers as read-only audit/rendering clients.

The current leading candidates are Nutrient or Apryse for the controlled browser/table runtime, Adobe Acrobat-PDFL for a native embedded engine, and Foxit as the strongest commercial alternative. The final choice should follow the proof-of-concept, not precede it.

## 8. Decision Gate

Before selecting an SDK, approve:

- OBT mode as the bid baseline;
- per-voter Finalized Ballot PDF as the electronic output;
- the signed QR payload as the authoritative ballot-authentication layer;
- PDF-level signing as optional or supplementary;
- browser/table interaction as a controlled WAES runtime;
- Acrobat, Preview, PDF.js, and external viewers as audit/rendering clients; and
- the volume test plan for the 83,510,241-ballot requirement.

## 9. Bid response and contract acceptance

The technical proposal and contract annexes should state, test, and make
traceable the following profile commitments:

| Bid commitment | Required proposal detail and acceptance evidence |
| --- | --- |
| Canonical electronic DRE record | Finalized authenticated ballot PDF is identified as the proposed voting record and precinct tally input; define cast boundary, PDF/canonical-data/QR binding, context, serial, retention, and eligible-set formation. |
| A4 voter-verification paper | Human-readable selected candidates plus a Truth QR; describe voter inspection, privacy, paper handling, sealing, retention, and audit access. Request COMELEC approval of the artifact's role and final format. |
| QR-assisted audit | Define scanner operation, human review/confirmation, independent tally, variance handling, and how the process relates to the applicable RMA instructions. Identify any required supplemental rule. |
| Discrepancies and failures | Specify actions for PDF/paper/QR mismatch, print failure, missing or duplicate serial, unreadable QR, and voter objection; preserve evidence and identify authorized disposition. |
| End-to-end acceptance | Use objective witnessed tests for selection-to-record fidelity, voter verification, PDF/QR/paper consistency, deterministic tally, audit, privacy, custody, offline recovery, and independent verification. |
| Legal and operational gates | Identify COMELEC determinations, AES certification, security testing, procurement clarifications, and final approvals required before official use; do not treat bid award as a substitute for any separately required gate. |

Winning the procurement can establish the accepted contract scope only for
requirements that were evaluated and incorporated into the signed contract
(including incorporated technical annexes), subject to the bidding documents'
order of precedence. It does not waive statutory safeguards or automatically
approve procedures outside the contracted scope. If the issued TOR does not
clearly support a proposed detail, request a written clarification or bid
bulletin and reflect the answer in the bid rather than asserting compliance by
implication. See the [legal-position note](LEGAL_POSITION_DRE_CANONICAL_ELECTRONIC_RECORD.md).
