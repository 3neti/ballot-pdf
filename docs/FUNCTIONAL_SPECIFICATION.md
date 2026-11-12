# Ballot PDF Functional Specification

## 1. Document Control

| Field | Value |
| --- | --- |
| Package | `3neti/ballot-pdf` |
| Status | Design draft for review before scaffolding |
| Primary purpose | Serialized electronic ballot stock, finalized ballot PDFs, immutable evidence, and independent verification |
| Intended first domain | Offline OpScan with DRE ballot preparation |
| Primary runtime | Local election appliance with Nutrient Web SDK rendering the interactive ballot PDF in an offline browser kiosk |
| DRE record terminology | In the proposed OpScan with DRE profile, the finalized signed ballot PDF is the canonical electronic DRE voting record and proposed tally input; legal designation and paper/VVPAT treatment require COMELEC determination |
| Security posture | Offline-first, cryptographically authenticated, append-only, independently verifiable |

This document describes the behavior and boundaries of the package. It is not an implementation plan, a COMELEC certification, or a claim that every capability is already available in the WAES application.

### Legal status and record terminology

Throughout this specification, “canonical electronic DRE voting record and
tally input” describes the proposed system architecture. It does not assert
that a PDF is the statutory “official ballot,” or that optional printing
satisfies the voter-verified paper audit trail requirement. RA 9369 defines the
official ballot as paper, separately defines DRE electronic voting data, and
requires VVPAT capability. RA 8792 recognizes qualifying electronic documents
and signatures but preserves existing-law formalities. COMELEC Resolution No.
11089 treats DRE VVPAT receipts as ballot equivalents for purposes of that
RMA resolution. See the [legal-position note](LEGAL_POSITION_DRE_CANONICAL_ELECTRONIC_RECORD.md)
for citations, scope, and questions requiring COMELEC determination.

## 2. Executive Intent

`ballot-pdf` provides a portable ballot-artifact model in which each ballot instance is represented by an individually serialized PDF rather than by one generic precinct PDF.

The v34 FASTrAC TOR introduces an important distinction. Its default OpScan wording treats the EMS-generated PDF as a digitally signed, pre-numbered Official Ballot Template (OBT), delivered to authorized precinct devices and used to print a ballot for each voter. This package therefore supports two supply modes:

- **OBT mode:** one locked, signed PDF master per ballot style or approved precinct scope, with controlled serial ranges issued at the precinct; and
- **Serialized stock mode:** one pre-generated PDF artifact per ballot serial, transferred and imported as controlled electronic ballot stock.

OBT mode is the model expressly described by the v34 TOR. Serialized stock mode is the stronger interpretation of the proposed digital-ballot model and requires COMELEC to confirm that pre-generating and transporting individual per-serial PDFs is permitted. The package must not confuse the two modes.

The package supports the following lifecycle:

```text
Election configuration
    -> Ballot PDF stock generation
    -> Signed stock manifest and controlled media transfer
    -> Precinct import and validation
    -> Control-number-authorized voter session
    -> Nutrient-rendered interactive ballot PDF
    -> Contest-limit enforcement and voter confirmation
    -> Finalized signed Ballot PDF
    -> WORM/equivalent immutable storage
    -> Optional paper output and optional physical retention
    -> Optical payload verification
    -> Independent recursive verification and reporting
```

The package treats the PDF as a first-class, visually auditable election artifact. It does not treat the PDF viewer, file name, directory name, or ordinary PDF metadata as a trusted election authority.

## 3. Design Principles

1. For the proposed OpScan with DRE profile, the finalized signed Ballot PDF is the canonical electronic DRE voting record and proposed tally input. This technical rule does not itself establish statutory “official ballot” status.
2. Paper output, voter verification, custody, and audit functions must follow the COMELEC-approved profile. The prototype's optional-print setting does not establish that statutory VVPAT requirements are met.
3. Every ballot instance has a stable, unique ballot serial.
4. Control-number authorization and ballot authentication are separate security functions.
5. Voter identity, control numbers, print PINs, and voter-linked authorization values do not appear in the public signed ballot payload.
6. A final ballot artifact is immutable after voter confirmation.
7. A cancelled, spoiled, unused, rejected, or superseded ballot remains accounted for and is never silently deleted.
8. Counting is based on signed payload verification, ballot status, context, uniqueness, and approved custody evidence, not filenames or PDF metadata.
9. All required operations work offline after the election package has been commissioned.
10. The package exposes independent verification interfaces so a municipality, auditor, party, or candidate can reproduce verification and reporting without the ballot-preparation application.
11. The package must distinguish PDF acceptance from optional printing, physical handling, and paper inspection.
12. The package must not require an Internet lookup, proprietary server, or live cloud service to interpret a finalized ballot.

## 4. Scope

### 4.1 In scope

- ballot-stock PDF generation;
- per-ballot serial assignment;
- ballot-style and precinct binding;
- signed stock manifests;
- controlled import from removable media;
- PDF encryption or protected-container integration;
- control-number-authorized session release;
- ballot interaction state and contest-limit rules;
- browser-rendered voter experience contracts;
- finalized Ballot PDF creation;
- signed machine-readable payload generation;
- PDF-native signature integration through a commissioned adapter;
- immutable/WORM storage contracts;
- append-only lifecycle events;
- recursive directory verification;
- duplicate, conflict, status, and context classification;
- deterministic tally input generation;
- evidence manifests and verification reports; and
- independent command-line or library verification.

### 4.2 Out of scope for the core package

- voter registration and voter eligibility decisions;
- Electoral Board legal ceremonies;
- the paper ballot as a physical object;
- physical ballot-box custody;
- COMELEC-specific candidate data ingestion;
- the complete EMS user interface;
- transmission policy or network routing;
- a national trust-root governance authority;
- a PDF viewer implementation;
- guaranteeing behavior of arbitrary PDF JavaScript in Acrobat, Preview, or browser viewers;
- legal determination of whether a ballot is valid when paper and payload disagree; and
- final election-law policy for public disclosure of ballot-level PDFs.

