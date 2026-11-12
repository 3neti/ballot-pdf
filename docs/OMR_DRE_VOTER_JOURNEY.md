# Voter Journey: OMR and OpScan with DRE

This document describes two distinct ballot profiles. OMR uses a marked paper
ballot as its input to scanning and interpretation. In the proposed OpScan with
DRE profile, the finalized ballot PDF is the **canonical electronic DRE voting
record and tally input**. This system term does not claim that the PDF replaces
the statutory paper “official ballot” or satisfies VVPAT requirements by
itself. See the [legal-position note](LEGAL_POSITION_DRE_CANONICAL_ELECTRONIC_RECORD.md).

## Shared election-day context

```mermaid
flowchart TD
    OPEN["Officers prepare and open the precinct"]
    VOTER["Voter journey: registration through completed submission"]
    NEXT{"Another voter admitted?"}
    CLOSE["Officers close the polls"]
    TALLY["Finalize the tally under the configured counting profile"]
    ER["Print Election Returns"]
    TX["Transmit Election Returns through the prescribed process"]

    OPEN --> VOTER --> NEXT
    NEXT -->|Yes| VOTER
    NEXT -->|No; closing conditions met| CLOSE
    CLOSE --> TALLY --> ER --> TX
```

## Side-by-side voter journey

```mermaid
%%{init: {"flowchart": {"htmlLabels": true, "nodeSpacing": 220, "rankSpacing": 45, "curve": "linear"}}}%%
flowchart TB
    OPEN["Officers open the precinct"]
    OMR["OMR<br/>Paper ballot"]
    DRE["OpScan with DRE<br/>PDF ballot"]
    OPEN --> OMR
    OPEN --> DRE

    subgraph S1["1. Arrive and register"]
        O1["Officer checks identity<br/>and registration"]
        D1["Officer checks identity<br/>and registration"]
    end
    subgraph S2["2. Receive ballot access"]
        O2["Receive a paper ballot"]
        D2["Receive a Voter Control Number"]
    end
    subgraph S3["3. Enter the private booth"]
        O3["Place ballot on<br/>the writing surface"]
        D3["Enter number on the fixed tablet<br/>Open the PDF ballot"]
    end
    subgraph S4["4. Choose candidates"]
        O4["Shade circles on paper"]
        D4["Tap circles on the PDF ballot"]
    end
    subgraph S5["5. Review and finalize"]
        O5["Review marked ballot<br/>Proceed to the scanner"]
        D5["Review selections and confirm<br/>Leave tablet in booth<br/>Keep the original control number"]
    end
    subgraph S6["6. Verify the record"]
        O6["Feed marked paper ballot into scanner<br/>Interpret marks under approved rules"]
        D6["Durably accept the signed ballot PDF<br/>Produce A4 voter-verification record<br/>Read back QR; voter inspects"]
    end
    subgraph S7["7. Custody and exit"]
        O7["Paper ballot retained in ballot box<br/>Voter leaves"]
        D7["Accepted PDF and evidence retained<br/>A4 record secured under approved custody<br/>Voter leaves"]
    end

    OMR --> O1 --> O2 --> O3 --> O4 --> O5 --> O6
    DRE --> D1 --> D2 --> D3 --> D4 --> D5 --> D6
    O6 --> O7
    D6 --> D7

    NEXT["Repeat for each next voter"]
    O7 --> NEXT
    D7 --> NEXT
    NEXT --> CLOSE["Officers close polls when closing conditions are met"]
    CLOSE --> TALLY["Finalize tally under the configured counting profile"]
    TALLY --> ER["Print Election Returns"]
    ER --> TX["Transmit Election Returns"]
```

## A4 voter-verification and audit path in the DRE bid profile

The offered OpScan with DRE solution includes an A4 human-readable record of
the voter's selections with a Truth QR encoding the corresponding authenticated
selections. The voter is given a private opportunity to inspect it. The QR is
read back to verify print correctness, and the paper is handled, sealed,
retained, and made available for audit under the approved procedure. COMELEC
and the issued procurement documents must designate the artifact's legal role
and approve the final procedure. The prototype's ability to skip printing is
not evidence that the proposed bid profile satisfies VVPAT requirements.

```mermaid
flowchart TD
    CONFIRM["Voter reviews and confirms selections on screen"]
    ACCEPT["Signed ballot PDF is durably accepted<br/>This is the casting event"]
    PRINT["Produce A4 human-readable voter-verification record with Truth QR"]
    PAPER["Read back QR and verify print correctness"]
    REVIEW["Voter privately inspects the selections"]
    MATCH{"Printed selections match the confirmed choices?"}
    CUSTODY["Secure, seal, retain, and provide audit access under approved procedure"]
    EXCEPTION["Pause normal completion<br/>Preserve evidence and follow the approved discrepancy procedure"]
    DONE["Complete the voter session<br/>Canonical electronic DRE record retained"]
    LEAVE["Voter leaves; station readied for the next voter"]

    CONFIRM --> ACCEPT --> PRINT --> PAPER --> REVIEW
    REVIEW --> MATCH
    MATCH -->|Yes| CUSTODY --> DONE
    MATCH -->|No| EXCEPTION
    PAPER -->|Unreadable or missing| EXCEPTION
    DONE --> LEAVE
```

## Voter needs at each stage

| Stage | Question the journey should answer |
|---|---|
| Arrive and register | Am I in the right place? |
| Receive ballot access | What do I take to the booth? |
| Enter the booth | Can I vote privately? |
| Choose candidates | Are these my choices? |
| Review and finalize | Am I ready to submit? |
| Verify the record | Does the record match my choices? |
| Complete and leave | Has my ballot PDF been durably accepted? |
