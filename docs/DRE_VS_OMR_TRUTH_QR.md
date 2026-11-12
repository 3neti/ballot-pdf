# OpScan with DRE
## Technical White Paper: PDF-Authoritative Ballots, Durable Acceptance, and Truth QR

**Audience:** COMELEC; Senate and congressional policymakers; election professionals; election observers; political parties and candidates; and the public.

**Status:** R&D technical white paper; specifies the intended profile and identifies current prototype evidence separately.

**Proposed authority model:** The finalized authenticated ballot PDF is authoritative within the OpScan with DRE design. Deployment under this model remains subject to applicable legal, procurement, and governance approval.

**Evidence baseline:** Package documentation, implementation, and PHP tests reviewed on 2026-11-12. This paper is not a certification, legal opinion, or production-readiness declaration.

## 1. Executive specification

OpScan with DRE is an authenticated, PDF-authoritative ballot-record system. The finalized authenticated ballot PDF becomes cast upon durable acceptance together with its evidence manifest. Paper output is optional supplementary evidence. OMR remains a separate paper-first profile.

The system targets explicit trust assumptions, signed ballot records, deterministic verification, append-only evidence, offline operation after commissioning, and independent tally reproduction. It does not claim end-to-end verifiability against fully compromised capture, signing, or tallying components.

| Item | OpScan with DRE specification |
|---|---|
| Voter-facing medium | Approved interactive ballot PDF |
| Selection input | DRE fields mapped to candidate codes |
| Authoritative ballot artifact | Finalized authenticated ballot PDF |
| Machine-readable encoding | Signed canonical selections represented by Truth QR |
| Complete-artifact authentication | PDF-native signature plus signed detached manifest containing complete-PDF SHA-256 |
| Cast-event authority | Commissioned local finalization service and acceptance ledger |
| Casting boundary | Durable acceptance acknowledgement backed by committed ballot bundle and acceptance record |
| Evidence preservation | WORM or commissioned equivalent; ordinary-filesystem prototype is logical append-only storage |
| Election closure | Signed Election Close Certificate committing to the accepted cast set and approved rules |
| Tally basis | Eligible records in the authenticated closed cast set, each counted once |
| Paper | Optional inspection and supplementary evidence; not a prerequisite for casting or counting |
| Payload cryptography | Ed25519 or another explicitly approved asymmetric profile |
| Deployment posture | Offline precinct operation; independently provisioned verification material |

The architecture distinguishes six assurance questions:

| Assurance question | What the relevant controls establish—and do not establish |
|---|---|
| Authenticity of signed bytes | Verification under an authorized key detects changes to signed bytes; it does not prove originating-software correctness. |
| Validity under approved rules | Context, candidate membership, mapping, and contest checks establish rule conformance, not voter intention. |
| Representation consistency | The visible PDF, canonical selections, and Truth QR must agree; a hash alone does not establish semantic agreement. |
| Cast-set eligibility | Authenticated acceptance, serial authority, lifecycle evidence, and closure determine inclusion—not file presence. |
| Voter-intent fidelity | Depends on correct presentation, mapping, capture, voter review, accessible private confirmation, and trusted finalization. Signatures do not prove intent. |
| Independent tally reproduction | A separate verifier recomputes totals from the authenticated closed cast set and approved rules. It cannot detect an intention changed before finalization merely by recounting those records. |

“Truth QR” is an authenticated encoding, not a certificate of truth. Absence of voter identity from the PDF does not by itself prove resistance to voter-to-ballot correlation.

## 2. Scope and terminology

This paper covers ballot preparation, voter interaction, canonical selections, authentication, acceptance, evidence preservation, verification, closure, and tally reproduction. It defines the OpScan with DRE profile, not all possible optical-scan or electronic-voting systems.

| Term | Meaning in this paper |
|---|---|
| EMS | Election Management System; compiles election-source snapshots, rules, ballot styles, and operational packages. |
| OBT | Official Ballot Template; approved blank ballot master, associated mapping, and authenticated context. It contains no voter selections and is not cast. |
| ACM | Commissioned precinct appliance consuming approved packages and hosting local voter interaction and services. |
| DRE | Direct electronic selection in this profile; the term does not independently determine legal record authority. |
| Canonical selections | Schema-defined candidate-code data serialized deterministically for authentication. |
| Truth QR | Product term for signed machine-readable ballot data encoded into one or more two-dimensional symbols. |
| Finalized PDF | Materialized ballot representation; finalization alone does not establish casting. |
| Acceptance ledger | Authority recording the cast decision after evidence-bundle commitment. |
| WORM | Write-once-read-many storage or a commissioned equivalent providing the specified preservation guarantees. |
| Election Close Certificate | Authenticated commitment to the closed accepted cast set, rule versions, and reconciliation evidence. |
| BEI | Precinct election-board actors referenced in package documents; exact official roles and signature thresholds require approval. |
| CCS | Receiving/consolidation system that independently verifies transferred returns and evidence. |
| VVPAT | Voter-verifiable paper audit trail; optional printed output in this profile is supplementary and must not be assumed to constitute a complete, voter-verified paper audit trail. |

