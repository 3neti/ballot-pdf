# PHP Lifecycle Scenario Runner

`LBHurtado\BallotPdf\Scenario\LifecycleScenarioRunner` exercises the
deterministic ballot-PDF workflow without a browser or PDF viewer:

```text
stock -> session -> contest validation -> finalization -> PDF authentication
      -> signed manifest -> WORM bundle -> acceptance ledger
      -> recursive verification -> close certificate -> deterministic Election Return -> reports
```

The scenario also asserts that an over-limit selection is rejected and that a
second finalization of the same session cannot overwrite committed evidence.
The acceptance boundary is covered by the acceptance-ledger feature tests,
including idempotent retries, changed-content conflicts, serial uniqueness,
and cast-set close-certificate generation.

Run it with the package test suite:

```bash
vendor/bin/pest tests/Scenario/LifecycleScenarioTest.php
```

This runner validates domain and evidence behavior. It does not execute
Acrobat JavaScript or replace browser compatibility tests. A production
scenario must inject a PDF-native signature adapter and commissioned signing
keys; the reference tests use isolated test signers.
