# TODO

## Remaining Audit-Ready Deployment Work

The reference acceptance, closure, verification, and A4 workflow is now
implemented. The following production and commissioning work remains:

- durable signing keys, public-key trust roster, and key-ceremony evidence;
- signed BEI/operator authorization and lifecycle records;
- OBT, ballot-stock, device-configuration, and mapping manifests;
- issued, used, spoiled, cancelled, and unused ballot reconciliation;
- append-only device and ceremony logs with printable audit reports;
- physical paper-ballot custody, handoff, and reconciliation records;
- Election Return and precinct-level reconciliation artifacts;
- independent replay and verification tooling;
- production acceptance-ledger deployment, idempotency recovery, and serial-authority controls;
- independent cast-set checkpoints and commissioned closure governance;
- evidence retention, archival, and package-level hashes; and
- end-to-end audit scenarios using non-synthetic fixtures.

This is an engineering backlog item, not a claim of legal compliance.

## Immediate Next Steps

- [x] Return the finalized PDF from `ballot.finalize` to the Vue host.
- [x] Add Download Finalized PDF and View Evidence actions.
- [x] Run recursive verification against the latest finalized artifact.
- [x] Add a deterministic Election Return/tally artifact to the scenario runner.
- [x] Replace ephemeral demo signing keys with an injectable persistent trust roster.
- [x] Add acceptance-ledger domain port and filesystem reference implementation.
- [x] Add dual-authenticated bundle acceptance service boundary.
- [x] Add deterministic Election Close Certificate domain and builder.
- [x] Route lifecycle and Manila scenarios through authenticated acceptance and closure.
- [x] Add deterministic A4 audit-output, print, QR read-back, discrepancy, and custody workflow.
- [x] Add manifest validation, content-bound retries, durable local sequence state, and recovery reporting.

## Deployment Hardening Status

- [x] OS keychain-compatible signing-key provider.
- [x] HSM-compatible signing boundary through injected key custody/signing ports.
- [x] Human-controlled two-person ceremony approval gate.
- [x] Hash-checked public-roster distribution records.
- [x] Encrypted key escrow and recovery fixture.
- [ ] Production HSM/OS keychain provisioning, quorum identity verification, and operational recovery drill.
- [x] Integrate acceptance commit and close-certificate generation into the end-to-end scenario runner and protocol service.
- [ ] Commission a real PDF-native signature adapter and validate it in Acrobat, Nutrient, and the independent verifier.
- [ ] Replace the deterministic reference signer with the commissioned standards-based PDF signature adapter.