“Must” describes a profile requirement, not a claim that the current prototype implements it. Section 15 identifies demonstrated behavior; sections 16–18 distinguish production requirements, assumptions, and pending decisions.

### Source reconciliation

The principal architecture sources are [domain boundaries](ARCHITECTURE_DOMAIN_BOUNDARIES.md), [ADR-0001](ADR-0001-pdf-authoritative-opscan-profile.md), and the [functional specification](FUNCTIONAL_SPECIFICATION.md). Artifact and ceremony details come from [OBT-to-finalized flow](OBT_TO_FINALIZED_BALLOT_FLOW.md) and the [voter journey](OMR_DRE_VOTER_JOURNEY.md).

Evidence status is informed by the [scenario-runner description](SCENARIO_RUNNER.md), [Manila scenario](MANILA_HAPPY_PATH_SCENARIO.md), [software decision log](SOFTWARE_DECISION_LOG.md), and [engineering backlog](TODO.md). Procurement context is drawn from the [TOR v34 delta and SDK criteria](TOR_V34_COMPLIANCE_DELTA_AND_SDK_CRITERIA.md); this paper does not independently certify the underlying TOR interpretation.

The external working references are `~/Downloads/Ballot Journey.pdf` and `~/Downloads/pdf-ballot-document-update-handoff.txt`. The journey PDF supplies voter-stage questions and A4/thermal inspection examples. Its printing-station sequence is not adopted as mandatory: the updated profile permits casting without paper. Reuse of a control number at printing remains subject to privacy controls.

Some source passages retain earlier paper-deposit, optional-PDF-signing, or tally-before-certificate language. For this paper, the explicit PDF-authoritative decision governs: dual authentication is required, paper is optional, and the closed cast set precedes the final reproducible tally. Remaining source inconsistencies are not evidence of legal approval.

## 3. OpScan with DRE architecture

### 3.1 Authority model

| Representation or component | Role |
|---|---|
| DRE selections | Voter-interface input |
| Canonical selections | Data to be authenticated |
| Truth QR | Authenticated machine-readable encoding |
| Finalized ballot PDF | Authoritative ballot record |
| Paper output | Optional supplementary evidence |
| Acceptance ledger | Authority for the cast event |
| Election Close Certificate | Authenticated commitment to the closed cast set |
| PDF metadata | Supporting information only; does not independently create a vote |

The QR is not a substitute for the complete PDF. The PDF must contain or be associated with its canonical selections and signed encoding through authenticated references. A conflict among visible content, canonical data, and QR is an inconsistency requiring evidence preservation and adjudication, not an opportunity to choose whichever representation is easiest to process.

### 3.2 Domain and service boundaries

```text
Immutable source snapshots + election rules
  -> candidate registry + precinct topology + ballot-style resolution
  -> EMS-generated OBT + mapping + operational packages
  -> approval and authorized-device provisioning
  -> local PDF viewer and voter session
  -> local domain validation and finalization
  -> payload signature + PDF-native signature + detached manifest
  -> WORM bundle
  -> acceptance ledger and durable acknowledgement
  -> authenticated election closure
  -> independent verification and tally reproduction
  -> Election Return and prescribed CCS handoff
```

The EMS is designed as a deterministic compiler over versioned source snapshots and rules. Published COMELEC OBTs are reference and regression artifacts, not automatically the production source templates. Candidate data, precinct topology, ballot-style resolution, serial allocation, and device custody have separate ownership.

The PDF is the ballot artifact and voter-facing surface, not the entire election appliance. The commissioned local service performs authorization, canonical validation, QR construction, signing, durable finalization, evidence operations, verification, and tally generation.

The `3neti.ballot-pdf` protocol separates viewer actions from authoritative services. Its specified operations include `session.open`, `session.update`, `ballot.finalize`, `acceptance.commit`, `ballot.print`, `evidence.commit`, `ceremony.status`, and `election.close`. Production messages must be authenticated and bound to context, session, OBT, request identity, nonce or sequence, and serial where allocated. A syntactically valid envelope is not proof of authorization or replay protection.

The planned Nutrient adapter supplies local PDF rendering and form-state export. Acrobat supports a separate demonstration/interoperability profile. Neither viewer is the sole election-rule authority. Private signing keys must not be embedded in the PDF.

## 4. OMR comparison

| Dimension | OMR profile | OpScan with DRE profile |
|---|---|---|
| Authoritative record | Paper ballot; vote interpretation comes from physical marks | Finalized authenticated ballot PDF |
| Selection input | Voter-made physical mark | Electronic candidate-code selection |
| Machine interpretation | Optical mark interpretation | Authenticated canonical payload verification |
| Human-readable marks | Inputs available for paper inspection and interpretation | Visible confirmation of encoded selections |
| Paper dependency | Constitutive of the paper-first pathway | Optional supplementary evidence |
| Casting and eligibility | Governed by the applicable paper-first rules | Governed by durable acceptance and authenticated closed-set eligibility |

