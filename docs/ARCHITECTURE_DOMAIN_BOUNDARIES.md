# EMS Architecture Domain Boundaries

**Status:** R&D architecture guide  
**Scope:** Election Management System, Official Ballot Template generation, precinct packages, and ballot-PDF integration  
**Principle:** Public election artifacts guide and test the EMS; the EMS generates the approved OBT and operational packages.

**DRE record terminology:** In the proposed OpScan with DRE profile, the
finalized signed Ballot PDF is the canonical electronic DRE voting record and
proposed tally input. This engineering term does not designate it as the
statutory “official ballot” or waive any paper/VVPAT requirement. OMR remains a
separate paper-marked workflow. See the
[legal-position note](LEGAL_POSITION_DRE_CANONICAL_ELECTRONIC_RECORD.md) before
using this design for an official election.

The OpScan with DRE acceptance boundary is:

```text
voter confirmation
  -> PDF-native signature
  -> complete-PDF hash
  -> signed detached manifest
  -> WORM bundle commit
  -> acceptance-ledger commit
  -> durable acceptance acknowledgement
```

The acceptance ledger owns the cast decision. The WORM store owns immutable
artifact preservation. Election closure is represented by a signed certificate
committing to the accepted cast set and approved tally rules.

---

## 1. Architectural Position

The EMS is a deterministic compiler over immutable election-source snapshots.

```text
Static source snapshots
  + election rules
        ↓
Domain registries
        ↓
Ballot-style resolution
        ↓
EMS compilation
        ↓
Generated OBT and operational packages
        ↓
ACM import and ballot preparation
```

The published COMELEC OBT is not the production source template for our EMS.
It is a public reference artifact used for research, conformance comparison,
and regression testing.

```text
COMELEC published OBT
  = guide, reference, and golden comparison fixture

CLC + POP + election rules
  = EMS static inputs

Our EMS
  = OBT generator and compiler

Generated OBT
  = EMS-owned output for approval, signing, and distribution
```

---

## 2. Domain Map

```text
Election Source Archive
        ↓
Candidate Registry ─────┐
Project of Precincts ───┼──> Ballot Style Resolver ──> OBT Generator
Election Rules ─────────┘                                  ↓
                                                     EMS Compiler
                                                          ↓
                         ┌────────────────────────────────┼────────────────┐
                         ↓                                ↓                ↓
                    OBT package                    Precinct package   Stock package
                         ↓                                ↓                ↓
                                      ACM Provisioning / Custody
                                                   ↓
                                              Ballot PDF
```

---

## 3. Domain Boundaries

### 3.1 Election Source Archive

**Owns:**

- downloaded public source files;
- source URL and publication reference;
- retrieval timestamp;
- source file hash;
- parser version;
- source status; and
- provenance records.

**Does not own:**

- normalized candidate identity;
- ballot-style decisions;
- generated OBTs; or
- production precinct assignments.

**Role:** Evidence and reproducibility layer.

---

### 3.2 Candidate Registry / CLC

**Owns:**

- candidate records;
- ballot names;
- legal names;
- political parties;
- office;
- district;
- locality;
- candidate number;
- source reference; and
- candidate record hash.

**Does not own:**

- ballot layout;
- OBT generation;
- precinct clustering; or
- device assignment.

**Role:** Candidate and office data authority for EMS compilation.

---

### 3.3 Project of Precincts / POP

**Owns:**

- precinct IDs;
- clustered precincts;
- polling places;
- barangays;
- cities and municipalities;
- legislative districts;
- provinces;
- regions; and
- precinct status.

**Does not own:**

- candidate names;
- contest limits;
- OBT layout;
- cryptographic keys; or
- ACM device assignment.

**Role:** Geographic and precinct topology authority.

---

### 3.4 Election Rules

**Owns:**

- offices;
- contest limits;
- election scope;
- national/local/BARMM applicability;
- district and locality relationships;
- eligibility rules; and
- contest ordering rules.

**Role:** Legal and functional rule input to ballot-style compilation.

Example:

```json
{
  "contest_code": "SENATOR",
  "title": "Senator",
  "maximum_selections": 12,
  "scope": "national"
}
```

---

### 3.5 Ballot Style Resolver

**Owns:**

- ballot-style identity;
- contest inclusion;
- candidate inclusion;
- jurisdiction composition;
- national-only versus composite classification;
- OBT reference selection; and
- ballot-style validation reports.

**Consumes:**

- Candidate Registry;
- POP;
- Election Rules; and
- reference OBT observations.

**Does not generate:**

- final PDF layout;
- serial stock; or
- device custody records.

**Role:** Determines what a valid ballot style contains.

Examples:

```text
National-only
  Senators + Party List

Manila composite
  Senators + Party List + House + Mayor + Vice-Mayor + Councilors

Provincial composite
  National + District + Provincial + Local contests
```

---

### 3.6 OBT Generator

**Owns:**

- official OBT PDF generation;
- candidate-field mapping;
- visual layout;
- pagination;
- contest headings;
- selection controls;
- metadata;
- Truth QR placement reservation;
- OBT hash; and
- generated OBT conformance report.

**Consumes:**

- resolved ballot style;
- candidate registry;
- election rules; and
- rendering profile.

**Does not own:**

