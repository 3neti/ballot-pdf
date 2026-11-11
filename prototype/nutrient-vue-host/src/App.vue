<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import NutrientViewer from '@nutrient-sdk/viewer'
import { BallotPdfProtocolClient } from './protocol'

const viewerContainer = ref(null)
const controlNumber = ref('DEMO-000001')
const status = ref('Awaiting control number')
const error = ref('')
const events = ref([])
const session = ref(null)
const finalized = ref(false)
const finalizedArtifact = ref(null)
const finalizedPdfUrl = ref('')
const evidenceVisible = ref(false)
const verification = ref(null)
const viewer = ref(null)
const protocol = new BallotPdfProtocolClient()
const protocolMode = import.meta.env.VITE_PROTOCOL_BASE_URL ? 'local service' : 'mock'
const nutrientBaseUrl = `${window.location.origin}/nutrient-web/`

const canFinalize = computed(() => Boolean(viewer.value && session.value && !finalized.value))

function record(type, detail = {}) {
  events.value.unshift({
    at: new Date().toLocaleTimeString(),
    type,
    detail: JSON.stringify(detail),
  })
}

function base64FromBuffer(buffer) {
  let binary = ''
  for (const byte of new Uint8Array(buffer)) binary += String.fromCharCode(byte)
  return btoa(binary)
}

function pdfUrlFromBase64(base64) {
  const binary = atob(base64)
  const bytes = Uint8Array.from(binary, (character) => character.charCodeAt(0))
  return URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }))
}

function clearFinalizedArtifact() {
  if (finalizedPdfUrl.value) URL.revokeObjectURL(finalizedPdfUrl.value)
  finalizedPdfUrl.value = ''
  finalizedArtifact.value = null
  evidenceVisible.value = false
  verification.value = null
}

function selectionsFromForm() {
  const values = viewer.value.getFormFieldValues()
  return {
    senator: Object.entries(values)
      .filter(([name, value]) => name.startsWith('senator.') && (value === 'Yes' || value === true || (Array.isArray(value) && value.includes('Yes'))))
      .map(([name]) => name.split('.')[1]),
  }
}

async function openBallot() {
  error.value = ''
  status.value = 'Opening local ballot protocol session...'

  try {
    session.value = await protocol.openSession({
      controlNumber: controlNumber.value,
      obtHash: import.meta.env.VITE_OBT_HASH || 'synthetic-obt-hash',
    })
    finalized.value = false
    clearFinalizedArtifact()
    record('session.open', session.value)

    await NutrientViewer.preloadWorker({ baseUrl: nutrientBaseUrl })

    viewer.value = await NutrientViewer.load({
      container: viewerContainer.value,
      document: import.meta.env.VITE_OBT_URL || '/fixtures/obt-synthetic-encrypted.pdf',
      baseUrl: nutrientBaseUrl,
      licenseKey: import.meta.env.VITE_NUTRIENT_LICENSE_KEY || undefined,
      password: controlNumber.value,
      onPasswordRequired: async () => controlNumber.value,
    })

    status.value = 'Ballot PDF open'
    record('viewer.ready', { sessionId: session.value.session_id })
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : String(cause)
    status.value = 'Unable to open ballot PDF'
    record('error', { message: error.value })
  }
}

async function finalize() {
  if (!canFinalize.value) return

  status.value = 'Exporting completed PDF to local finalization protocol...'
  try {
    const buffer = await viewer.value.exportPDF()
    const selections = selectionsFromForm()
    const result = await protocol.finalize({
      sessionId: session.value.session_id,
      controlNumber: controlNumber.value,
      pdfBytes: buffer.byteLength,
      pdfBase64: base64FromBuffer(buffer),
      selections,
      ballotSerial: session.value.ballot_serial,
      electionId: import.meta.env.VITE_ELECTION_ID || 'DEMO-2028',
      precinctId: import.meta.env.VITE_PRECINCT_ID || 'DEMO-PRECINCT-001',
      ballotStyleId: import.meta.env.VITE_BALLOT_STYLE_ID || 'DEMO-BS-001',
      mappingHash: import.meta.env.VITE_MAPPING_HASH || 'demo-mapping-hash',
    })
    status.value = 'Finalization protocol acknowledged'
    finalized.value = true
    finalizedArtifact.value = result
    finalizedPdfUrl.value = pdfUrlFromBase64(result.pdf_base64)
    const { pdf_base64: _pdfBase64, ...resultSummary } = result
    record('ballot.finalize', { ...resultSummary, pdf_bytes: buffer.byteLength })
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : String(cause)
    status.value = 'Finalization failed'
    record('error', { message: error.value })
  }
}

