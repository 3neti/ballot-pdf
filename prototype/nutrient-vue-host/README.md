# Nutrient Vue Host Prototype

This is an isolated Nutrient Web SDK host for the Ballot PDF research work.
It does not implement election-domain logic and does not modify the core
`3neti/ballot-pdf` package.

## Setup

```bash
npm install
npm run fixture:generate
npm run prepare
npm run dev
```

The prototype uses `@nutrient-sdk/viewer`. Set
`VITE_NUTRIENT_LICENSE_KEY` in a local `.env` file when a trial or licensed
key is available. Do not commit `.env` files or keys.

Optional environment variables:

```text
VITE_NUTRIENT_LICENSE_KEY=local-only-key
VITE_OBT_URL=/fixtures/obt-synthetic-encrypted.pdf
VITE_OBT_HASH=synthetic-obt-hash
VITE_PROTOCOL_BASE_URL=http://127.0.0.1:8017
```

Without `VITE_PROTOCOL_BASE_URL`, the protocol client uses an in-browser mock
acknowledgement so the viewer flow can be demonstrated before the local PHP
service exists.

The development control number for the synthetic fixture is:

```text
DEMO-000001
```

This prototype uses synthetic data only.

The fixture also includes Acrobat document JavaScript for the demonstration
contest limit. Acrobat/Reader must permit document JavaScript for that client
side behavior; the local finalization service remains authoritative.

The fixture generator uses `pdf-lib` to create a real AcroForm ballot and
`qpdf` to apply AES-256 PDF encryption. The development control number for
the generated fixture is `DEMO-000001`.

The synthetic OBT is a two-page role-demo-style facsimile with four contest
panels, contest-limited AcroForm controls, review/confirmation fields, and
reserved Truth QR placement. It remains demonstration-only.

## Manila OBT Fixture

The Manila fixture preserves the supplied OBT PDF as an immutable visual source
and generates an automation-ready derivative with one named DRE field per
candidate mark. The field mapping is recorded in the adjacent manifest and uses
candidate numbers as stable demonstration codes. The source PDF is not changed.

```bash
npm run fixture:manila
qpdf --check --password=OBT-MANILA-000001 public/fixtures/obt-manila-first-district-2025-automation-encrypted.pdf
```

The fixture password is `OBT-MANILA-000001`. This is demonstration material
only; the paper ballot remains the controlling legal record. The old EMS
currently assumes an eight-contest package, while this supplied Manila OBT
contains six contests. A future EMS export should emit the six-contest manifest
directly rather than relying on this source-derived compatibility mapping.