```text
OMR
physical mark
  -> optical mark interpretation

OpScan with DRE
DRE candidate-code selection
  -> signed canonical payload
  -> authenticated ballot PDF
```

This distinction concerns the source of vote interpretation. It does not imply that OMR systems lack signatures, offline operation, serial controls, duplicate detection, or independent audits. Those controls can exist in either profile.

QR decoding avoids classification of voter-made mark darkness. It still depends on encoding, rendering, decoding, approved mappings, and trustworthy capture and finalization. No general superiority in accuracy, security, cost, speed, or scalability is asserted without comparative evidence.

## 5. PDF ballot as the selected medium

PDF is selected to combine a fixed ballot layout, interactive selection fields, human-readable final content, and a portable evidence artifact. These are format capabilities; their reliable election use depends on a restricted, approved profile and tested adapters.

| Capability | Intended use | Qualification |
|---|---|---|
| Fixed layout and controlled fonts | Consistent approved ballot face | Cross-viewer rendering must be validated. |
| Form fields and viewer integration | Selection, navigation, review, correction | Client behavior is revalidated by the domain service. |
| Final materialization | Preserve human-readable selections and QR | Flattening/read-only flags do not replace authentication. |
| PDF-native signature | Authenticate the document under the commissioned signature profile | SDK support, signed revision coverage, and validation rules must be commissioned. |
| Encryption | Protect authorized opening or stored/transported content | Does not authenticate intent or prevent all copying and correlation. |
| Detached manifest | Bind complete artifact bytes to canonical data and acceptance identity | Manifest signature and trust scope must be verified. |
| Optional printing | Inspection and supplementary evidence | Print completion does not determine casting. |

The package distinguishes OBT mode—an approved master plus controlled serial allocation—from proposed serialized-stock mode, with one blank PDF per serial. Both lead to a per-ballot finalized PDF. Pre-generating a PDF for every estimated voter is not assumed mandatory. Serialized-stock distribution and the applicable supply model require approval.

Deterministic EMS output means reproducibility under fixed source hashes, rules, compiler, rendering profile, and controlled inputs. It does not imply that PDFs with different signatures, ceremony data, or acceptance records must have identical bytes.

## 6. Ballot journey

### 6.1 Voter-facing journey

1. Officers verify and open the commissioned precinct context.
2. Admission establishes voter eligibility outside the ballot-content domain.
3. A control number or approved credential authorizes one private session.
4. The voter opens the approved PDF, selects candidates, navigates contests, and corrects selections.
5. The voter privately reviews candidate names, selected choices, and permitted undervotes, then confirms.
6. The local service validates and authenticates the final record, preserves the bundle, commits acceptance, and acknowledges durable acceptance.
7. Optional paper may be printed and inspected under the approved procedure.
8. The authoritative PDF remains retained; officers reconcile, close the accepted set, verify, and tally.

### 6.2 Required lifecycle

```text
allocated
  -> opened
  -> selections_in_progress
  -> voter_confirmed
  -> finalized
  -> PDF-native signature applied
  -> complete PDF hashed
  -> detached manifest signed
  -> WORM bundle committed
  -> acceptance ledger committed
  -> durable acceptance acknowledged
  -> optionally printed
  -> reconciled
  -> election closed
  -> tallied
```

The durable acceptance acknowledgement is the casting event. It is backed by the ledger commit, not by successful delivery of a screen message alone. If acknowledgement delivery is lost after commitment, the accepted record persists and a retry must recover the original result rather than create a second vote.

Saving a PDF, a viewer confirmation action, QR scanning, or printing alone does not cast a ballot. The optional printing step may be skipped without blocking reconciliation or tally eligibility. Print failure after acceptance must not silently uncast the ballot.

The diagram includes session, artifact, printing, and election milestones; these are not interchangeable statuses. Cancelled, spoiled, abandoned, invalid, and duplicate records must be explicitly classified and accounted for. No finalized artifact or serial history is silently deleted. Post-acceptance changes of disposition require separately approved authority and append-only evidence.

### 6.3 Voter-intent fidelity and discrepancies

Voter-intent fidelity depends on correct ballot presentation, correct candidate-to-code mapping, accurate capture, voter review, accessible and private confirmation, and trusted finalization.

The approved mapping must be independently checked against candidate identities, contest definitions, and visible PDF labels. This validation is separate from viewer rendering and finalization logic. A hash confirms which mapping is used, not that the mapping was correct when approved. Independent checks sharing the same incorrect input can agree.

Contest limits must be enforced in the interface, domain layer, finalizer, and verifier. They check election rules, not whether the voter intended a particular candidate.

Before confirmation, voters must be able to review and correct choices and use approved cancellation procedures. After acceptance, a discrepancy report must preserve the accepted record, identify the stage and representations involved, record a stable exception, and escalate under the approved procedure. The system must not silently rewrite the accepted ballot.

Accessibility testing must establish private, independent navigation, review, confirmation, error reporting, and recovery for the intended users and languages. A review screen is an opportunity to inspect choices, not proof that every voter inspected them effectively.

## 7. Canonical selections and Truth QR