async function verifyEvidence() {
  if (!session.value || !finalizedArtifact.value) return
  status.value = 'Verifying finalized PDF and evidence recursively...'
  try {
    verification.value = await protocol.verify({
      sessionId: session.value.session_id,
      ballotSerial: finalizedArtifact.value.ballot_serial,
      electionId: import.meta.env.VITE_ELECTION_ID || 'DEMO-2028',
      precinctId: import.meta.env.VITE_PRECINCT_ID || 'DEMO-PRECINCT-001',
      ballotStyleId: import.meta.env.VITE_BALLOT_STYLE_ID || 'DEMO-BS-001',
      mappingHash: import.meta.env.VITE_MAPPING_HASH || 'demo-mapping-hash',
    })
    status.value = 'Evidence verification complete'
    record('evidence.verify', verification.value)
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : String(cause)
    status.value = 'Evidence verification failed'
    record('error', { message: error.value })
  }
}

async function print() {
  if (!session.value) return
  const result = await protocol.print({ sessionId: session.value.session_id })
  status.value = 'Print request acknowledged'
  record('ballot.print', result)
}

function closeBallot() {
  if (viewer.value) {
    NutrientViewer.unload(viewerContainer.value)
    viewer.value = null
  }
  session.value = null
  finalized.value = false
  clearFinalizedArtifact()
  status.value = 'Awaiting control number'
  record('session.close')
}

onBeforeUnmount(closeBallot)
</script>

<template>
  <main class="shell">
    <header class="topbar">
      <div>
        <p class="eyebrow">Ballot PDF Prototype</p>
        <h1>Nutrient ACM Host</h1>
      </div>
      <span class="status" :class="{ ready: status === 'Ballot PDF open' }">{{ status }}</span>
    </header>

    <section class="control-strip" aria-label="Ballot session controls">
      <label>
        Control number
        <input v-model="controlNumber" :disabled="Boolean(session)" autocomplete="off" />
      </label>
      <button type="button" :disabled="Boolean(session) || !controlNumber" @click="openBallot">Open ballot PDF</button>
      <button type="button" class="secondary" :disabled="!canFinalize" @click="finalize">Finalize</button>
      <a v-if="finalizedPdfUrl" class="button secondary" :href="finalizedPdfUrl" :download="`${finalizedArtifact.ballot_serial}-finalized.pdf`">Download finalized PDF</a>
      <button type="button" class="secondary" :disabled="!finalizedArtifact" @click="evidenceVisible = !evidenceVisible">{{ evidenceVisible ? 'Hide evidence' : 'View evidence' }}</button>
      <button type="button" class="secondary" :disabled="!finalizedArtifact" @click="verifyEvidence">Verify evidence</button>
      <button type="button" class="secondary" :disabled="!session" @click="print">Print</button>
      <button type="button" class="quiet" :disabled="!session" @click="closeBallot">Close</button>
    </section>

    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section v-if="finalizedArtifact && evidenceVisible" class="evidence-panel" aria-label="Finalized ballot evidence">
      <div>
        <p class="eyebrow">Finalized artifact</p>
        <h2>Evidence summary</h2>
      </div>
      <dl>
        <div><dt>Ballot serial</dt><dd>{{ finalizedArtifact.ballot_serial }}</dd></div>
        <div><dt>PDF SHA-256</dt><dd><code>{{ finalizedArtifact.pdf_sha256 }}</code></dd></div>
        <div><dt>Evidence manifest SHA-256</dt><dd><code>{{ finalizedArtifact.evidence_manifest_sha256 }}</code></dd></div>
        <div><dt>Truth QR read-back</dt><dd>{{ finalizedArtifact.truth_qr_scans?.length ?? 0 }} scan(s)</dd></div>
        <div v-if="verification"><dt>Recursive verification</dt><dd>{{ verification.target_results?.[0]?.classification || 'No matching result' }}; {{ verification.recursive_result_count }} artifact(s) scanned</dd></div>
        <div v-if="verification"><dt>Verification report SHA-256</dt><dd><code>{{ verification.report_sha256 }}</code></dd></div>
      </dl>
    </section>

    <section class="workbench">
      <div ref="viewerContainer" class="viewer" aria-label="Nutrient ballot PDF viewer"></div>
      <aside class="events" aria-label="Protocol event log">
        <div class="events-heading">
          <div>
            <p class="eyebrow">Local protocol</p>
            <h2>Event log</h2>
          </div>
          <code>{{ protocolMode }}</code>
        </div>
        <p v-if="events.length === 0" class="empty">No protocol events yet.</p>
        <ol v-else>
          <li v-for="event in events" :key="`${event.at}-${event.type}`">
            <strong>{{ event.type }}</strong>
            <time>{{ event.at }}</time>
            <code>{{ event.detail }}</code>
          </li>
        </ol>
      </aside>
    </section>
  </main>
</template>
