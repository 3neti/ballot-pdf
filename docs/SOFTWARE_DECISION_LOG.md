# Ballot PDF Software Decision Log

## Document Purpose

This document records practical research and development decisions for the
Ballot PDF system. It preserves the reasoning behind software selection and
records what was tested, when it was tested, where it was tested, how it was
validated, and whether a decision applies only to the prototype or also to a
future production appliance.

This is an engineering decision log. It is not a procurement approval,
security certification, legal opinion, or COMELEC acceptance document.

## Decision Statuses

- `proposed`: under consideration;
- `prototype`: approved for experimentation only;
- `validated`: demonstrated against the current acceptance checks;
- `production-candidate`: suitable for procurement evaluation, subject to licensing and certification; and
- `rejected`: not selected, with the reason recorded.

## System Boundary

```text
3neti/ballot-pdf
  -> ballot domain, protocol, payloads, signatures, QR, lifecycle,
     verification, storage contracts, tally, and reports

Viewer/PDF adapter
  -> PDF rendering, forms, encryption, materialization, and printing handoff

ACM local service
  -> offline protocol endpoint and commissioned appliance ceremonies

EMS/CCS applications
  -> generation, import/export, evidence handling, and reporting workflows
```

The viewer or PDF engine must not become the source of election-domain truth.
The finalization and verification contracts remain in `3neti/ballot-pdf`.

## Decision Register

### D-001: Adobe Acrobat Reader

| Field | Decision |
| --- | --- |
| Status | `proposed` |
| What | Adobe Acrobat Reader desktop |
| Why | Compatibility testing for ordinary PDF opening, viewing, form filling, password-protected PDFs, and printing |
| When | Prototype interoperability phase |
| Where | Developer workstation and selected compatibility-test machines |
| How | Install the official Reader build or Homebrew cask; open test OBT and finalized PDFs; record rendering, form, password, print, and QR results |
| Production role | None selected. Reader is a test/reference viewer only |
| Cost posture | Free viewer; verify current Adobe terms before redistribution |

Reader is useful for determining how a recipient or observer sees the ballot
artifact. It is not the authoring environment for the ballot PDF and is not
the planned ACM runtime.