- source candidate ingestion;
- POP maintenance;
- serial allocation; or
- ACM custody.

**Role:** Produces the principal EMS deliverable: the approved OBT.

The generated OBT is a product of the EMS. The published COMELEC OBT is only a
reference and comparison artifact.

---

### 3.7 EMS Compiler

**Owns:**

- compilation orchestration;
- input manifest selection;
- compiler version;
- deterministic output;
- generated OBT package;
- precinct package;
- serial-stock package;
- package hashes; and
- approval/signing boundaries.

**Compilation contract:**

```text
same source hashes
+ same rule version
+ same compiler version
    = same generated output hashes
```

---

### 3.8 Ballot Stock and Serial Domain

**Owns:**

- authorized serial ranges;
- stock manifests;
- serial allocation;
- issuance;
- cancellation;
- spoilage;
- unused stock; and
- serial reconciliation.

**Does not own:**

- candidate identity;
- precinct topology;
- OBT visual design; or
- cryptographic trust-roster administration.

---

### 3.9 ACM Provisioning and Custody

**Owns:**

- device identity;
- device assignment;
- package import;
- offline initialization;
- OBT verification;
- precinct-package verification;
- BEI authorization; and
- custody events.

**Does not own:**

- candidate registry maintenance;
- OBT design decisions;
- candidate ordering; or
- EMS source compilation.

---

### 3.10 Ballot PDF and Truth QR

**Owns:**

- voter-facing PDF interaction;
- candidate-code selection;
- contest-limit enforcement;
- finalization;
- Truth QR construction;
- signature verification;
- evidence manifests;
- recursive verification; and
- deterministic tally artifacts.

**Consumes:**

- approved OBT;
- approved mapping;
- precinct context;
- serial authorization; and
- trust roster.

---

## 4. Static Input Model

Static does not mean unstructured or unversioned. Every input package must be
immutable, hashable, and reproducible.

```json
{
  "package_type": "candidate-registry",
  "election_id": "2025NLE",
  "source_reference": "CLC2025",
  "retrieved_at": "2026-11-12T00:00:00Z",
  "parser_version": "1.0.0",
  "files": [
    {
      "path": "NCR/MANILA.pdf",
      "sha256": "...",
      "media_type": "application/pdf"
    }
  ],
  "package_sha256": "..."
}
```

Required properties:

- canonical JSON manifests;
- source-file hashes;
- parser version;
- schema version;
- election ID;
- publication reference;
- provenance;
- deterministic ordering; and
- explicit status: tentative, approved, superseded, or withdrawn.

---

## 5. Reference OBT Conformance

The public OBT is used as a golden comparison fixture.

```text
Generated OBT
    vs.
Published reference OBT
```

Comparison dimensions:

- election label;
- jurisdiction label;
- precinct/cluster label;
- contest count;
- contest order;
- candidate count;
- candidate number;
- candidate display name;
- party label;
- selection limit;
- page count;
- pagination;
- field position; and
- visible text.

Differences must be reported, not silently corrected.

---

## 6. Package Strategy

The initial implementation should use explicit modules in one EMS workspace.

Extract a domain into an independent Composer package only when at least one of
these conditions is satisfied:

- the schema is stable;
- the domain is independently reusable;
- the domain has an independent test suite;
- the domain has an independent release cadence; or
- the domain is consumed by more than one application.

Likely first package candidates:

1. `3neti/candidate-registry`
2. `3neti/project-of-precincts`
3. `3neti/election-rules`
4. `3neti/ballot-style`
5. `3neti/ems-compiler`
6. `3neti/ballot-pdf`

Premature package extraction is not required for clean domain separation.

---

## 7. Initial R&D Fixture Set

### Fixture A: Manila Composite

```text
CLC snapshot
+ POP snapshot
+ election rules
+ published Manila reference OBT
    ↓
MANILA-1ST-DISTRICT-COMPOSITE-2025
```

### Fixture B: Overseas National-Only

```text
CLC national snapshot
+ overseas rules
+ overseas reference OBT
    ↓
NATIONAL-OVERSEAS-2025
```

These two fixtures test whether the EMS supports both national-only and
composite domestic ballot styles.

---

## 8. Implementation Order

1. Source archive and manifest schema.
2. Candidate Registry import and normalization.
3. POP import and normalization.
4. Election Rules schema.
5. Ballot-style scope resolver.
6. Manila composite fixture.
7. Overseas national-only fixture.
8. OBT generator.
9. Reference OBT conformance report.
10. EMS compiler package output.
11. ACM package import.
12. Serial ballot generation and Truth QR finalization.

---

## 9. Architectural Guardrails

- Public OBTs guide and test the EMS; they do not replace EMS generation.
- CLC owns candidate data, not ballot layout.
- POP owns precinct topology, not device assignment.
- Election Rules own contest limits and scope.
- Ballot Style resolves content; OBT Generator renders it.
- EMS Compiler produces signed outputs; ACM consumes them.
- Serial Stock owns serial identity, not candidate identity.
- Ballot PDF owns finalization and evidence, not source registry maintenance.
- Static inputs are immutable and hash-locked.
- Derived outputs are reproducible and independently verifiable.
- No domain silently repairs another domain’s data.
- Differences from a reference OBT become explicit conformance findings.