## 5. Terminology

### 5.1 Ballot PDF Stock

A pre-election PDF artifact used as controlled electronic ballot material. In OBT mode it is the locked, signed official ballot master with approved serial ranges. In serialized stock mode it is an individually serialized PDF ballot instance. In both modes it contains an approved ballot face, precinct and ballot-style context, and machine-readable stock metadata. It does not contain voter selections.

### 5.1.1 Official Ballot Template (OBT)

The COMELEC-approved, digitally signed, pre-numbered electronic master from which an OpScan ballot PDF is generated and, when requested, printed. The OBT is generated once, hash-locked, delivered only to authorized devices, and governed as controlled election material. A serial allocated from the OBT is single-use.

The OBT may contain a non-secret `bei_authorization_policy_id` or equivalent
policy reference identifying the approved offline BEI authorization rules.
The OBT must not contain a BEI PIN, PIN verifier, private signing key, or
reusable authorization secret.

### 5.1.2 Serialized Ballot Stock PDF

An optional serialized-stock artifact containing one approved blank ballot face and one assigned ballot serial. It is a packaging and distribution optimization only if COMELEC approves it. It must obey the same encryption, hash, serial, spoilage, cancellation, and retention rules as an OBT-derived ballot. Any printing, voter inspection, custody, or audit role must follow the COMELEC-approved OpScan with DRE profile and must not be presumed supplementary.

### 5.2 Ballot Session

The control-number-authorized interaction in which a voter is shown an eligible ballot, makes selections, reviews them, and confirms or abandons the session.

### 5.3 Finalized Ballot PDF

The read-only electronic ballot artifact produced after voter confirmation. It contains the human-readable selected ballot representation and a signed machine-readable payload. It is ready for complete-artifact authentication, durable acceptance, optional printing, evidence storage, and independent verification.

### 5.4 Ballot serial

The unique identifier of a ballot instance. It is the primary duplicate and lifecycle identity. It is not a voter identity and must not be derived from a control number in a reversible manner.

### 5.5 Control number

A voter-admission or ballot-session authorization credential. It may authorize access to a ballot style or ballot-stock instance, but it is not the ballot signature and is not part of the public final-ballot payload.

### 5.6 Signed payload

The canonical machine-readable representation authenticated by an asymmetric digital signature. It binds the ballot to its election context and selection content.

### 5.7 Truth QR

The WAES product term for the signed, machine-readable payload rendered as one or more two-dimensional optical symbols. The package should keep this as a profile or label rather than making it the only possible payload name.

### 5.8 WORM storage

Write-once-read-many or an equivalent commissioned storage control that prevents alteration or deletion of finalized ballot artifacts and their evidence without detection.

### 5.9 Ballot PDF protocol

The protocol is the canonical interaction contract between the self-contained
ballot PDF and a commissioned local service. It carries session, selection,
finalization, printing, storage, custody, and status messages. Protocol
messages use canonical JSON envelopes and are bound to the election context,
ballot serial, session, nonce, and OBT hash.

### 5.10 Acceptance ledger

The authoritative acceptance ledger records the atomic cast decision after the
complete signed ballot bundle has been committed. It records the ballot serial,
complete-PDF hash, payload hash, authenticated manifest hash, idempotency key,
acceptance sequence, timestamp, context, and status. A WORM store preserves
artifacts but does not independently decide acceptance.

### 5.11 Election Close Certificate

The signed close certificate commits the election or precinct scope to an
authenticated accepted cast set. It includes the close timestamp, last
acceptance sequence, accepted serials or an equivalent authenticated set
commitment, tally-rule hash, trust-roster hash, reconciliation totals, and
required signatures. A timestamp alone is not election closure.

## 6. Package Boundary and Dependencies

### 6.1 Core package responsibilities

The package owns the domain model and deterministic operations for Ballot PDF artifacts. It should expose framework-neutral PHP services and value objects.

### 6.2 Integration responsibilities

The consuming election application owns:

- election configuration;
- voter admission;
- control-number issuance;
- Electoral Board ceremonies;
- Nutrient-powered PDF/UI integration;
- local printer and scanner adapters;
- storage-device custody;
- COMELEC forms and reports;
- physical ballot handling; and
- official legal adjudication.

### 6.3 Proposed package dependencies

- `3neti/truth-qr` for canonical payloads, signatures, verification, and multipart optical encoding;
- a PDF generation adapter selected by the consuming application;
- a PDF inspection/rendering adapter selected by the consuming application;
- PHP Sodium or an approved cryptographic provider;
- filesystem or removable-media adapters supplied by the consuming application.

The ACM integration should be supplied by a separate frontend adapter,
`@3neti/ballot-pdf-nutrient` or an equivalent approved package. It adapts the
Nutrient Web SDK to the ballot session and finalization ports without moving
election rules into the viewer SDK. Nutrient assets and an offline license are
provisioned with the ACM; ordinary voting must not require Nutrient cloud
services or Internet access.

The package must not duplicate Truth QR signing or verification logic where the shared package already provides the required profile.

## 7. Actors and Trust Boundaries

| Actor | Responsibility | Trust boundary |
| --- | --- | --- |
| EMS operator | Creates election configuration, ballot styles, mapping, serial pools, and stock manifests | Authorized configuration ceremony |
| Stock custodian | Transfers and receives portable ballot-stock media | Physical custody and manifest verification |
| ACM operator | Imports stock, opens authorized sessions, prints ballots, records lifecycle events | Commissioned precinct appliance |
| Voter | Makes and confirms selections | Private voter session |
| Electoral Board | Authorizes opening, reset, closeout, custody, and official actions | Multi-party ceremony and signed/journaled approvals |
| CCS operator | Verifies and receives Election Returns and evidence packages | Separate receiving system |
| Auditor/watcher | Inspects permitted artifacts and independently verifies reports | Read-only disclosure boundary |
| Independent verifier | Validates PDFs, signatures, manifests, statuses, and tally inputs | Offline trust package |
| Storage administrator | Maintains WORM/equivalent storage | Infrastructure control, not ballot authority |