Candidate identity comes from approved field-to-code mapping, not screen coordinates or ink density. The canonical record must identify the schema, selections, and authenticated election context.

| Binding | Purpose |
|---|---|
| Election and precinct IDs | Limit the record to the commissioned scope |
| Ballot-style identity and approved hash | Establish the applicable contests and candidates |
| Mapping hash | Identify the approved field/code/rule mapping |
| Ballot serial | Identify the ballot instance for authorization, lifecycle, and duplicate checks |
| OBT/document profile and hash | Identify the source ballot definition |
| Asset bundle identity and hash | Bind approved visual/interactive assets where applicable |
| Device binding | Establish authorized originating-device context |
| Candidate codes | Encode selections under the approved schema |
| Key ID, signature profile, and signature | Permit scoped trust resolution and authenticity checks |

Truth QR renders the signed canonical data into one or more optical symbols. The codec must define canonical bytes, multipart reconstruction, validation, and hash boundaries. The existing `aes-election-v2` integration uses the legacy field name `paper_ballot_serial`; this name does not make paper authoritative. Schema migration and profile identifiers must be explicit and must not silently reinterpret older paper-first records.

No voter name, registration identity, raw control number, print PIN, voter-linked authorization credential, or private key belongs in the public ballot payload.

Successful QR signature verification is necessary but insufficient for acceptance or tally eligibility. It does not establish the complete PDF's identity, lifecycle status, serial authorization, casting, or inclusion in the closed set.

## 8. Complete-PDF authentication

Complete-artifact authentication requires both:

1. A PDF-native signature through a commissioned PDF-signature adapter.
2. A signed detached manifest binding the complete PDF to canonical data, context, and acceptance identity.

| Signed detached-manifest content | Required binding |
|---|---|
| Complete PDF SHA-256 | All bytes of the final PDF after PDF-native signing |
| Canonical payload hash | Canonical selection record under the declared schema |
| Truth QR payload hash | Declared machine-readable encoding and its defined hash boundary |
| Election context | Election and precinct |
| Ballot serial | Authorized ballot identity |
| Ballot-style and mapping hashes | Approved content and interpretation |
| Device binding | Commissioned device scope |
| Acceptance identity and sequence | Reserved identity/order subsequently bound by the ledger commit |
| Lifecycle status | Declared status, effective only with authenticated lifecycle and acceptance evidence |
| Artifact references | Authenticated links among PDF, payload, source packages, and related evidence |

The required non-circular sequence is:

```text
finalize PDF
  -> apply PDF-native signature
  -> hash complete PDF
  -> sign detached manifest containing that hash
  -> commit PDF and manifest to WORM
  -> commit acceptance record
  -> acknowledge durable acceptance
```

The manifest is external to the bytes it hashes. The ledger subsequently references the authenticated manifest hash. The pre-commit manifest must not depend on hashing a future record that in turn hashes that same manifest. Acceptance identity and sequence reservation must be coordinated by the acceptance authority; production sequencing and recovery require implementation evidence.

The PDF-native signature authenticates the document under its approved validation profile. The detached manifest binds the exact complete artifact bytes, including the final signed revision. Approved handling of incremental revisions and any post-signing modification must be explicit.

A valid Truth QR transplanted into another PDF must fail complete-artifact verification because the complete-PDF hash and authenticated manifest will not match. This assumes the relevant trust infrastructure is not compromised; an authorized malicious signer can still authenticate false content.

The PDF hash does not prove that its visible labels and selections semantically match the QR. Representation-consistency checks under the approved PDF profile are separately required. A mismatch is quarantined for adjudication, not silently normalized.

## 9. Durable acceptance and WORM evidence

### 9.1 Acceptance authority

The local finalization service and acceptance ledger form the authoritative acceptance authority. WORM preserves immutable artifacts; it does not independently decide whether a ballot is accepted.

An acceptance record links the acceptance identity, idempotency key, serial, complete-PDF hash, canonical payload hash, authenticated manifest hash, sequence, acceptance time, context, and lifecycle status. Production acceptance evidence must be authenticated and independently verifiable.

WORM bundle commitment precedes ledger commitment. The service may acknowledge durable acceptance only after the required bundle and acceptance evidence are committed under the commissioned durability model. An artifact without authenticated acceptance evidence is excluded from the accepted cast set even when its QR signature is valid.

### 9.2 Retry and failure semantics

| Condition | Required behavior |
|---|---|
| Identical request retried with the same idempotency key | Return the original accepted result; do not create new acceptance or tally contribution. |
| Same key reused with changed ballot content | Report an idempotency conflict; never silently return success for the changed content. |
| Same serial submitted under another request | Verify against authoritative acceptance history; reject conflicting acceptance and classify copies. |
| Failure before a complete bundle commit | Preserve/classify partial evidence as incomplete or abandoned; do not count it. |
| Bundle committed, ledger absent | Preserve the orphan bundle; exclude it until an authorized recovery completes acceptance. |
| Ledger committed, acknowledgement lost | Recover the original acceptance by authorized request/serial lookup; do not recast. |
| Process or power failure | Recover against the committed ledger and bundle, not inferred file dates or screen state. |
| Optional printing fails after acceptance | Append a print exception; retain acceptance unless a separately governed disposition applies. |