Reference: [Adobe Acrobat plans](https://www.adobe.com/acrobat/plans)

### D-002: Adobe Acrobat Pro

| Field | Decision |
| --- | --- |
| Status | `proposed` |
| What | Adobe Acrobat Pro desktop |
| Why | Authoring and debugging interactive PDF forms, document JavaScript, password protection, signatures, field locking, and print behavior |
| When | First PDF ballot prototype and Acrobat interoperability tests |
| Where | Developer workstation; not deployed to precinct devices |
| How | Use the official trial or licensed desktop application; create a minimal OBT; add form fields and document scripts; test control-number opening, contest limits, review, finalization, save, print, and protocol submission |
| Production role | Public demonstration adapter only, unless separately approved later |
| Cost posture | Trial first; production licensing is not part of this decision |

Acrobat Pro is the reference environment for the PDF-centric public
demonstration. Its behavior must not be assumed to define the Nutrient ACM
runtime.

Reference: [Adobe Acrobat Pro trial](https://www.adobe.com/acrobat/free-trial-download.html)

### D-003: Nutrient Web SDK for ACM

| Field | Decision |
| --- | --- |
| Status | `proposed` |
| What | Nutrient Web SDK running in the local ACM browser/WebView |
| Why | PDF-centric voter interaction, forms, local rendering, materialization, printing, and offline-capable browser deployment |
| When | ACM proof of concept after the Acrobat PDF behavior is characterized |
| Where | Local Vue/Chromium kiosk or equivalent ACM WebView; first on a developer workstation, then on target tablet hardware |
| How | Use the trial SDK; load the approved encrypted OBT; connect the viewer to the local Ballot PDF Protocol; export form state to the local finalization service; print and verify the resulting PDF |
| Production role | Preferred ACM viewer candidate, subject to offline licensing, hardware testing, and procurement approval |
| Cost posture | Trial for evaluation; production requires a negotiated commercial license and explicit offline deployment rights |

Nutrient-specific types remain behind `@3neti/ballot-pdf-nutrient` or an
equivalent adapter. Nutrient does not own ballot rules, Ed25519 payload
signing, QR semantics, lifecycle eligibility, or tallying.

Reference: [Nutrient SDK trial](https://www.nutrient.io/sdk/try),
[Nutrient Web SDK](https://www.nutrient.io/sdk/web/getting-started/),
[Nutrient deployment options](https://www.nutrient.io/sdk/deployment-options/)

### D-003-A: Nutrient integration quickstart prompt

| Field | Decision |
| --- | --- |
| Status | `prototype` |
| Source | Nutrient Web SDK website |
| Prompt | `Integrate Nutrient Web SDK in my application. Use getapdfviewer.com` |
| Intended use | Starting instruction for a prototype integration or coding assistant |
| Required treatment | Use only as a scaffolding lead; verify generated code against official Nutrient documentation and this decision log |
| Production role | None by itself |

The prompt is not, by itself, a Composer dependency, license grant, SDK
distribution mechanism, or production deployment instruction. The prototype
must still use the planned `@3neti/ballot-pdf-nutrient` boundary and must keep
ballot rules, signing, QR semantics, lifecycle, evidence, and tallying in
`3neti/ballot-pdf`.

### D-004: `3neti/ballot-pdf-nutrient` adapter

| Field | Decision |
| --- | --- |
| Status | `proposed` |
| What | Frontend adapter around Nutrient Web SDK |
| Why | Keep commercial viewer APIs out of the election domain and allow replacement of the viewer engine |
| When | After the first Nutrient proof of concept |
| Where | ACM frontend package, consumed by the local Vue application |
| How | Expose viewer/session/form export/finalization bridge/offline capability ports; communicate with the local Ballot PDF Protocol |
| Production role | Required integration boundary if Nutrient is selected |
| Cost posture | Do not bundle Nutrient binaries or license keys in the public package |

Candidate frontend surface:

```text
NutrientBallotViewer
NutrientBallotSession
NutrientFormStateExporter
NutrientFinalizationBridge
NutrientOfflineCapabilityCheck
```

### D-005: `3neti/ballot-pdf` package scaffolding

| Field | Decision |
| --- | --- |
| Status | `proposed` |
| What | Framework-neutral Composer package for ballot PDF artifacts |
| Why | Establish stable domain and protocol contracts before binding to a PDF engine or viewer |
| When | After the Acrobat and Nutrient proof-of-concept findings are recorded |
| Where | `/Users/rli/PhpstormProjects/packages/ballot-pdf` |
| How | Scaffold domain value objects, application services, ports, deterministic payloads, lifecycle events, verification, recursive tally, and test doubles first |
| Production role | Core election-artifact and verification library candidate |
| Cost posture | Keep commercial SDK integrations optional adapters |

Initial package boundaries:

```text
Core:
  ballot model
  canonical payload
  Ed25519 signing ports
  Truth QR ports
  ballot lifecycle
  evidence manifests
  recursive verification
  tally and reports

Adapters:
  Nutrient Web SDK
  PDF generation/materialization
  local protocol transport
  filesystem/WORM storage
  printer and scanner
```

## Prototype Test Matrix

| Test | Acrobat Pro | Acrobat Reader | Nutrient Web SDK | `3neti/ballot-pdf` |
| --- | --- | --- | --- | --- |
| Open OBT PDF | required | required | required | inspect/verify |
| Control-number/password opening | create and debug | consume | consume through adapter | authorize session |
| Candidate form interaction | reference | compatibility | target runtime | validate state |
| Contest-limit behavior | reference script | observe | target behavior | authoritative revalidation |
| Review/correction/cancel | reference | observe | target behavior | lifecycle validation |
| Signed payload | inspect result | verify display | handoff only | authoritative creation |
| Truth QR | inspect/print | inspect/print | render/materialize | create/verify |
| Printing | reference | compatibility | ACM handoff | record event |
| Recursive tally | not applicable | not applicable | not applicable | authoritative verifier |

## Evidence to Preserve

For every meaningful test, preserve:

- software name and version;
- operating system and hardware;
- SDK/license mode;
- input OBT and manifest hashes;
- test control number or synthetic credential identifier;
- test selections;
- generated PDF hash;
- screenshots or screen recordings where useful;
- printed-page scan where useful;
- QR verification result;
- protocol request and response fixtures with secrets removed;
- failure behavior; and
- tester/date/result.

Do not place real voter identity, real control numbers, private keys, or
production credentials in this log or its test fixtures.

## Installation Record

Complete this section as software is installed. Do not record secrets.

| Date | Machine | Software | Version | Installation method | Result | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| 2026-11-09 | Development workstation | Homebrew catalog check | Homebrew 7.0.7 | `brew search --cask` | Adobe casks available | Nutrient requires vendor trial/download |
| 2026-11-09 | Development workstation | Adobe Acrobat Pro | 26.003.22075 | Homebrew cask `adobe-acrobat-pro` | Cask installed; app launch pending | Homebrew reports the cask installed, but the Acrobat Pro application bundle was not found under `/Applications` during verification. Pro authoring validation remains pending. |
| 2026-11-09 | Development workstation | Adobe Acrobat Reader | 26.001.21662 | Homebrew cask `adobe-acrobat-reader` | Installed | `/Applications/Adobe Acrobat Reader.app` is present. Reader compatibility validation remains pending. |
| 2026-11-09 | Development workstation | Nutrient Web SDK | 1.25.0 | `npm install @nutrient-sdk/viewer@1.25.0` | Prototype installed | Installed in `prototype/nutrient-vue-host`; vendor assets are copied at prepare time and ignored by source control. Trial/license key remains pending. |
| 2026-11-09 | Development workstation | Nutrient Vue host | Vite 7.3.8 / Vue 3.5.44 / Vitest 4.0.0 | Local prototype scaffold | Browser smoke test passed | Encrypted synthetic OBT opened with `DEMO-000001`; local session and live finalization protocol completed. |

### Prototype validation record

- Operating system: macOS 15.6 (build 24G84).
- Runtime: Node 22.21.1, npm 10.9.4, PHP 8.4.22.
- OBT fixture: `prototype/nutrient-vue-host/public/fixtures/obt-synthetic-encrypted.pdf`.
- OBT SHA-256: `d9d287b9ba0573e08b23bd9901d008ed8d607510e9de189d5f99db6f02cd7071`.
- OBT protocol label: `synthetic-obt-hash` (fixture label used by the mock protocol; production manifest binding is not implemented here).
- Control number: `DEMO-000001` (synthetic only).
- Password behavior: Nutrient opened the encrypted PDF after the control number was supplied.
- Acrobat contest-limit behavior: the regenerated fixture embeds document JavaScript that marks the third senator field read-only after two selections and displays a validation alert if an over-limit state is encountered. Acrobat/Reader execution still depends on document JavaScript being enabled.
- Automated PDF fixture tests: `npm test` now runs four native Node tests covering encrypted PDF validity, embedded Acrobat field actions, the two-selection rule model, and fixture credential hygiene. These tests verify structure and logic; they do not execute Acrobat JavaScript.
- Offline/local assets: Nutrient assets were served from the local `/nutrient-web/` path; no external network was required by the browser smoke test.
- Protocol fixtures: session open, finalization, and print acknowledgements were captured in the in-browser event log with synthetic identifiers and no secrets.
- Live finalization: the Vue host sent exported PDF bytes and selections to `ballot.finalize`; the reference service returned a finalized PDF hash and signed Truth QR scan, and committed PDF plus manifest evidence to logical-WORM storage.
- Finalized artifact handoff: `ballot.finalize` now returns the finalized PDF as base64 plus the PDF hash, deterministic evidence-manifest hash, evidence fields, and Truth QR read-back. The Vue host provides download and evidence-summary actions while keeping raw PDF bytes out of the event log.
- Recursive verification: the local `evidence.verify` route now traverses the logical-WORM root, verifies signed QR payloads, checks declared manifest PDF hashes against stored PDFs, classifies duplicate serials, and returns a deterministic report hash. Earlier demo artifacts signed before the demo trust key was persisted are intentionally not accepted; injectable persistent trust-roster support remains the next security slice.
- Deterministic Election Return: `election.return` now rebuilds the tally from accepted verification results and returns a canonical, timestamp-free artifact with eligible ballot serials, payload hashes, context, tally, source-verification hash, and artifact SHA-256. The demonstration is not a prescribed COMELEC form.
- Persistent trust roster: signing and verification now use the injectable `PersistentTrustRoster`, which loads active Ed25519 keys from a local JSON roster and scopes them by purpose, election, and precinct. The prototype roster remains synthetic and temporary; production key ceremony and custody are still deferred.
- Key ceremony and rotation evidence: added split public-roster and private-custody providers plus `KeyCeremonyManager`. Versioned public rosters, mode-restricted private key records, ceremony records, rotation ledger entries, and hashes are now produced without placing secret key material in distributed roster files. HSM/OS keychain integration and human-controlled ceremony approval remain deployment work.
- Deployment hardening: added a macOS Keychain adapter, an injected signing-key boundary suitable for HSM integration, a two-person approval gate, hash-checked roster distribution records, and encrypted escrow/recovery records. These are reference controls; production provisioning, identity verification, and recovery drills remain operational work.
- QR read-back: `pdftoppm` plus `zbarimg` read the signed Truth QR from the finalized PDF; `qpdf --check` reported no syntax or stream-encoding errors.
- Finalized PDF behavior: the reference materializer sets the review field to `FINALIZED`, flattens the form, embeds the QR, and preserves the paper-ballot/legal-record note. The Nutrient evaluation build adds a visible evaluation watermark, which must be addressed through licensing or a production deployment decision.
- Automated prototype coverage: the Vitest adapter tests are present, but the current Vitest/Vite module-runner combination fails before test collection with `Unknown method: getBuiltins`; the production Vite build succeeds. This is a tooling compatibility gap, not a ballot-domain failure.
- Acrobat compatibility: Adobe Acrobat Reader is installed but remains at its first-run license agreement, so opening/saving/printing compatibility has not yet been completed. Acrobat Pro's application bundle was not found under `/Applications`; authoring validation remains pending.
- Viewer notes: Nutrient emitted only the vendor warning for the package's copy-based asset setup; the viewer loaded the encrypted AcroForm, exposed three checkboxes and a review field, and completed the smoke test. These are prototype follow-ups, not production acceptance results.

## Decision Gate Before Production Licensing

Do not purchase or deploy production licenses until the following evidence is
complete:

- Acrobat reference PDF renders and prints correctly;
- Nutrient renders the same OBT and supports the required form workflow;
- Nutrient operates on target ACM hardware in offline mode;
- the local protocol completes session, finalization, print, and evidence flows;
- `3neti/ballot-pdf` independently verifies the finalized artifact;
- QR read-back succeeds from the printed page;
- recursive tally reproduces the expected result; and
- Nutrient confirms offline appliance/OEM licensing in writing.

## Change Log

| Date | Change |
| --- | --- |
| 2026-11-09 | Created the software decision log before installing Adobe or Nutrient. Recorded Adobe Reader, Adobe Pro, Nutrient Web SDK, the Nutrient adapter, and core package-scaffolding decisions. |
| 2026-11-09 | Recorded Nutrient's integration quickstart prompt as a prototype scaffolding lead, not as a production dependency or license decision. |
| 2026-11-09 | Implemented the isolated Vue/Vite Nutrient host, synthetic encrypted OBT fixture, local protocol bridge, and reproducible build/smoke-test path. No core ballot domain changes were made. |
| 2026-11-09 | Initial Adobe installation attempt was blocked by macOS administrator authorization; no production or trial credentials were stored. |
| 2026-11-09 | Adobe Acrobat Reader installation completed successfully. Homebrew reports Acrobat Pro's cask as installed, but its application bundle still requires verification before authoring tests can begin. |
| 2026-11-09 | Vendor credential check: the Nutrient Portal rejected both supplied password attempts; the official Web SDK trial page indicates a key is not required for initial evaluation. Adobe account creation rejected the supplied password; no password or credential was written to project files. |
| 2026-11-09 | Installed `qpdf` 12.4.2. Password-aware inspection of the synthetic encrypted OBT passed with no syntax or stream-encoding errors. |
| 2026-11-09 | Replaced the visual-only OBT fixture with a reproducible `pdf-lib` AcroForm generator and AES-256 `qpdf` encryption. Nutrient browser smoke test opened the PDF, selected two candidates, exported the completed PDF, and acknowledged finalization. |
| 2026-11-09 | Scaffolded `3neti/ballot-pdf`, the `@3neti/ballot-pdf-nutrient` adapter boundary, the local JSON protocol reference service, filesystem logical-WORM storage, recursive verification, and deterministic reports. |
| 2026-11-11 | Added the Adobe meeting discussion outline at `~/Downloads/Adobe_Ballot_PDF_Meeting_Discussion_Points.md` and upgraded the local protocol reference route to invoke the package finalizer with synthetic Ed25519/Truth QR evidence. |
| 2026-11-11 | Completed the live Vue-to-service finalization path with exported PDF bytes, selections, control-number decryption, signed Truth QR embedding, PDF flattening, logical-WORM evidence commit, and zbar read-back. |
| 2026-11-11 | Added the raw-PDF QR scanner path to recursive verification and recorded the remaining viewer/licensing and Vitest runner gaps. |
| 2026-11-11 | Replaced the timer-based contest-limit script with click-triggered Acrobat field actions and corrected unchecked-value handling, eliminating the intermittent initial locking behavior observed in Reader. |
| 2026-11-11 | Suppressed Acrobat's interactive field highlight color in the fixture so unchecked fields remain visually plain; the ballot mark is represented by the check itself. |
| 2026-11-11 | Added native PDF widget highlight mode `/N` in addition to the Acrobat JavaScript setting, because Reader continued to display its preference highlight color. |
| 2026-11-11 | Added a native `npm test` suite for fixture structure and selection-rule regression coverage; actual Acrobat JavaScript execution remains an integration test. |
| 2026-11-11 | Acrobat debugger exposed `InvalidSetError` for `Field.highlight`; removed that unsupported assignment because it aborted the contest-limit function. Gray field fill is now documented as a Reader display preference, while selection enforcement remains independent. |
| 2026-11-11 | Fixed local demo WORM collisions by deriving a unique deterministic ballot serial per opened session and disabling repeat finalization after a successful commit. |
| 2026-11-11 | Added the PHP lifecycle scenario runner covering stock/session, contest enforcement, finalization, Truth QR verification, WORM evidence, recursive verification, tally, reports, and repeat-finalization rejection. Pest now passes 7 tests with 23 assertions. |
| 2026-11-11 | Added `docs/TODO.md` with the deferred audit-ready evidence package and the next prototype integration steps. |
