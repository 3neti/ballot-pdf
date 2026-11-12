# Manila Composite OBT Happy Path

`ManilaHappyPathScenarioRunner` demonstrates the approved prototype flow for a
composite Manila First District ballot style:

```text
signed OBT manifest
  -> signed precinct package
  -> offline ACM initialization
  -> authorized serial stock
  -> deterministic valid selections
  -> finalized PDF and signed Truth QR
  -> PDF-native signature and signed detached manifest
  -> WORM bundle and acceptance ledger
  -> recursive verification
  -> Election Close Certificate
  -> deterministic Election Return
```

The runner receives the OBT manifest, precinct package, contest definitions,
ballot count, serial prefix, deterministic seed, evidence root, and an injected
package signer. It refuses to run when the precinct package does not match the
OBT hash or ballot style, or when the requested serials exceed the authorized
range.

The happy path is intentionally synthetic. It proves orchestration and evidence
shape; it does not replace commissioned PKI, acceptance-ledger deployment,
physical custody, or production PDF materialization. The production signer,
PDF-native signature adapter, Truth QR codec, PDF materializer, WORM
implementation, and acceptance authority remain injected boundaries.
