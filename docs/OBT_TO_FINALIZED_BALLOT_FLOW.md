# OBT to Finalized Ballot PDF Flow

## Document Status

| Field | Value |
| --- | --- |
| Package | `3neti/ballot-pdf` |
| Status | Refinable design draft |
| Related document | `docs/FUNCTIONAL_SPECIFICATION.md` |
| Pathway | OpScan with DRE ballot preparation |
| DRE record terminology | In the proposed OpScan with DRE profile, the finalized signed ballot PDF is the canonical electronic DRE voting record and proposed tally input; legal designation and paper/VVPAT treatment require COMELEC determination |
| Package host | EMS uses `3neti/ballot-pdf`; the ACM uses the planned Nutrient adapter for local PDF rendering, form interaction, materialization, and printing |

## 1. End-to-End Flow

```text
EMS using `3neti/ballot-pdf`
  |
  | 1. Configure election, precinct, ballot style,
  |    candidates, contests, limits, and mapping
  v
Generate OBT PDF through the package
  |
  | 2. Add approved ballot face, candidate roster,
  |    contest rules, QR placement, and serial-range scope
  v
Compute hash and prepare OBT approval package
  |
  | 3. Record OBT hash, mapping hash, trust data,
  |    and approval manifest for COMELEC approval
  v
COMELEC approval and signature ceremony
  |
  | 4. COMELEC-authorized authority approves the
  |    ballot design, mapping, OBT hash, and serial scope;
  |    the approved OBT manifest is digitally signed
  v
Deliver approved OBT to authorized ACM
  |
  | 5. Import OBT, verify COMELEC signature/hash,
  |    precinct scope, device authorization,
  |    and serial ranges
  v
BEI import and custody verification
  |
  | 6. BEI verifies the approved OBT manifest.
  |    Each authorized BEI enters a PIN that
  |    unlocks or authorizes an offline signing key;
  |    the ACM signs the precinct custody/
  |    initialization record
  v
Open voter session
  |
  | 7. Voter enters control number
  |    and WAES authorizes one session
  v
Open Nutrient-powered ballot PDF session
  |
  | 8. Nutrient loads the approved encrypted OBT
  |    PDF and displays its embedded ballot form
  v
Voter selects candidates inside the PDF
  |
  | 9. Nutrient manages form interaction, navigation,
  |    review, correction, and presentation; the local
  |    ballot service revalidates contest limits
  v
Voter confirms selections
  |
  | 10. WAES allocates one unused ballot serial
  |    and creates the final ballot data
  v
Export selections and materialize Finalized Ballot PDF
  |
  | 11. Nutrient exports the completed form state;
  |    `3neti/ballot-pdf` validates selections, creates
  |    the signed payload, and Nutrient materializes the PDF
  v
Sign and hash Finalized Ballot PDF
  |
  | 12. Apply PDF-native signature, hash the complete PDF,
  |     sign the detached manifest, and commit the bundle
  |     to WORM/equivalent evidence storage
  v
Acceptance ledger commit
  |
  | 13. Commit the acceptance record with serial, hashes,
  |     manifest identity, idempotency key, and sequence;
  |     acknowledge durable acceptance
  v
Produce A4 voter-verification/audit record
  |
  | 14. The offered system produces an A4 human-readable record
  |     with the corresponding authenticated Truth QR; record
  |     print success or exception for reconciliation
  v
Print read-back and voter inspection
  |
  | 15. Verify QR signature, context, serial, mapping,
  |     duplicate status, and print readability; provide
  |     private voter inspection and record its disposition
  v
Reconcile accepted PDF and A4 record
  |
  | 16. Retain the accepted PDF and evidence; secure, seal,
  |     retain, and make the A4 record available for audit
  |     under the approved custody procedure
  v
Post-close PDF verification
  |
  | 17. Authorized officials verify accepted PDFs
  |     and count each accepted serial exactly once
  v
Generate signed Election Return
  |
  | 18. Issue a signed Election Close Certificate
  |     committing to the accepted cast set and rules;
  |     reconcile ballots and tally; BEI signs reports
  v
Transmit or transfer to CCS
```

## 2. Artifact Flow

