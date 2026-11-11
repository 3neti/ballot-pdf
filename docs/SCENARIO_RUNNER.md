# PHP Lifecycle Scenario Runner

`LBHurtado\BallotPdf\Scenario\LifecycleScenarioRunner` exercises the
deterministic ballot-PDF workflow without a browser or PDF viewer:

```text
stock -> session -> contest validation -> finalization -> Truth QR verification
      -> WORM evidence -> recursive verification -> tally -> reports
```

The scenario also asserts that an over-limit selection is rejected and that a
second finalization of the same session cannot overwrite committed evidence.

Run it with the package test suite:

```bash
vendor/bin/pest tests/Scenario/LifecycleScenarioTest.php
```

This runner validates domain and evidence behavior. It does not execute
Acrobat JavaScript or replace browser compatibility tests.