“Durable” must be demonstrated for the commissioned process-crash and power-loss model. Disk loss, device destruction, replication, removable-media custody, and recovery objectives require explicit deployment decisions; ordinary writes and file locks do not establish those guarantees.

Serial uniqueness applies across the actual acceptance authority, not just within one directory. Disconnected devices require authenticated non-overlapping serial ranges or equivalent offline allocation. Device replacement, range transfer, and recovery must not create simultaneous authority over the same serials.

### 9.3 Preservation and auditability

Final PDF bytes are not overwritten. Later lifecycle, print, discrepancy, and adjudication records are appended and authenticated rather than editing the ballot. Exports must retain original bytes, hashes, authenticated references, and source-versus-derived distinctions.

An ordinary-filesystem WORM adapter provides logical append-only behavior through application controls. It is not physical WORM and does not by itself resist privileged replacement, deletion, rollback, or hardware failure.

Logs and preserved PDFs support audit and investigation, but records produced by the same compromised system are not automatically independent evidence of intent. Evidence auditability must not be represented as demonstrated software independence or a complete paper-based risk-limiting audit.

## 10. Ballot secrecy and privacy

Control numbers are authorization credentials, not ballot signatures. Voter identity must not appear in the public ballot payload. These rules are necessary but do not alone establish unlinkability.

| Correlation source | Required treatment |
|---|---|
| Control numbers and authorization records | Separate admission evidence from selection-bearing artifacts; restrict lookup and avoid raw credentials in ordinary logs. |
| Serial allocation | Prevent durable voter-to-serial associations from becoming selection lookups; random serials alone are insufficient. |
| Timestamps and acceptance sequences | Assess correlation with observed arrival, booth, or queue order; disclose only under approved policy. |
| Session identifiers and idempotency records | Restrict recovery associations and prevent their leakage into public ballot data. |
| Print queues and spool files | Control selection-bearing copies and the association between private print requests and sessions. |
| Backups and device logs | Apply privacy controls to retained and restored data, not only live artifacts. |
| Custody evidence | Preserve accountability without publishing voter-linked access or interaction order. |

Temporary recovery links must be access-controlled and separated or removed according to an approved retention policy. Append-only evidence design must account for this requirement before sensitive associations are written permanently.

The original control number may be used at a private printing station only under an approved access and privacy design. Its use must not produce an unrestricted voter-to-ballot lookup or a public selection receipt.

Signatures provide authenticity, not confidentiality. PDF encryption and protected transport address different risks and require separate key and access policy.

Selection-bearing PDFs, QR images, and receipts create coercion and vote-buying risks when copied or distributed. A signed selection record is not by itself proof of casting, but it can still provide transferable authenticated evidence of selections. Publication and copying require a privacy assessment covering payloads, metadata, manifests, logs, backups, and cross-record correlation.

Independent-verification access must be designed together with ballot secrecy. A public trust roster can be distributed without distributing private keys; access to ballot-level data and acceptance order is a separate governance question.

## 11. Recursive verification

Recursive verification means enumerating nested artifact collections and evidence packages, then applying the same authenticated validation rules to each record and to the resulting set. Directory traversal only enumerates artifacts. It is not itself a security control.

The commissioned verifier must authenticate or validate:

1. PDF-native signature and approved signed-revision coverage.
2. Detached-manifest signature.
3. Complete-PDF hash and artifact references.
4. Truth QR signature, reconstruction, and canonical payload hash.
5. Election and precinct context.
6. Approved ballot style and source OBT.
7. Mapping and candidate/contest membership, including contest limits.
8. Device binding and authorization.
9. Serial authorization and uniqueness.
10. Authenticated lifecycle status.
11. Acceptance evidence and its linkage to the bundle.
12. Duplicate/conflict status and membership in the authenticated closed cast set.
13. Visible-PDF/canonical-selection/QR consistency under the approved profile.

Filenames, directory names, timestamps, and filesystem order must never be authority. Sorting may make reports reproducible, but must not decide which conflicting ballot wins.

Classifications must distinguish accepted records, exact copies, conflicting duplicates, cancelled, spoiled, abandoned, unused, invalid signature, invalid context, missing/unauthorized serial, incomplete evidence, and representation discrepancy. Exact copies do not create additional votes. Conflicting records require authoritative resolution; no arbitrary first-file-wins rule is permitted.

The verifier must preserve source artifacts, report every exclusion and reason, and retain provenance for reconstructed and derived evidence. Required independent inputs include public trust anchors, authenticated roster and source packages, approved schemas and rules, acceptance evidence, lifecycle evidence, and the Election Close Certificate.

These are production verification requirements. The existing directory verifier implements only a subset, as detailed in section 15.

## 12. Election closure and tally reproduction

### 12.1 Signed Election Close Certificate

A datetime alone does not close an election. The Election Close Certificate is the authenticated commitment binding a scope to its closed accepted cast set.