```text
EMS + `3neti/ballot-pdf`
  -> OBT PDF
  -> OBT hash and approval package
  -> COMELEC approval signature
  -> authorized ACM import
  -> BEI import/custody signature
  -> voter session
  -> Finalized Ballot PDF
  -> signed QR payload
  -> PDF-native signature
  -> signed detached acceptance manifest
  -> WORM/evidence store
  -> acceptance ledger record
  -> A4 voter-verification/audit record and print result
  -> QR read-back and voter-inspection evidence
  -> PDF acceptance event
  -> post-close PDF verification result
  -> precinct reconciliation
  -> signed Election Return
```

The package is used by the EMS and precinct appliance as a deterministic artifact and verification library. It does not itself constitute COMELEC approval authority or replace BEI legal ceremonies.

For the legal distinction between this system's canonical electronic DRE
record and the statutory “official ballot,” see the
[legal-position note](LEGAL_POSITION_DRE_CANONICAL_ELECTRONIC_RECORD.md).
The proposed PDF tally-input role, A4/Truth QR voter-verification and audit
path, mismatch rules, and end-to-end contract acceptance tests must be explicit
in the bid and its incorporated technical annexes; refer to the note's
[bid and contract guidance](LEGAL_POSITION_DRE_CANONICAL_ELECTRONIC_RECORD.md#bid-and-contract-guidance).

## 3. OBT Approval and Signing Responsibilities

### 3.1 EMS using `3neti/ballot-pdf`

The EMS uses the package to:

- generate the canonical OBT PDF;
- compute the OBT content hash;
- generate the mapping and serial-range references;
- prepare the OBT manifest;
- produce the approval package;
- verify the signed OBT after approval; and
- package the approved OBT for authorized ACM delivery.

The package supplies deterministic artifact generation and verification. It does not make the EMS the legal approval authority.

### 3.2 COMELEC-authorized approval authority

COMELEC, through the authorized ballot-approval or Printing Committee ceremony, approves:

- ballot design;
- candidate and contest mapping;
- election and precinct scope;
- OBT version;
- OBT hash;
- serial-range scope;
- trust-roster context; and
- authorized-device distribution.

The COMELEC-authorized authority signs the approved OBT manifest or the OBT itself using the commissioned election authority key. That approval signature is the authoritative approval signature for the OBT.

### 3.3 Bidder and EMS technical signatures

The bidder or EMS may provide technical signatures or provenance signatures for:

- software builds;
- generated-file provenance;
- package manifests;
- device configuration;
- technical evidence; and
- export packages.

Those technical signatures must not be represented as a replacement for the COMELEC approval signature.

### 3.4 BEI signatures

The BEI signs precinct-level records, including:

- OBT import and custody certificate;
- opening and zero report;
- serial-range receipt;
- cancellation, spoilage, and replacement records;
- close-polls record;
- reconciliation report;
- Election Return; and
- evidence or storage handoff certificate.

The BEI does not ordinarily sign the OBT master. The BEI verifies and accepts the approved OBT at the precinct.

The precinct ceremony is offline. Before deployment, the ACM is provisioned
with the COMELEC trust chain, election-context manifest, authorized BEI
credential identifiers, and the applicable certificate-validity or
revocation package. A BEI PIN unlocks or authorizes the corresponding
private signing key; the PIN itself is not the signature and is never stored
in the OBT. The ACM signs a canonical ceremony record containing the OBT
hash, election and precinct context, device identity, ceremony type,
credential identifier, and ceremony sequence. CCS verifies the signature
after evidence transfer without requiring precinct Internet access.

### 3.5 Signature chain

```text
EMS + ballot-pdf
  -> generates OBT and computes hash
  -> prepares approval package

COMELEC authority
  -> approves and signs OBT manifest

Authorized ACM
  -> verifies COMELEC signature and hash

BEI
  -> enters PIN to authorize an offline signing key
  -> signs import, custody, initialization, and reconciliation records

Voter session
  -> creates selections; no voter identity is placed in the public ballot payload

Ballot signer
  -> signs finalized ballot payload
```

The package must support injected signing and verification ports. It must not hard-code COMELEC roles or keys into the PDF generation implementation.

### 3.6 Nutrient ACM runtime

The commissioned ACM uses the planned `3neti/ballot-pdf-nutrient` adapter
with Nutrient Web SDK to render and interact with the approved ballot PDF
locally. Nutrient provides the PDF form surface, navigation, review state,
materialization, and print handoff. The adapter must load the provisioned
Nutrient assets and license offline and must not require a cloud service during
ordinary voting.

The visible voter interaction remains inside the PDF. The local
`3neti/ballot-pdf` service remains authoritative for contest-limit
validation, payload construction, Ed25519 signing, Truth QR generation,
finalization, lifecycle, and tally eligibility. Nutrient form state is input
to finalization, not an independent source of election truth.

The PDF communicates with the local service through the `3neti.ballot-pdf`
protocol. The initial messages are:

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

Each message is bound to the session, OBT hash, election context, request
sequence, nonce, and ballot serial when allocated. The PDF consumes the
control number at session opening; subsequent ceremony actions are driven by
the embedded protocol client and local service responses.

## 4. Artifact 1: Official Ballot Template PDF

The Official Ballot Template (OBT) is the approved blank ballot master for a precinct and ballot-style scope.

### 4.1 OBT contents

The OBT may contain or be associated with:

- election identifier;
- precinct identifier;
- ballot-style identifier;
- approved ballot layout;
- candidate roster;
- contest definitions and selection limits;
- approved language and accessibility profile;
- QR or machine-readable payload placement;
- security-marking area;
- mapping identity or mapping hash;
- serial-range reference;
- non-secret BEI authorization-policy reference;
- schema version; and
- OBT artifact hash.

### 4.2 OBT security state

The OBT must be:

- generated through an approved EMS ceremony;
- digitally signed or included in a signed manifest;
- hash-locked after approval;
- delivered only to authorized ACM devices;
- protected against unauthorized modification;
- imported with signature and scope verification; and
- retained as controlled election material.

The OBT contains no voter selections and is not itself a cast ballot.
It must not contain a BEI PIN, PIN verifier, private key, or reusable
authorization secret. A non-secret authorization-policy reference may bind
the approved OBT to the offline BEI ceremony rules.

## 5. Artifact 2: OBT Manifest and Serial-Range Manifest

The OBT manifest establishes what the ACM is authorized to use.

### 5.1 Minimum OBT manifest fields

- election identifier;
- precinct identifier;
- ballot-style identifier;
- OBT version;
- OBT content hash;
- mapping hash;
- schema version;
- issuing authority or signer key ID;
- authorized ACM/device scope;
- serial-range reference;
- trust-roster reference;
- generation ceremony reference; and
- manifest signature.

### 5.2 Minimum serial-range manifest fields

- election identifier;
- precinct identifier;
- ballot-style identifier;
- first serial;
- last serial or explicit serial list;
- allocation policy;
- current status summary;
- replacement policy;
- voiding policy;
- manifest hash; and
- manifest signature.

## 6. Artifact 3: Ballot Session

The Ballot Session is the authorized, voter-facing interaction created from the OBT.

### 6.1 Session opening

The session may require a control number or other approved admission credential. The control number authorizes the session; it does not become the ballot signature and should not appear in the public final-ballot payload.

The session must verify:

- authorization status;
- precinct and ballot-style scope;
- ACM identity;
- OBT version and hash;
- serial availability; and
- applicable voter-session rules.

### 6.2 Session interaction

The session must support:

- ballot navigation;
- position and contest navigation;
- candidate selection and deselection;
- review and correction;
- intentional undervoting;
- contest-limit enforcement;
- accessibility controls;
- cancellation and spoilage; and
- final confirmation.

Contest limits must be enforced by the voter interface, the domain layer, the finalization validator, and the independent verifier.

### 6.3 Session states

- `authorized`;
- `opened`;
- `in_progress`;
- `reviewing`;
- `confirmed`;
- `cancelled`;
- `spoiled`;
- `timed_out`; and
- `finalized`.

## 7. Artifact 4: Finalized Ballot PDF

The Finalized Ballot PDF is the per-voter electronic representation produced after confirmation.

### 7.1 Finalized Ballot PDF contents

It must contain or be associated with:

- approved OBT layout;
- human-readable voter selections;
- election identifier;
- precinct identifier;
- ballot-style identifier;
- unique ballot serial;
- mapping hash;
- issuing-device identifier;
- payload schema version;
- signed machine-readable payload;
- QR or equivalent optical representation;
- PDF content hash;
- PDF-native signature result;
- signed detached manifest containing the complete-PDF hash;
- acceptance identity and sequence;
- finalization status; and
- evidence references.

### 7.2 Fields excluded from the final public payload

The final ballot payload must not contain:

- voter name;
- voter registration ID;
- raw control number;
- print PIN;
- voter-linked authorization value; or
- private key material.

### 7.3 Finalization rule

After voter confirmation:

1. The selections are validated.
2. One unused ballot serial is allocated.
3. The canonical payload is constructed and signed.
4. The QR representation is rendered.
5. The Finalized Ballot PDF is generated.
6. A PDF-native signature is applied through the approved adapter.
7. The complete final PDF hash is computed.
8. A detached manifest containing the PDF hash and artifact references is signed.
9. The PDF and signed manifest are committed to WORM/equivalent storage.
10. The acceptance ledger commits the cast event and returns an acknowledgement.
11. The offered A4 voter-verification/audit path is authorized only after durable acceptance. The prototype may allow printing to be skipped; that setting does not demonstrate compliance with the offered profile or VVPAT requirements.

The final artifact must be read-only from the application’s perspective. Any later lifecycle change is an append-only event, not an overwrite of the finalized PDF.

## 8. Artifact 5: A4 Voter-Verification and Audit Record

The offered OpScan with DRE profile includes an A4 human-readable record of
the voter's selections and a Truth QR encoding the corresponding authenticated
selections. The printer, paper stock, layout, private voter inspection,
read-back verification, and custody procedure must follow the approved
profile. COMELEC and the procurement documents must designate whether this is
the statutory official ballot, a VVPAT, a voter receipt, an audit record, or
another defined artifact. Prototype paper-optional operation does not establish
that statutory VVPAT requirements are met.

The print event must record:

- ballot serial;
- final PDF hash;
- printer/device identity;
- print attempt number;
- print result;
- print-fault reason, if any;
- QR read-back result;
- spoilage or cancellation status; and
- operator or ceremony reference.

The voter must be provided the approved private inspection opportunity. Record
privacy-preserving handling, sealing, retention, and audit access. These paper
procedures do not silently change the proposed electronic cast event or tally
input; any conflict rule must be expressly approved and documented.

## 9. Artifact 6: QR Read-Back and Validation Result

Immediately after printing, the ACM may read back the machine-readable payload to verify print correctness.

This validation must be distinct from the official post-close counting event.

The read-back result must record:

- complete payload obtained;
- signature valid or invalid;
- election context valid or invalid;
- precinct valid or invalid;
- ballot style valid or invalid;
- mapping hash valid or invalid;
- serial valid or invalid;
- duplicate status;
- print readability; and
- whether the ballot proceeds to voter inspection, spoilage, or resolution.

## 10. Artifact 7: Post-Close Verification Result

After close of voting, authorized officials verify the durably accepted ballot
PDFs and their evidence manifests, and reconcile the A4 records under the
approved audit procedure. The legal designation and effect of paper/PDF
discrepancies must be determined by COMELEC and reflected in the applicable
profile; software must not presume that paper is irrelevant or automatically
controlling.

The verification result must record:

- acceptance-ledger reference;
- ballot serial;
- complete payload result;
- signature result;
- context result;
- mapping result;
- duplicate/replay result;
- lifecycle status;
- tally eligibility;
- reconciliation reference;
- operator/ceremony reference.

An accepted ballot may contribute to the tally exactly once. A print-time
read-back must not create a second tally contribution. Directory traversal is
only enumeration; acceptance requires authenticated signatures, manifest
references, lifecycle evidence, and the closed cast-set certificate.

## 11. Artifact 8: BEI Ceremony Record

BEI electronic signatures should be applied to ceremony records and reconciliation artifacts, not necessarily to every voter ballot.

Relevant signed records include:

- OBT import and custody certificate;
- opening and zero report;
- configuration and trust-roster confirmation;
- serial-range receipt and release;
- cancellation, spoilage, and replacement records;
- close-polls record;
- ballot and serial reconciliation;
- signed Election Return;
- signed Election Close Certificate;
- evidence manifest; and
- storage or CCS handoff certificate.

Each ceremony record should contain:

- document digest;
- election and precinct context;
- ceremony type;
- ceremony sequence;
- required signer roles;
- BEI signature values;
- certificate/key IDs;
- signing status; and
- prior-event reference.

A BEI PIN authorizes a signing action. The PIN itself is not the signature and must not be embedded in the voter ballot artifact.

## 12. Artifact 9: Precinct Reconciliation Report

The precinct report must reconcile at minimum:

- OBTs imported;
- serials available;
- serials issued;
- PDFs finalized;
- ballots printed;
- print failures;
- spoiled ballots;
- cancelled ballots;
- reprints;
- QR read-backs;
- A4 voter-verification/audit outputs, including recorded print exceptions or prototype omission;
- post-close PDF verification results;
- acceptance-ledger entries;
- accepted ballots;
- rejected ballots;
- unresolved ballots;
- voter receipts printed/deposited where applicable; and
- unused or voided serials.

The report must explain every difference and be signed through the approved BEI ceremony.

## 13. Artifact 10: Election Close Certificate, Signed Election Return, and CCS Handoff

The precinct generates:

- a signed Election Close Certificate containing the close timestamp, last
  acceptance sequence, accepted-cast-set commitment, tally-rules hash,
  trust-roster hash, reconciliation totals, and required signatures;

- the prescribed human-readable Election Return;
- the signed machine-readable Election Return payload;
- the reconciliation report;
- the evidence manifest; and
- the BEI signature bundle.

The CCS independently verifies the complete return before acceptance. Transport success, file receipt, or visual receipt of a QR image does not by itself establish authenticity.

## 14. Status and Custody Summary

```text
OBT approved
  -> OBT imported
  -> serial available
  -> serial reserved
  -> session authorized
  -> ballot confirmed
  -> PDF-native signature applied
  -> signed manifest committed
  -> acceptance ledger committed
  -> A4 voter-verification/audit record produced (prototype may omit; approved procedure governs)
  -> print read-back and voter-inspection evidence recorded
  -> reconciled as an accepted PDF
  -> verified post-close
  -> close certificate issued
  -> reconciled
  -> included or excluded from tally
```

Cancellation, spoilage, replacement, rejection, and unused status are append-only dispositions. No finalized ballot PDF or serial history is silently deleted or overwritten.

## 15. Acrobat Demonstration Profile

The package should provide a demonstration profile that allows external
testers to exercise the complete ballot lifecycle through a browser, Adobe
Acrobat, a nominated precinct endpoint, and an independent directory tally.
This profile is a demonstration adapter and must not redefine the offline
production ceremony.

```text
Tester enters email address
  -> demo service issues one-time control number
  -> demo service sends encrypted ballot PDF
  -> tester opens PDF in Adobe Acrobat
  -> Acrobat requests the control number as the PDF password
  -> tester selects, reviews, and confirms candidates
  -> PDF is saved or finalized for upload
  -> precinct endpoint verifies and commits the ballot artifact
  -> endpoint tallies eligible artifacts automatically
```

The demonstration must keep the following boundaries:

- the email address is demo authorization data and is not placed in the public ballot payload;
- the control number is a PDF access/session credential and is not the ballot signature;
- Acrobat form behavior is a presentation and interaction layer, not the sole contest-limit enforcement mechanism;
- the upload service or package finalizer revalidates selections, creates the canonical signed payload, verifies the QR representation, and hash-locks the final artifact;
- server-side automatic tallying independently verifies signature, context, serial, lifecycle status, and duplicate status; and
- the same finalized PDFs can be copied into nested directories and processed by the independent recursive verifier.

The demonstration should produce a human-readable Election Return look-alike,
clearly marked as a demonstration report, together with machine-readable
verification, tally, duplicate, and reconciliation reports.

The Acrobat demonstration may use PDF encryption and viewer-supported form
features. Production evidence must remain verifiable without Acrobat,
browser scripting, a remote URL, or an Internet connection.

## 16. Open Refinements

This flow remains subject to refinement on:

1. Whether OBT serials are embedded in the PDF or supplied through a signed serial manifest.
2. The commissioned PDF-native signature profile, certificate model, and validation rules required for each finalized ballot PDF, in addition to the signed Truth QR payload and signed detached manifest.
3. Whether immediate QR read-back is only a print-validation ceremony or also an official cast event.
4. COMELEC's legal treatment of the proposed canonical electronic DRE record,
   tally input, VVPAT, applicable law, and TOR.
5. The exact BEI signature threshold and certificate model.
6. The final WORM storage implementation and evidence export format.
7. Which evidence is available to watchers, parties, candidates, and the public.
