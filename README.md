# `3neti/ballot-pdf`

Framework-neutral ballot PDF domain and evidence services for the WAES/OpScan
with DRE prototype.

The package keeps paper as the controlling legal record. It owns deterministic
ballot context, contest validation, signed Truth QR payload integration,
finalization boundaries, lifecycle evidence, recursive verification, and
reports. PDF viewers, printers, physical custody, and appliance deployment are
adapters.

## Development

```bash
COMPOSER_CACHE_DIR=/tmp/ballot-pdf-composer-cache composer install
vendor/bin/pest
```

The package uses the existing `3neti/truth-qr` `aes-election-v2` profile for
Ed25519 signed ballot payloads.

The reference local protocol service is documented in
`prototype/local-service/README.md`. The Nutrient adapter boundary is in
`adapter/nutrient` and is consumed by the Vue host prototype.

The deterministic PHP lifecycle scenario is documented in
`docs/SCENARIO_RUNNER.md` and exercises finalization, Truth QR verification,
WORM evidence, recursive verification, tallying, and reports.

The deferred audit-ready evidence package and immediate next steps are tracked
in `docs/TODO.md`.