| Required content | Purpose |
|---|---|
| Election and precinct scope | Define the population and authority being closed |
| Close timestamp | Record the closure ceremony time |
| Last acceptance sequence | Establish the committed ledger boundary |
| Accepted serials or equivalent cast-set commitment | Identify the accepted records and their authenticated artifact bindings |
| Tally-rules hash | Bind the interpretation and tally rules |
| Trust-roster hash | Bind the applicable verification material |
| Reconciliation totals | Explain issued, accepted, cancelled, spoiled, unused, and unresolved serials and related events |
| Previous checkpoint reference | Connect closure to independently retained prior evidence |
| Required official/device signatures | Authenticate closure under the approved ceremony and role policy |

A hash list or Merkle commitment identifies a set but does not alone prove completeness. Completeness also requires authenticated acceptance authority, serial reconciliation, and independent checkpoints. A self-consistent certificate from a compromised authority is not proof that all eligible votes were captured.

Final tallying must use the closed set and approved rules. Closure must be coordinated with acceptance so the sequence boundary cannot race with new commits. Exceptions must be reported and resolved under approved procedures rather than silently changing the certified population.

### 12.2 Reproduction and reports

Independent tally reproduction recomputes totals from the authenticated closed cast set and approved rules, without relying on the ballot-preparation application's previously calculated totals.

```text
Authenticated close certificate + acceptance evidence
  + complete ballot bundles + approved rules + trust material
  -> verify set membership, eligibility, and representation consistency
  -> count each eligible accepted serial once
  -> reproduce canonical totals and reconciliation report
  -> compare with Election Return
```

Reproducible totals do not prove that selections were captured as intended before finalization. Optional paper does not become controlling through a recount.

The prototype generates a canonical, timestamp-free Election Return-like artifact containing context, eligible records, tally, and verification references. It is not a prescribed COMELEC form. Production must define canonical report bytes separately from ceremony timestamps, signature envelopes, and official presentation. Independent implementations and privacy-approved access remain required.

## 13. Optional paper output

Paper may support human inspection, supplementary evidence, demonstrations, reconciliation, or operational contingency. Approved A4 laser or thermal output may be considered, subject to readability, custody, retention, and procurement validation.

Printing is optional for OpScan with DRE. Paper inspection, physical retention, and deposit are not casting prerequisites. Printed confirmation marks are human-readable representations, not OMR inputs in this profile. QR read-back checks encoding and print readability; it does not create another cast event or tally contribution.

Where output is produced, record the originating PDF hash, serial, print attempt, printer identity, result, read-back evidence, and custody disposition under the privacy policy. A reprint is not a new vote; any replacement-ballot procedure requires separate authorization and serial accounting.

If paper and PDF disagree:

1. Preserve the authoritative PDF, manifest, acceptance record, paper, and relevant print evidence.
2. Classify the discrepancy and identify the affected representations.
3. Do not silently substitute paper, QR, canonical data, or a newly rendered PDF.
4. Follow the approved reporting, escalation, and adjudication process.
5. Distinguish machine-processing status, investigative hold, and legal invalidity.

Optional paper evidence cannot override the accepted PDF record under this profile. How a discrepancy affects eligibility or certification is a pending governance decision. An optional printed subset must not be presented as a complete paper audit trail or as automatically supporting an election-wide paper-based risk-limiting audit.

## 14. Trust and key custody

The trust model includes EMS compilation, OBT approval, precinct provisioning, ACM identity, the viewer/capture path, finalization service, signing keys, trust-roster distribution, evidence storage, acceptance authority, closure authority, and independent verification tools.

| Trust role | Required separation |
|---|---|
| Election approval | Approves OBT, mapping, scope, and authorized packages; technical provenance signatures do not replace official approval. |
| Ballot and manifest signing | Authenticates selections and complete artifacts under scoped keys. |
| Acceptance authority | Commits cast events and protects serial/sequence integrity. |
| BEI/official ceremony | Authorizes initialization, custody, reconciliation, closure, and required returns. |
| Verification | Uses independently provisioned public trust material and validates purpose, election, precinct, status, and policy. |

Production key management requires controlled generation, protected keys, role separation, offline trust-roster distribution, revocation and rotation, governed recovery or escrow procedures, and ceremony evidence. HSM or OS keychain integration must be commissioned and tested, not inferred from an interface.

A BEI PIN unlocks or authorizes an asymmetric signing key. It is not itself a digital signature. The OBT may carry a non-secret policy reference but must not contain a PIN, verifier, private key, or reusable ceremony secret.

Offline operation requires preloaded authenticated trust and applicable validity/revocation material. Ordinary precinct ceremonies and artifact interpretation must not secretly depend on Internet OCSP, cloud services, or vendor license calls. Production policy must address later compromise discovery and verification of older records.

Signing-key escrow or duplication expands signing authority and requires explicit authorization. Encryption-key recovery and signing-key recovery have different consequences. Public rosters must not distribute private key material.

A valid signature proves authenticity under the trust infrastructure. It does not prove that the originating software was correct, that the approved source data were correct, or that the voter intended the signed selections.