## 8. Artifact Model

### 8.1 Ballot stock artifact

Each stock artifact must include or be associated with:

- ballot serial;
- election identifier;
- precinct identifier;
- ballot-style identifier;
- candidate/contest mapping identity;
- stock version;
- issuing authority or stock signer key ID;
- payload schema version;
- creation timestamp or ceremony sequence, where allowed;
- PDF byte hash or canonical ballot-content hash; and
- stock status.

The stock artifact must not contain voter selections or voter identity.

### 8.2 Finalized Ballot PDF artifact

Each finalized artifact must provide:

- human-readable ballot face;
- election and precinct context;
- ballot style;
- ballot serial;
- selected candidate identifiers;
- intentional undervote representation where required;
- payload schema version;
- mapping hash;
- issuing-device identity;
- payload integrity value;
- asymmetric digital signature;
- Truth QR or equivalent optical representation;
- finalization status;
- PDF content hash;
- PDF-native signature or authenticated PDF-signature adapter result;
- signed detached acceptance manifest;
- durable acceptance identity and sequence;
- evidence references; and
- PDF-native signature result.

The final payload must not include:

- voter name;
- voter registration ID;
- raw control number;
- print PIN;
- voter-linked authorization value;
- private key material; or
- a remote URL required for interpretation.

### 8.3 Status manifest

The status manifest records the lifecycle state of every serialized ballot serial. It must be append-only and independently verifiable.

Minimum fields:

- ballot serial;
- current derived status;
- ordered status events;
- event actor or role;
- event sequence;
- election context;
- device context;
- timestamp or ceremony sequence;
- reason code;
- related artifact hash;
- previous-event hash; and
- manifest version.

### 8.4 Evidence manifest

The evidence manifest groups the finalized PDF, signed payload, complete-PDF
hash, PDF-native signature result, detached manifest signature, acceptance
record, optional print result, optional custody references, and tally
disposition. The manifest signature must cover the canonical manifest fields,
including the complete-PDF hash. It must identify whether an item is source
evidence, reconstructed evidence, or a derived report.

### 8.5 Offline BEI authorization and signature artifacts

The package must model the BEI authorization credential separately from the
OBT and from the voter control number. Before deployment, the commissioned
precinct appliance is provisioned with:

- the COMELEC trust chain;
- the election-context manifest;
- authorized BEI credential identifiers;
- the certificate-validity or revocation package applicable to the election;
- the approved BEI authorization policy; and
- the device signing or verification configuration.

At precinct initialization, the ACM must:

1. verify the COMELEC signature and hash of the OBT and manifest;
2. verify the election, precinct, device, ballot-style, and policy context;
3. require each applicable BEI to enter a PIN or equivalent local unlock credential;
4. use that credential to unlock or authorize an asymmetric private signing key;
5. create a canonical `OBT_IMPORT` or `PRECINCT_INITIALIZATION` record;
6. sign the record offline; and
7. append the signed record to the local evidence and custody journal.

The PIN is an authorization mechanism, not a digital signature. It must not
be embedded in the OBT, ballot PDF, public payload, or ordinary logs. The
signed ceremony record must include the OBT hash, election ID, precinct ID,
device ID, ceremony type, credential or certificate identifier, ceremony
sequence, and signature. CCS or an independent verifier must be able to
validate the record later using the provisioned trust chain without requiring
Internet access at the precinct.

Short-lived election certificates and a preloaded, COMELEC-signed trust or
revocation package are preferred for offline operation. Online OCSP or CRL
access must not be a hidden dependency for ordinary precinct ceremonies.

## 9. Ballot Stock Generation

### 9.1 Inputs

The stock generator accepts:

- election configuration;
- precinct and jurisdiction assignments;
- ballot styles;
- contest definitions;
- candidate mapping;
- language and accessibility profile;
- serial-number allocation;
- security-marking layout;
- payload schema and profile;
- PDF layout profile; and
- stock quantity and replacement policy.

### 9.2 Generation rules

The generator must:

1. validate the election configuration;
2. produce a deterministic ballot face for each ballot style;
3. allocate non-repeating serials within the approved scope;
4. bind every serial to its precinct and ballot style;
5. produce the PDF stock artifact;
6. compute the artifact hash;
7. produce a signed stock manifest;
8. record the generation ceremony; and
9. fail closed on duplicate serials, missing configuration, or mapping mismatch.

### 9.3 Storage and packaging

Ballot stock may be packaged as:

- one PDF per ballot serial;
- encrypted archive segments;
- a directory tree by election, municipality, precinct, and ballot style; or
- an equivalent indexed container.

The package must preserve the logical identity of each ballot even when the physical storage format changes.

The stock package must support portable media transfer with:

- signed manifest;
- file count;
- serial range or explicit serial list;
- precinct and ballot-style scope;
- total byte count;
- hash algorithm and values;
- source and destination custody events; and
- import verification result.

### 9.4 High-volume generation and the 2028 TOR quantity

The v34 TOR identifies an estimated total of **83,510,241 ballots** across official, test, public-demo, training, overseas, local-absentee, and related activities. It separately estimates **81,358,541 official ballots**. These quantities are paper and operational quantities; they do not, by themselves, require 83,510,241 independent PDF files.

The package must support both interpretations:

#### OBT mode, aligned with the express v34 wording

1. Generate and approve one OBT per approved ballot-style scope.
2. Sign and hash-lock the OBT.
3. Publish or escrow the OBT hash and approved serial ranges.
4. Transfer the OBT only to authorized precinct devices.
5. At precinct initialization, require offline BEI PIN authorization and sign the OBT import/custody record with the authorized asymmetric key.
6. Allocate each serial once at the voter session.
7. Render the voter selections and signed payload from the OBT.
8. Optionally print supplementary paper evidence at the precinct.
9. Reconcile each serial as issued, printed if applicable, spoiled, cancelled, cast, voided, or unused.

