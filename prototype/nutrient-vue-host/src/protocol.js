import { BallotPdfProtocolClient as AdapterProtocolClient } from '@3neti/ballot-pdf-nutrient'

const baseUrl = import.meta.env.VITE_PROTOCOL_BASE_URL

function syntheticId(prefix) {
  return `${prefix}-${crypto.randomUUID().slice(0, 8)}`
}

export class BallotPdfProtocolClient extends AdapterProtocolClient {
  constructor() {
    super({ baseUrl })
  }

  openSession({ controlNumber, obtHash }) {
    return this.send('session.open', {
      session_id: syntheticId('SESSION'),
      election_id: import.meta.env.VITE_ELECTION_ID || 'DEMO-2028',
      precinct_id: import.meta.env.VITE_PRECINCT_ID || 'DEMO-PRECINCT-001',
      obt_hash: obtHash,
      control_number_present: Boolean(controlNumber),
    })
  }

  finalize({ sessionId, controlNumber, pdfBytes, pdfBase64, selections, ballotSerial, electionId, precinctId, ballotStyleId, mappingHash }) {
    return this.requestFinalization({
      session_id: sessionId,
      control_number: controlNumber,
      pdf_bytes: pdfBytes,
      pdf_base64: pdfBase64,
      selections,
      ballot_serial: ballotSerial,
      election_id: electionId,
      precinct_id: precinctId,
      ballot_style_id: ballotStyleId,
      mapping_hash: mappingHash,
      status: 'awaiting-local-ballot-pdf-finalizer',
    })
  }

  updateSelectionState({ sessionId, selections }) {
    return super.updateSelectionState({ session_id: sessionId, selections })
  }

  print({ sessionId }) {
    return this.requestPrint({
      session_id: sessionId,
      status: 'awaiting-local-printer-adapter',
    })
  }
}