## 15. Demonstrated capabilities

### 15.1 Automated package evidence

On 2026-11-12, `vendor/bin/pest` completed with **15 passing tests and 85 assertions**. This is a point-in-time test result, not a certification or comprehensive adversarial evaluation.

| Evidence | Demonstrated behavior | Boundary |
|---|---|---|
| `tests/Feature/CoreWorkflowTest.php` | Contest ceiling rejection; context-bound stock; injected authorization; final-artifact construction; overwrite refusal; protocol envelope handling; nested duplicate suppression; declared-PDF-hash mismatch detection | Several codecs, materializers, and authorization dependencies are test doubles. |
| `tests/Feature/TruthQrIntegrationTest.php` | Existing `aes-election-v2` Ed25519 encode/decode; scoped key resolution; reference ceremony/rotation records; approval-ID checks; distribution hashes; escrow tamper rejection; injected key-store boundary | Synthetic keys and records; no production HSM, human identity ceremony, or historical revocation assurance. |
| `tests/Feature/AcceptanceLedgerTest.php` | Identical ledger-record retries; changed-record conflict; deterministic close-set commitment; PDF/manifest storage before ledger acceptance | PDF signer and manifest signer are test doubles; no real PDF-native signature validation or crash/concurrency proof. |
| `tests/Scenario/LifecycleScenarioTest.php` | Synthetic selection/finalization/verification/report flow and repeat-finalization refusal | Does not currently integrate the new acceptance service or closed-set authentication. |
| `tests/Scenario/ManilaHappyPathScenarioTest.php` | Synthetic composite-contest orchestration, offline-context fixtures, serial-range checks, artifact/report generation | Simulated PDF/codec/signing dependencies; not a live approved precinct ceremony. |

### 15.2 Recorded prototype integration evidence

The software decision log records a local Nutrient browser smoke test opening an encrypted synthetic OBT, exporting selections, invoking reference finalization, returning flattened PDF and QR evidence, and using local assets. It also records QR extraction/read-back and `qpdf` syntax checks. These are prior recorded prototype observations, not newly repeated cross-viewer acceptance tests for this white paper.

The same log records incomplete Acrobat compatibility validation, a Nutrient evaluation watermark/licensing issue, and a Vitest module-runner failure before test collection. A successful Vite build or PHP suite does not close those gaps.

### 15.3 Implementation gaps that limit current claims

The reviewed implementation is relevant evidence, but interfaces and passing tests do not establish the complete architecture:

- `src/Application/AcceptFinalizedBallot.php` applies an injected PDF signature, hashes returned bytes, signs a manifest, stores artifacts, and then writes an acceptance record. Its existing-idempotency shortcut returns the original record without comparing new request content. The ledger's changed-record test does not establish changed-content conflict handling at this service boundary.
- Its current manifest includes a payload hash, QR scans, context, idempotency key, signature metadata, and status, but not every explicit binding required by section 8, including the separate QR payload hash, acceptance sequence, and complete style/mapping/artifact-reference schema.
- `src/Infrastructure/Storage/FilesystemAcceptanceLedger.php` stores JSON records with file-write locking. It does not demonstrate authenticated ledger exports, authority-wide transactional uniqueness, monotonic sequence allocation, or power-loss durability. An `AcceptanceRecord` hash is not an independent signature.
- `src/Application/BuildElectionCloseCertificate.php` and `src/Domain/Evidence/ElectionCloseCertificate.php` build deterministic certificate data and a cast-set hash. They do not currently supply the required official/device signatures, previous-checkpoint field, or an enforced acceptance freeze.
- `src/Application/VerifyDirectory.php` checks decoded context, selected statuses, serials, and declared PDF hashes, but does not enforce all PDF-native, detached-manifest, acceptance-ledger, or close-certificate checks. It still locates PDF candidates by basename and retains the first eligible representation when classifying later duplicates. This is legacy prototype behavior, not the required authority policy.
- `src/Protocol/ReferenceProtocolService.php` exposes injectable `acceptance.commit` and `election.close` callbacks, but route availability does not prove their deployment or full context authentication. The scenario runners still use the earlier finalization path.

Some scenario descriptions describe the intended full chain more broadly than the code currently exercises. The backlog explicitly retains acceptance/closure integration and real PDF-native signing as unfinished. This paper therefore does not call the current scenario an end-to-end demonstration of the commissioned profile.

## 16. Production requirements

The following capabilities are required by the architecture but are not established as commissioned by the current evidence:

