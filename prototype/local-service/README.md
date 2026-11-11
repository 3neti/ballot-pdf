# Local Ballot PDF Protocol Service

This is a framework-neutral reference endpoint for the Nutrient host. It is
intentionally a demonstration service: it validates protocol envelopes,
accepts the exported PDF bytes and selections, decrypts the synthetic fixture
under the supplied control number, embeds signed Truth QR evidence, commits a
logical-WORM artifact, and returns deterministic acknowledgement data. Physical
printing remains an application adapter.

`ballot.finalize` returns the finalized PDF as `pdf_base64`, together with
`pdf_sha256`, `evidence`, `evidence_manifest_sha256`, and the signed
`truth_qr_scans`. The Vue host keeps the base64 payload out of its event log,
offers a download of the returned PDF, and exposes the hashes and read-back
count through its evidence panel.

`evidence.verify` recursively scans the logical-WORM evidence root, verifies
signed QR payloads and manifest-to-PDF hashes, classifies duplicates, and
returns a deterministic report hash. The demo trust key is generated once in
the system temporary directory and is intentionally not a production key
roster. The roster is loaded through the package's injectable
`PersistentTrustRoster` boundary and scopes keys by purpose, election, and
precinct.

The service now uses the split demonstration custody path: a versioned public
roster is distributed to verification, while the private signing key is kept
in a separate mode-restricted custody file. Ceremony records and the rotation
ledger are written beside the roster; no private key is included in the public
roster.

`election.return` rebuilds the accepted tally from recursive verification
results and returns a canonical, timestamp-free Election Return JSON artifact,
its SHA-256, and the eligible ballot list. It is a demonstration artifact and
not a legally prescribed COMELEC form.

Run from the package root:

```bash
php -S 127.0.0.1:8017 -t prototype/local-service prototype/local-service/router.php
```

Set the Vue host environment variable:

```text
VITE_PROTOCOL_BASE_URL=http://127.0.0.1:8017
```

Each opened demo session receives a deterministic synthetic ballot serial. A
finalized session cannot be submitted twice; this preserves the logical-WORM
behavior while allowing repeated demonstrations.