#### Serialized stock mode, proposed per-PDF model

1. Generate one blank PDF per approved ballot serial.
2. Shard the PDFs by election, municipality, precinct, ballot style, and controlled media package.
3. Produce a signed manifest for every shard and a signed root manifest for the complete stock set.
4. Support resumable generation and verification so a failure does not require restarting the full run.
5. Verify file count, serial ranges, hashes, byte totals, ballot style, and precinct scope before media sealing.
6. Import only the authorized shard into each ACM.
7. Treat the imported PDFs as controlled ballot stock and apply the same single-use serial rules as OBT mode.

Serialized stock mode requires volume controls beyond ordinary PDF generation:

- deterministic sharding;
- resumable batch generation;
- parallel generation with deterministic output;
- manifest checkpoints;
- duplicate and missing-serial detection;
- media-size and transfer-time planning;
- import-time hash verification;
- storage-capacity testing; and
- a complete custody and reconciliation report.

The package must not require a single directory containing tens of millions of files. It may use indexed archives or shard containers, provided that the independent verifier can enumerate and verify every ballot serial without treating the container format as a new source of truth.

## 9.5 v34 TOR alignment

The following v34 provisions are directly supported by this specification:

| v34 TOR requirement | Package treatment |
| --- | --- |
| EMS-generated PDF ballot face | `BallotPdfRenderer` and OBT/stock generation profile. |
| Digitally signed, pre-numbered OBT | OBT artifact, signed manifest, hash lock, and serial-range value objects. |
| OBT delivered only to authorized precinct devices | Media package scope, device authorization, import verification, and custody manifest. |
| Single-use serial per voting session | Ballot serial lifecycle and append-only status journal. |
| Per-precinct printed ballot | Optional print authorization, print result, spoilage, reprint, and supplementary reconciliation events. |
| QR payload printed on official ballot | `truth-qr` payload profile and optional print-time read-back verification. |
| Ballot verification | Payload validation, sample independent-imager test, and optional paper/payload evidence. |
| BITS or equivalent status tracking | `StatusResolver`, manifest reports, and per-precinct reconciliation exports. |
| Eighty-three million-plus total quantity | High-volume generation/stock planning; quantity is not assumed to mean one PDF per ballot unless COMELEC approves serialized stock mode. |

## 9.6 v34 TOR issues requiring written clarification

The package specification must preserve these as unresolved until COMELEC issues a clarification or bid bulletin:

1. Whether “pre-numbered OBT” means one master PDF with controlled serial ranges or one PDF file for every serial.
2. Whether per-serial PDF stock may be transported on municipal portable media before election day.
3. Whether each per-serial PDF is required to be physically present on the ACM, or whether an OBT plus serial ledger is sufficient.
4. Whether the OpScan pathway's immediate QR read-back is validation only or an official cast/tally event.
5. The issued TOR's paper or voter-receipt provisions must be reconciled with the proposed canonical electronic DRE record and tally-input rule, including applicable statutory VVPAT requirements.
6. Any paper-deposit or receipt language must be classified by COMELEC as an OMR ballot, DRE VVPAT, statutory official ballot, or other defined artifact; the software must not assume it is merely supplementary evidence.
7. Whether the PDF-level signature, the signed QR payload, or both are required for OBT and final ballot authenticity.
8. Whether the 83,510,241 quantity includes electronic stock artifacts, printed paper only, or both.

Until these points are clarified, a bidder should describe serialized PDF stock as a proposed compliant implementation option, not as an unambiguous reading of the issued TOR.

### 9.7 Bid commitments and system acceptance

The bid baseline should explicitly propose (a) the finalized authenticated
ballot PDF as the canonical electronic DRE voting record and input to the
precinct electronic tally, and (b) a human-readable A4 paper record with a
Truth QR for voter verification and audit. These are distinct artifacts with
defined roles; neither role should be left to implication from a file format
or QR implementation.

The implementation and acceptance plan must define:

- the cast event and durable-acceptance boundary relative to voter inspection
  of the A4 paper record;
- the required visible content, readability, privacy, QR binding, paper
  handling, sealing, retention, and audit access;
- whether QR scanning assists the RMA and what human confirmation or tally
  steps remain required by the approved procedure;
- discrepancy, print-failure, unreadable-QR, duplicate/missing-serial, voter
  objection, and recovery procedures; and
- witnessed, reproducible acceptance tests from voter selection through PDF,
  paper/QR, tally, Election Return, independent verification, and audit.

