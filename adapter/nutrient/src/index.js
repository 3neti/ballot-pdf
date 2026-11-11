export class NutrientBallotViewer {
  constructor({ NutrientViewer, container, baseUrl, licenseKey }) {
    this.NutrientViewer = NutrientViewer
    this.container = container
    this.baseUrl = baseUrl
    this.licenseKey = licenseKey
    this.instance = null
  }

  async open({ document, password, onPasswordRequired }) {
    await this.NutrientViewer.preloadWorker({ baseUrl: this.baseUrl })
    this.instance = await this.NutrientViewer.load({
      container: this.container, document, baseUrl: this.baseUrl,
      licenseKey: this.licenseKey, password, onPasswordRequired,
    })
    return this.instance
  }

  async exportPdf() {
    if (!this.instance) throw new Error('Ballot viewer is not open.')
    return this.instance.exportPDF()
  }

  close() {
    if (this.instance) this.NutrientViewer.unload(this.container)
    this.instance = null
  }
}

export class NutrientBallotSession {
  constructor(protocol) { this.protocol = protocol }
  open(controlNumber, context) { return this.protocol.openSession({ controlNumber, ...context }) }
  update(state) { return this.protocol.updateSelectionState(state) }
  finalize(state) { return this.protocol.requestFinalization(state) }
  print(artifactId) { return this.protocol.requestPrint(artifactId) }
}

export class NutrientFormStateExporter {
  constructor(viewer) { this.viewer = viewer }
  export() { return this.viewer.exportPdf() }
}

export class NutrientFinalizationBridge {
  constructor(protocol) { this.protocol = protocol }
  finalize(state) { return this.protocol.requestFinalization(state) }
}

export class NutrientOfflineCapabilityCheck {
  constructor({ baseUrl, fetchImpl = globalThis.fetch }) { this.baseUrl = baseUrl; this.fetchImpl = fetchImpl }
  async check() {
    const response = await this.fetchImpl(`${this.baseUrl}nutrient-viewer.js`, { method: 'HEAD' })
    return { localAssets: response.ok, baseUrl: this.baseUrl }
  }
}

export class BallotPdfProtocolClient {
  constructor({ baseUrl = '', fetchImpl = globalThis.fetch?.bind(globalThis), cryptoImpl = globalThis.crypto } = {}) {
    this.baseUrl = baseUrl; this.fetchImpl = fetchImpl; this.crypto = cryptoImpl
  }

  async send(messageType, body = {}) {
    const envelope = {
      protocol: '3neti.ballot-pdf', version: '1.0', message_type: messageType,
      request_id: `REQ-${this.crypto.randomUUID().slice(0, 8)}`,
      nonce: this.crypto.randomUUID(), ...body,
    }
    if (!this.baseUrl) return { status: 'mock_acknowledged', ...envelope }
    const response = await this.fetchImpl(`${this.baseUrl}/${messageType}`, {
      method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify(envelope),
    })
    if (!response.ok) {
      let detail = ''
      try {
        const errorBody = await response.json()
        detail = errorBody.error_message ? `: ${errorBody.error_message}` : ''
      } catch {
        detail = ''
      }
      throw new Error(`Protocol ${messageType} failed with ${response.status}${detail}`)
    }
    return response.json()
  }

  openSession(body) { return this.send('session.open', body) }
  updateSelectionState(body) { return this.send('session.update', body) }
  requestFinalization(body) { return this.send('ballot.finalize', body) }
  requestPrint(body) { return this.send('ballot.print', body) }
  verifyEvidence(body) { return this.send('evidence.verify', body) }
  requestElectionReturn(body) { return this.send('election.return', body) }
  commitEvidence(body) { return this.send('evidence.commit', body) }
  readCeremonyStatus(body) { return this.send('ceremony.status', body) }
}
