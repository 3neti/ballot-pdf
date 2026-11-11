# TODO

## Audit-Ready Election Evidence Package

Deferred until the prototype workflow is stable. This package must add:

- durable signing keys, public-key trust roster, and key-ceremony evidence;
- signed BEI/operator authorization and lifecycle records;
- OBT, ballot-stock, device-configuration, and mapping manifests;
- issued, used, spoiled, cancelled, and unused ballot reconciliation;
- append-only device and ceremony logs with printable audit reports;
- physical paper-ballot custody, handoff, and reconciliation records;
- Election Return and precinct-level reconciliation artifacts;
- independent replay and verification tooling;
- evidence retention, archival, and package-level hashes; and
- end-to-end audit scenarios using non-synthetic fixtures.

This is an engineering backlog item, not a claim of legal compliance.

## Immediate Next Steps

- [x] Return the finalized PDF from `ballot.finalize` to the Vue host.
- [x] Add Download Finalized PDF and View Evidence actions.
- [ ] Run recursive verification against the latest finalized artifact.
- [ ] Add a deterministic Election Return/tally artifact to the scenario runner.
- [ ] Replace ephemeral demo signing keys with an injectable persistent trust roster.