The signed contract and incorporated technical annexes should make these
commitments, acceptance tests, legal/technical gates, and any conditions for
operational use explicit. Award of a bid does not itself convert a technical
specification into a waiver of statutory safeguards or substitute for
certification and approvals required elsewhere. Refer to the
[bid and contract guidance](LEGAL_POSITION_DRE_CANONICAL_ELECTRONIC_RECORD.md#bid-and-contract-guidance).

## 10. Control-Number Authorization

### 10.1 Purpose

The control number opens or authorizes a ballot session. It does not authenticate the final vote content.

### 10.2 Authorization rules

The verifier must:

- validate the control number against a commissioned local authorization roster or equivalent verifier;
- apply retry, expiry, cancellation, and one-time-use rules;
- select the authorized ballot style or stock scope;
- avoid exposing the raw control number in ordinary logs;
- avoid embedding the raw control number in the final public ballot payload;
- record a privacy-preserving authorization reference; and
- fail closed when the verifier is unavailable or the authorization record is stale, revoked, or already used.

The voter control number and the BEI PIN are different credentials. The
control number authorizes a voter session; it must not unlock a BEI signing
key or authorize a precinct ceremony.

### 10.3 Privacy rules

The control number may correspond to a voter record in the BEI paper ledger, but the package must not create a durable, public link between that ledger identity and the final ballot selections.

The design should separate:

```text
control number -> voter admission/session authorization
ballot serial -> ballot artifact identity
digital signature -> ballot integrity and signer authenticity
paper ledger -> voter admission evidence
```

### 10.4 Network behavior

The PDF or local verifier must not trust a hard-coded IP address or arbitrary Wi-Fi presence as an identity. A local server may provide authorization through a commissioned device identity, authenticated channel, or local trust service.

Offline operation must be supported after provisioning. Online or municipal server confirmation may be an optional ceremony, not a hidden dependency for ordinary voting.

### 10.5 Offline BEI PIN-mediated PKI ceremony

The BEI PIN is not embedded in the OBT. The OBT may identify the applicable
authorization policy, but the BEI credential and private signing key remain
separate protected artifacts. The recommended implementation is:

```text
COMELEC-signed OBT and manifest
  -> imported and verified by ACM
BEI credential + PIN
  -> unlocks or authorizes an asymmetric private key
Canonical precinct ceremony record
  -> signed offline by the authorized key
Local evidence journal
  -> exported for CCS and independent verification
```

The ceremony record must bind the signature to the election ID, precinct ID,
device ID, OBT hash, ceremony type, credential identifier, and sequence. A
PIN-only verifier with no asymmetric signing operation is not sufficient when
the TOR requires PKI. The PIN must mediate an actual asymmetric signature.

## 11. Voter Interaction and Ballot UI

### 11.1 Experience baseline

The rendered ballot experience should preserve the interaction model of:

`election/role-demo/voter/ballot`

The experience must support:

- control-number entry;
- ballot opening confirmation;
- position and contest navigation;
- page navigation;
- candidate search where the approved ballot style allows it;
- selection and deselection;
- review and correction;
- intentional undervoting;
- accessibility mode;
- confirmation summary;
- cancel/spoil workflow; and
- explicit final confirmation.

### 11.2 Contest-limit enforcement

For each contest, the ballot configuration defines:

- contest identifier;
- maximum selections;
- minimum selections, if any;
- candidate set;
- selection ordering;
- undervote policy; and
- overvote policy.

When the maximum is reached, further unselected candidates in that contest must be disabled or otherwise prevented from selection. If a selected candidate is deselected, an available slot becomes active again.

The limit must be enforced at more than one layer:

1. rendered interaction layer;
2. session command/domain layer;
3. finalization validator; and
4. independent verifier/tally layer.

No browser-only rule is sufficient for official ballot integrity.

### 11.3 Navigation shortcuts

The UI contract should support:

- next/previous contest;
- next/previous ballot page;
- jump to contest;
- jump to first unresolved contest;
- review all selections;
- clear current contest;
- clear all selections with confirmation;
- open accessibility controls; and
- proceed to final review.

Shortcut behavior must not bypass contest validation or confirmation.

### 11.4 Session states

Minimum states:

- `authorized`;
- `opened`;
- `in_progress`;
- `reviewing`;
- `confirmed`;
- `cancelled`;
- `spoiled`;
- `timed_out`;
- `printed`;
- `deposited`;
- `scanned`;
- `accepted`;
- `rejected`; and
- `held_for_resolution`.

State transitions must be explicit, journaled, and validated against role and ceremony rules.

## 12. Autonomous PDF Behavior

### 12.1 Intended meaning

For this package, “autonomous PDF” means:

- the finalized artifact carries its own context and signed payload;
- it can be opened and visually inspected without the election application;
- an independent verifier can validate it without the election application;
- the artifact does not require an Internet lookup; and
- a recursive verifier can classify and tally a directory of artifacts.

### 12.2 Self-contained protocol client

The ballot PDF is self-contained for voter-facing interaction. It contains
the ballot form, navigation, review, contest-limit behavior, ceremony state,
approved ballot metadata, and the client logic required to communicate with
the commissioned local ballot protocol.

The PDF may request external protocol operations for:

- session opening after control-number consumption;
- selection-state acknowledgement;
- finalization;
- QR and signature materialization;
- evidence commit;
- printing;
- custody events; and
- ceremony status.

The local service responds using canonical protocol envelopes. The PDF
remains the voter-facing surface, while the local `3neti/ballot-pdf` service
performs the authoritative domain validation, signing, evidence, and status
operations.

### 12.3 Protocol envelope

Every request and response must include:

- protocol name and version;
- message type;
- election identifier;
- precinct identifier;
- ballot-style identifier;
- session identifier;
- ballot serial when allocated;
- OBT hash;
- nonce or request sequence;
- payload hash; and
- status or error code.

The initial protocol surface is:

```text
session.open
session.update
ballot.finalize
acceptance.commit
ballot.print
evidence.commit
ceremony.status
election.close
```

### 12.4 Nutrient ACM runtime

The production ACM uses Nutrient Web SDK in a controlled local browser or
WebView. The Nutrient adapter must:

- load the approved encrypted OBT PDF locally;
- present the control-number/password admission step;
- expose the PDF form surface for candidate selection;
- support navigation, review, correction, confirmation, and cancellation;
- export the completed form state to the local ballot service;
- materialize or flatten the finalized PDF after successful finalization;
- hand the finalized PDF to the printer and evidence store; and
- operate with provisioned assets and an explicitly approved offline license.

Nutrient form state is not itself the election authority. The local
`3neti/ballot-pdf` service must revalidate the selections, create the
canonical signed payload, generate or verify the QR representation, compute
the final hash, authenticate the complete PDF, sign the detached manifest,
and commit the immutable bundle to the acceptance ledger before the ballot is
accepted.
The public Acrobat demonstration may use the same protocol messages through
its own viewer adapter. It does not change the production Nutrient runtime.

## 13. Finalization and Signing

### 13.1 Finalization sequence

1. Load the authorized Ballot PDF Stock instance.
2. Bind the session to the ballot serial and commissioned context.
3. Apply voter selections.
4. Validate contest limits and ballot-style rules.
5. Render the selected human-readable ballot.
6. Construct the canonical signed payload.
7. Sign the payload using the commissioned ballot-signing key.
8. Render the Truth QR or equivalent optical representation.
9. Produce the finalized PDF.
10. Apply the PDF-native signature through the commissioned PDF-signature adapter.
11. Compute the complete final PDF hash.
12. Sign the detached manifest containing the complete-PDF hash and artifact references.
13. Commit the PDF and signed manifest to WORM/equivalent storage.
14. Commit the acceptance record to the authoritative acceptance ledger.
15. Acknowledge durable acceptance.
16. Optionally authorize printing.

Finalization must be atomic from the perspective of the ballot lifecycle. A partial finalized artifact must not appear as accepted ballot evidence.

### 13.2 Signature profile

The payload signature must use Ed25519 or another approved asymmetric algorithm. Complete-artifact authentication uses both a PDF-native signature through a commissioned adapter and a signed detached manifest.

The package should support:

- signed payload inside the Truth QR;
- detached signature in the evidence manifest; and
- standards-compatible PDF-native signature adapter.

The payload signature, PDF-native signature, signed detached manifest, and
independent trust roster are jointly required for complete-artifact
verification. The payload signature authenticates canonical ballot data; the
PDF-native signature authenticates the PDF through the approved viewer or
adapter profile; the detached manifest links the complete-PDF hash to the
payload, context, and acceptance evidence.

## 14. WORM and Immutable Storage

### 14.1 Functional requirements

The storage boundary must:

- reject overwrite of a finalized ballot serial;
- reject deletion of finalized ballot evidence;
- preserve the original bytes;
- preserve the artifact hash;
- append status events rather than mutate history;
- support independent read access;
- expose storage and verification errors; and
- support export with a manifest.

WORM commit and acceptance-ledger commit are separate boundaries. The storage
commit occurs first. If the ledger commit is interrupted, the preserved bundle
remains verifiable evidence but is excluded from the accepted cast set until a
recovery procedure records acceptance. The same idempotency identity with
different content is a conflict; identical retries return the original
acceptance.

### 14.2 Storage interface

The package should define a storage port such as:

```text
putStock(ballotStock): StockReceipt
commitFinal(ballotPdf, evidence): FinalizationReceipt
appendEvent(event): EventReceipt
readArtifact(locator): ArtifactStream
listArtifacts(scope): ArtifactIterator
exportEvidence(scope): EvidencePackage
```

The package cannot make ordinary writable filesystem storage physically WORM by itself. It can enforce logical append-only behavior and require a commissioned storage adapter to provide stronger media or operating-system guarantees.

### 14.3 Acceptance ledger and recovery

The acceptance ledger is the authoritative acceptance authority. The PDF and
signed manifest are committed to WORM before the ledger record is committed.
An artifact without an acceptance record remains verifiable evidence but is
excluded from the accepted cast set. Identical retries return the original
acceptance. The same idempotency identity with different content is a
conflict. A serial may be accepted only once across the actual acceptance
authority; disconnected devices therefore require non-overlapping serial
ranges or an equivalent offline allocation authority.

### 14.4 Election Close Certificate

Election closure is a signed certificate, not a timestamp alone. The
certificate commits the election or precinct scope to the accepted cast set
and includes:

- close timestamp;
- last acceptance sequence;
- accepted serials or an authenticated set commitment;
- tally-rules hash;
- trust-roster hash;
- reconciliation totals;
- previous checkpoint reference; and
- required official or device signatures.

A hash list or Merkle-style commitment identifies a set but does not by itself
prove completeness. Completeness requires authenticated acceptance authority,
serial reconciliation, and independently retained checkpoints.

### 14.3 Cancellation and spoilage

Cancellation and spoilage append an event. They do not erase the PDF or reuse the serial.

## 15. Recursive Independent Verification

### 15.1 Input

The verifier accepts one or more roots and recursively traverses directories and evidence packages. It must not infer election meaning from path names.

### 15.2 Per-artifact checks

For each PDF, the verifier should:

1. locate the signed payload or Truth QR;
2. decode all multipart symbols;
3. reconstruct the canonical payload;
4. verify the signature against an independent trust roster;
5. verify the PDF-native signature or adapter evidence;
6. verify the detached manifest signature and complete-PDF hash;
7. verify election, precinct, ballot-style, device, mapping, and schema context;
8. validate selections and contest limits;
9. verify the ballot serial and authenticated lifecycle status;
10. detect duplicate and conflicting serials;
11. compare visible PDF, canonical selections, and QR payload where required;
12. verify acceptance evidence when classifying a ballot as accepted;
13. record a deterministic classification; and
14. never mutate the source artifact.

### 15.3 Classification categories

- `accepted`;
- `duplicate_exact`;
- `duplicate_conflicting`;
- `cancelled`;
- `spoiled`;
- `unused`;
- `invalid_signature`;
- `unknown_signer`;
- `wrong_election`;
- `wrong_precinct`;
- `wrong_ballot_style`;
- `invalid_mapping`;
- `invalid_selection_count`;
- `incomplete_payload`;
- `malformed_payload`;
- `unreadable_payload`;
- `missing_manifest_entry`;
- `unlisted_artifact`;
- `paper_payload_discrepancy`; and
- `pdf_payload_discrepancy`;
- `held_for_resolution`.

### 15.4 Reports

The verifier must produce:

- artifact inventory;
- status counts;
- duplicate and conflict report;
- signature and trust report;
- context mismatch report;
- ballot-selection validation report;
- candidate and contest totals from eligible records;
- precinct reconciliation report;
- evidence manifest;
- election close certificate;
- deterministic report hash; and
- machine-readable export plus human-readable PDF or text report.

The report must identify which records were excluded and why. It must never silently discard a PDF.

## 16. Transport and CCS Integration

Finalized PDFs and evidence packages may be transported through:

- removable media;
- local network;
- open network;
- optical presentation; or
- an approved file-transfer channel.

Transport success does not establish authenticity. The receiving verifier must independently validate the artifact, signature, trust roster, election scope, and duplicate/replacement status.

Confidentiality and authenticity are separate:

- signatures detect alteration and authenticate the signer;
- encryption protects confidentiality during transport or storage;
- custody manifests prove handling history;
- optional paper output supports supplementary physical reconciliation.

## 17. Privacy and Transparency

### 17.1 Internal evidence

Internal evidence may contain operational identifiers, status events, and custody references subject to the approved secrecy procedure.

### 17.2 Public evidence

Public or party-verification exports must be designed to avoid unauthorized voter-to-ballot correlation. They should not expose:

- raw control numbers;
- voter registration IDs;
- voter names;
- paper-ledger references;
- unnecessary timestamps;
- queue position;
- device interaction order; or
- private authorization data.

### 17.3 Independent verification package

An independent verifier package should contain:

- public trust anchor;
- signed key roster;
- payload schema;
- mapping and version identity;
- decoder/verifier;
- tally rules;
- duplicate policy;
- status-manifest policy;
- test vectors; and
- reproducibility instructions.

## 18. Failure, Recovery, and Exception Handling

The package must support explicit handling for:

- missing stock file;
- duplicate stock serial;
- failed media import;
- invalid stock manifest;
- unauthorized control number;
- expired or reused control number;
- abandoned session;
- timeout;
- print failure;
- spoiled ballot;
- cancelled ballot;
- replacement ballot;
- duplicate final ballot;
- QR unreadability;
- invalid signature;
- wrong context;
- storage failure;
- power interruption;
- application interruption;
- WORM commit failure;
- incomplete multipart payload;
- PDF/payload mismatch; and
- paper/payload discrepancy.

Every exception must have:

- stable reason code;
- operator-facing message;
- journal event;
- recovery or escalation action;
- effect on ballot status; and
- effect on tally eligibility.

## 19. Functional Acceptance Criteria

The package is functionally acceptable when it can demonstrate:

1. Deterministic generation of individually serialized stock PDFs.
2. Signed stock manifest creation and verification.
3. Portable-media import with missing, duplicate, altered, and wrong-scope detection.
4. Control-number authorization with one-time, expiry, retry, and privacy behavior.
5. Nutrient rendering of the approved interactive ballot PDF in the offline ACM browser.
6. Contest-limit enforcement for all configured contests.
7. Intentional undervoting and correction.
8. Finalization into a PDF-native-signed Ballot PDF with a signed detached manifest.
9. Rejection of post-finalization mutation.
10. Truth QR or equivalent payload reconstruction and signature verification.
11. WORM/equivalent bundle commit followed by authoritative acceptance-ledger commit.
12. Recursive verification over nested directories.
13. Exact duplicate classification and conflicting-serial detection.
14. Correct exclusion of cancelled, spoiled, invalid, and unresolved records.
15. Deterministic tally generation from an authenticated closed cast set.
16. Idempotent retry, lost-acknowledgment, crash-recovery, and serial-concurrency behavior.
17. Signed Election Close Certificate with cast-set commitment and reconciliation totals.
18. Reproducible reports from a separate verifier process.
19. Offline Nutrient operation after commissioning, including ballot rendering, form interaction, finalization handoff, optional printing, and evidence storage.
20. Protection against voter-to-ballot correlation in the final payload and operational evidence.
21. Self-contained PDF protocol interaction from control-number consumption through durable acceptance status.

### 19.1 Acrobat demonstration profile

The package must support a demonstration profile in which an external tester
can exercise the complete ballot lifecycle:

1. The tester enters an email address on a demonstration website.
2. The demonstration service issues a one-time control number.
3. The service sends an encrypted ballot PDF to the tester.
4. The tester opens the PDF in Adobe Acrobat.
5. Acrobat requests the control number as the PDF password.
6. The tester selects, reviews, and confirms candidates.
7. The tester saves or finalizes the PDF for upload.
8. The tester uploads the artifact to a nominated precinct endpoint.
9. The endpoint verifies, stores, and automatically tallies eligible artifacts.
10. The same artifacts can be copied into a directory tree and tallied recursively by an independent program.

The demonstration service may provide online control-number issuance. This is
an adapter for the demonstration only; it must not remove the offline
authorization path required for a commissioned precinct appliance.

The demonstration implementation must keep the email address outside the
public ballot payload. The control number is a PDF access and session
credential, not a ballot signature, voter identity, or tally input.

The Acrobat viewer may provide password prompting and form interaction, but
the viewer must not be the sole authority for contest limits, finalization,
signing, or tallying. At upload or finalization, the trusted package/service
must:

- revalidate every selection and contest limit;
- create the canonical signed payload;
- render or verify the QR representation;
- compute the finalized PDF hash;
- commit the artifact and lifecycle event to nominated storage; and
- prevent a second valid finalization for the same session or ballot serial.

The endpoint and independent recursive verifier must produce:

- accepted and excluded ballot counts;
- duplicate and conflicting-serial classifications;
- signature and context verification results;
- candidate and contest totals;
- reconciliation results; and
- a machine-readable report plus a human-readable Election Return look-alike clearly marked as a demonstration report.

The demonstration acceptance path must work without trusting ordinary PDF
form fields as authoritative tally data. The signed payload or QR
representation remains authoritative for independent verification.

## 20. Proposed Package API Surface

The initial package should be organized around ports and value objects rather than a Laravel-only application layer.

Candidate namespaces:

```text
LBHurtado\\BallotPdf\\Domain\\Ballot
LBHurtado\\BallotPdf\\Domain\\Stock
LBHurtado\\BallotPdf\\Domain\\Authorization
LBHurtado\\BallotPdf\\Domain\\Lifecycle
LBHurtado\\BallotPdf\\Domain\\Payload
LBHurtado\\BallotPdf\\Domain\\Evidence
LBHurtado\\BallotPdf\\Application\\GenerateStock
LBHurtado\\BallotPdf\\Application\\AuthorizeSession
LBHurtado\\BallotPdf\\Application\\FinalizeBallot
LBHurtado\\BallotPdf\\Application\\VerifyDirectory
LBHurtado\\BallotPdf\\Application\\BuildReport
LBHurtado\\BallotPdf\\Contracts
LBHurtado\\BallotPdf\\Infrastructure
```

Potential ports:

- `BallotPdfRenderer`;
- `PdfInspector`;
- `PayloadSigner`;
- `PayloadVerifier`;
- `TrustRosterProvider`;
- `ControlNumberAuthorizer`;
- `BallotStockRepository`;
- `FinalBallotRepository`;
- `AppendOnlyJournal`;
- `WormStore`;
- `ManifestSigner`;
- `ArtifactEnumerator`;
- `StatusResolver`; and
- `TallyReportWriter`.

The ACM frontend adapter should expose a separate JavaScript surface, for example:

```text
@3neti/ballot-pdf-nutrient
  NutrientBallotViewer
  NutrientBallotSession
  NutrientFormStateExporter
  NutrientFinalizationBridge
  NutrientOfflineCapabilityCheck
```

The adapter must expose the local protocol bridge without exposing framework
or vendor types to the ballot domain:

```text
BallotPdfProtocolClient
  openSession(controlNumber)
  updateSelectionState(state)
  requestFinalization(state)
  requestPrint(artifactId)
  commitEvidence(receipt)
  readCeremonyStatus()
```

The first scaffold should keep the Nutrient integration behind this adapter
and should not allow Nutrient-specific types to cross into the election domain.

## 21. Open Design Decisions Before Scaffolding

These questions should be answered before implementation begins:

1. Is the pre-election stock PDF a fully rendered blank ballot or a reusable ballot-style template with a serial envelope?
2. Are ballot serials allocated to all expected voters in advance, or drawn from a controlled pool during voter admission?
3. Does the control number authorize a ballot style only, or a specific stock serial?
4. What privacy-preserving identifier replaces raw control-number inclusion in internal evidence?
5. Which approved PDF-native signature profile and viewer/adapter validation rules will be commissioned for the required PDF signature?
6. Which PDF encryption profile must Acrobat, Preview, and the browser viewer support?
7. Is a server-backed authorization check mandatory, optional, or only used during commissioning?
8. What is the offline fallback when the local authorization service is unavailable?
9. Which Nutrient Web SDK feature profile and offline license terms are required for the PDF form, security, printing, and finalization handoff?
10. What constitutes a separately governed post-acceptance disposition, if any, when optional printing fails after WORM and ledger commit?
11. Does a printed but un-deposited ballot become spoiled, cancelled, or unresolved?
12. Is the ballot serial public, encrypted, or represented only through the signed payload?
13. Which fields are permitted in public transparency exports?
14. How are replacement ballots linked without linking voter identity to final selections?
15. What evidence proves that a portable media package was complete at transfer and import?
16. What is the authoritative lifecycle status when two custody logs disagree?
17. Must the independent verifier consume the signed acceptance ledger export and Election Close Certificate in addition to the PDFs and manifests?
18. What is the approved policy for paper/payload disagreement?
19. Which `truth-qr` profile and signature algorithm are mandatory for the first implementation?
20. What storage medium or operating-system control qualifies as WORM for the target appliance, and what operational evidence proves its deployment?

## 22. Architectural Risk Register

### R1. PDF viewer execution

**Risk:** Different viewers interpret interactive PDF forms, JavaScript, encryption, and signatures differently.  
**Default position:** Use Nutrient Web SDK for the PDF-centric voter surface, while retaining trusted local `3neti/ballot-pdf` validation and finalization outside the viewer.

### R2. Control-number correlation

**Risk:** Embedding a ledger-linked control number in the final ballot can defeat ballot secrecy.  
**Default position:** Keep raw control numbers out of the final payload and use a privacy-preserving authorization reference.

### R3. WORM over ordinary filesystems

**Risk:** A package cannot make a normal writable directory physically immutable.  
**Default position:** Define a WORM storage port and require commissioned infrastructure evidence.

### R4. Copy and duplicate semantics

**Risk:** Same control number, same serial, same hash, and replacement status can be confused.  
**Default position:** Ballot serial is the artifact identity; lifecycle status and signed evidence determine eligibility.

### R5. Public ballot transparency

**Risk:** Publishing visually appealing PDFs can expose correlation metadata.  
**Default position:** Separate internal custody evidence from public verification exports.

### R6. Double-counting

**Risk:** Immediate validation and later optical counting can count one ballot twice.  
**Default position:** Separate validation, physical deposit, accepted-count contribution, and post-close reconciliation events.

## 23. Recommended First Refinement

Before scaffolding, approve a one-page ballot artifact state machine and one canonical signed-payload schema. Those two decisions will determine the package boundaries more reliably than starting with PDF rendering code.

The first implementation slice should then be limited to:

- immutable domain value objects;
- stock manifest generation and verification;
- finalized payload construction and verification through `truth-qr`;
- lifecycle/status events;
- a filesystem-backed test WORM adapter;
- recursive verifier classification; and
- deterministic reports.

Browser interaction, PDF rendering, removable-media packaging, and production WORM integration should follow as separate adapters after the artifact contract is stable.

## 24. Decision Gate

The package is ready for scaffolding when the project owners have approved:

- the legal-record rule;
- the meaning and privacy treatment of the control number;
- ballot serial allocation;
- the stock-to-final artifact transition;
- the signed payload fields;
- the PDF signature position;
- the WORM storage boundary;
- duplicate and replacement rules;
- public transparency rules; and
- the PDF runtime strategy for contest-limit enforcement.
