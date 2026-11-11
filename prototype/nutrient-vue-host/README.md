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