- A real PDF-native signing SDK/adapter and independent signature verification, with approved algorithms, revision rules, and certificate handling.
- Complete signed-manifest schema and verification covering every section 8 binding.
- Integrated finalization/acceptance protocol with changed-content idempotency checks, authority-wide serial uniqueness, sequence reservation, authenticated acceptance exports, and explicit recovery.
- Crash and power-failure injection across signing, bundle writes, manifest writes, ledger commit, and acknowledgement; concurrent and disconnected-device tests.
- Commissioned WORM/equivalent deployment, rollback detection, backup and media custody, and independently verifiable exports.
- Signed closure, previous-checkpoint chaining, authenticated reconciliation, enforced close boundary, and independent cast-set completeness checks.
- A verifier that uses authenticated references rather than paths, excludes unaccepted artifacts, holds unresolved conflicts without first-file-wins selection, and verifies the closed population before final tally.
- Independent mapping approval and representation-consistency tests, including malicious or incorrect visible-PDF/canonical-data/QR combinations and transplanted valid QR.
- Validated offline viewer behavior across approved Acrobat, Nutrient, and independent-verifier profiles; target-device accessibility, rendering, optional printing, and license tests.
- Production key custody, HSM integration where selected, roster distribution, revocation policy, human approval identity verification, and recovery drills.
- Privacy testing across public artifacts and internal correlation sources, including restored backups and optional printing.
- Versioned independent-verification packages, negative test vectors, approved reports, and reproduction by a separately operated verifier.
- Workload, storage, concurrency, sustained-operation, and distribution measurements on the proposed hardware. Prototype fixtures do not establish national-scale performance.

Required demonstrations must include valid/invalid signatures, wrong-context rejection, duplicate conflicts, complete-artifact mutation, abandoned/orphan evidence exclusion, lost acknowledgements, print failure after acceptance, and deterministic totals under fixed approved inputs.

## 17. Legal, procurement, and governance limitations

### 17.1 Assumptions

The design assumes an explicitly selected PDF-authoritative profile; approved source snapshots, mappings, rules, and trust material; controlled precinct appliances; authenticated offline serial authority; and permitted independent access to evidence under a privacy policy. Those assumptions are not established by file format or signatures.

Capture, finalization, signing, acceptance, and closure remain trusted boundaries. Independent verification may detect unauthorized changes, invalid artifacts, inconsistent evidence, or tally deviations when appropriate independent inputs exist. It does not establish end-to-end verifiability against fully compromised components.

### 17.2 Pending legal and procurement authority

Legal authority for PDF-authoritative ballots remains pending. Software implementation cannot authorize replacing a legally controlling paper record. Deployments requiring paper authority must use their applicable paper-first rules rather than silently applying this profile.

The TOR-delta document preserves paper-printing/deposit requirements and unresolved signature language from earlier procurement analysis. Its reported quantities and hardware criteria are planning context, not proof that PDF authority with optional paper is compliant. Written clarification must reconcile the proposed profile with the applicable TOR, receipt requirements, stock-supply interpretation, approved signing profile, and official forms.

Viewer and signing vendors remain subject to licensing, offline deployment rights, procurement, interoperability, and commissioning. No SDK feature list substitutes for tests on the approved appliance.

### 17.3 Governance and audit authority

Official bodies must approve discrepancy adjudication, post-acceptance dispositions, retention, disclosure, independent audit access, signature thresholds, and key ceremonies. Technical rejection, an investigative hold, and legal invalidity are distinct outcomes.

Optional paper cannot override the accepted PDF record under this design. Its availability does not establish complete voter verification, a complete physical audit trail, or a risk-limiting audit of all ballots. Independent tally reproduction does not establish intent fidelity.

## 18. Open decisions

The architecture is fixed for this paper; the following commissioning and governance details remain open:

| Decision | Required resolution |
|---|---|
| Legal adoption and procurement | Applicable authority, profile approval, TOR clarification, and official forms |
| PDF-native signature profile | SDK/adapter, algorithms, certificate validation, signed revisions, and permitted post-finalization behavior |
| Canonical schemas | Exact payload/QR hash boundaries, manifest fields, acceptance references, profile migration, and independent test vectors |
| Acceptance deployment | Transaction and recovery mechanism; sequence allocation; authenticated ledger evidence; power-loss and device-loss guarantees |
| Offline serial authority | Non-overlapping ranges, device replacement, range transfer, concurrency, and reconciliation |
| Closure governance | Signature threshold, freeze procedure, previous-checkpoint format, independent retention, and dispute handling |
| Mapping and visible consistency | Independent approval process and reproducible semantic checks across supported renderers |
| Post-acceptance discrepancies | Authorized disposition, effect on eligibility/certification, and preservation without silent replacement |
| Privacy and publication | Temporary recovery links, retention/deletion separation, allowed ballot-level access, copies, receipts, and correlation assessment |
| Optional paper | Availability, format, access, reprint rules, inspection, and custody without becoming a casting dependency |
| Keys and compromise | Provisioning, historical verification, offline revocation, signing versus encryption recovery, and authorized escrow |
| Viewer/accessibility acceptance | Approved versions, hardware, private review, error reporting, offline licensing, and representative-user testing |
| Independent reproduction | Canonical totals/report scope, approved tally rules, separately operated verifier, and privacy-compatible evidence access |
| Future assurance | Whether a separately specified and evaluated end-to-end-verifiable protocol is required in a future profile |

The technical claim is deliberately bounded: authenticated PDF records, cast-event evidence, and a closed-set commitment can support rule-based verification and independent tally reproduction under explicit trust assumptions. They do not cryptographically prove voter intention or confer legal authority.
