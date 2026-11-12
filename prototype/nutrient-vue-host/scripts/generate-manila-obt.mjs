import { PDFDocument, PDFName, PDFString } from 'pdf-lib'
import { execFileSync } from 'node:child_process'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const fixtureDir = resolve(here, '../public/fixtures')
const sourcePath = resolve(fixtureDir, 'obt-manila-first-district-2025-source.pdf')
const plainPath = resolve(fixtureDir, 'obt-manila-first-district-2025-automation.pdf')
const encryptedPath = resolve(fixtureDir, 'obt-manila-first-district-2025-automation-encrypted.pdf')
const manifestPath = resolve(fixtureDir, 'obt-manila-first-district-2025-manifest.json')
mkdirSync(fixtureDir, { recursive: true })

const contests = [
  { id: 'senator', code: 'SEN', max: 12, page: 0, yMin: 325, yMax: 730, punctuation: true, expected: 66 },
  { id: 'house', code: 'HOUSE', max: 1, page: 0, yMin: 780, yMax: 810, punctuation: true, expected: 4 },
  { id: 'mayor', code: 'MAYOR', max: 1, page: 0, yMin: 840, yMax: 930, punctuation: true, expected: 11 },
  { id: 'vice_mayor', code: 'VICE_MAYOR', max: 1, page: 0, yMin: 960, yMax: 1020, punctuation: true, expected: 7 },
  { id: 'councilor', code: 'COUNCILOR', max: 6, page: 0, yMin: 1050, yMax: 1325, punctuation: true, expected: 26 },
  { id: 'party_list', code: 'PARTY', max: 1, page: 1, yMin: 300, yMax: 1400, punctuation: false, expected: 155 },
]

function decodeHtml(value) {
  return value.replaceAll('&amp;', '&').replaceAll('&apos;', "'").replaceAll('&quot;', '"')
}

function pagesFromBbox(pdfPath) {
  const xml = execFileSync('pdftotext', ['-bbox-layout', pdfPath, '-'], { encoding: 'utf8' })
  return [...xml.matchAll(/<page width="([^"]+)" height="([^"]+)">([\s\S]*?)<\/page>/g)].map((match) => ({
    width: Number(match[1]),
    height: Number(match[2]),
    words: [...match[3].matchAll(/<word xMin="([^"]+)" yMin="([^"]+)" xMax="([^"]+)" yMax="([^"]+)">([^<]+)<\/word>/g)].map((word) => ({
      x: Number(word[1]),
      y: Number(word[2]),
      xMax: Number(word[3]),
      yMax: Number(word[4]),
      text: decodeHtml(word[5]),
    })),
  }))
}

const sourceHash = execFileSync('shasum', ['-a', '256', sourcePath], { encoding: 'utf8' }).split(/\s+/)[0]
const pages = pagesFromBbox(sourcePath)
const pdf = await PDFDocument.load(readFileSync(sourcePath), { updateMetadata: false })
const form = pdf.getForm()
const fieldDefinitions = []
const manifestContests = []

for (const contest of contests) {
  const pageInfo = pages[contest.page]
  const candidates = pageInfo.words
    .filter((word) => word.y >= contest.yMin && word.y <= contest.yMax)
    .filter((word) => contest.punctuation ? /^\d+\.$/.test(word.text) : /^\d+$/.test(word.text))
    .sort((a, b) => a.y - b.y || a.x - b.x)
    .map((word) => ({ number: Number(word.text.replace('.', '')), word }))

  if (candidates.length !== contest.expected) {
    throw new Error(`${contest.id}: expected ${contest.expected} candidate marks, found ${candidates.length}`)
  }

  const mapped = candidates.sort((a, b) => a.number - b.number).map(({ number, word }) => {
    const candidateCode = `${contest.code}-${String(number).padStart(3, '0')}`
    const fieldName = `contest.${contest.id}.candidate.${candidateCode}`
    const checkbox = form.createCheckBox(fieldName)
    const x = Math.max(0, word.x - 18)
    const y = pageInfo.height - word.yMax - 4
    checkbox.addToPage(pdf.getPages()[contest.page], {
      x,
      y,
      width: 18,
      height: 16,
      borderWidth: 0,
    })
    fieldDefinitions.push({ name: fieldName, contest: contest.id, candidateCode })
    return {
      candidate_code: candidateCode,
      candidate_number: number,
      field_name: fieldName,
      mark: {
        page: contest.page + 1,
        x: Number(x.toFixed(3)),
        y: Number(y.toFixed(3)),
        width: 18,
        height: 16,
        coordinate_system: 'pdf-points-from-bottom-left',
      },
    }
  })

  manifestContests.push({
    id: contest.id,
    code: contest.code,
    max_selections: contest.max,
    candidate_count: mapped.length,
    candidates: mapped,
  })
}

const javascript = `
function enforceContestLimits() {
  var definitions = ${JSON.stringify(contests.map((contest) => ({ id: contest.id, limit: contest.max, fields: fieldDefinitions.filter((field) => field.contest === contest.id).map((field) => field.name) })))};
  for (var c = 0; c < definitions.length; c++) {
    var definition = definitions[c];
    var selected = 0;
    var fields = [];
    for (var i = 0; i < definition.fields.length; i++) {
      var field = this.getField(definition.fields[i]);
      fields.push(field);
      if (field && (field.value === 'Yes' || field.value === true)) selected++;
    }
    for (var j = 0; j < fields.length; j++) {
      if (fields[j]) fields[j].readonly = selected >= definition.limit && fields[j].value !== 'Yes' && fields[j].value !== true;
    }
  }
}
enforceContestLimits();
`
pdf.addJavaScript('manila-obt-contest-limits', javascript)
const limitAction = pdf.context.obj({ S: PDFName.of('JavaScript'), JS: PDFString.of('enforceContestLimits();') })
for (const field of fieldDefinitions) {
  const checkbox = form.getCheckBox(field.name)
  checkbox.acroField.dict.set(PDFName.of('AA'), pdf.context.obj({ U: limitAction }))
  for (const widget of checkbox.acroField.getWidgets()) {
    widget.dict.set(PDFName.of('H'), PDFName.of('N'))
    widget.dict.set(PDFName.of('AA'), pdf.context.obj({ U: limitAction }))
  }
}
pdf.setTitle('Automation-ready OBT - Manila First District 2025')
pdf.setSubject('DRE ballot preparation fixture derived from the supplied OBT')
pdf.setKeywords(['OBT', 'DRE', 'ballot preparation', 'Manila', '2025'])
form.updateFieldAppearances()
writeFileSync(plainPath, await pdf.save({ useObjectStreams: false }))
execFileSync('qpdf', ['--encrypt', 'OBT-MANILA-000001', 'demo-owner', '256', '--', plainPath, encryptedPath])

const manifest = {
  schema_version: 'automation-ready-obt-1',
  election_id: '2025NLE',
  ballot_style_id: 'MANILA-1ST-DISTRICT-2025',
  source_obt: {
    file: 'obt-manila-first-district-2025-source.pdf',
    sha256: sourceHash,
    source_precinct_id: '39010001',
    provenance: 'Supplied COMELEC ballot PDF; preserved unchanged as the visual OBT source.',
  },
  interaction_model: 'DRE-field-selection',
  paper_record: 'controlling-legal-record',
  contests: manifestContests,
  field_count: fieldDefinitions.length,
  control_number_demo_password: 'OBT-MANILA-000001',
  generated_artifacts: {
    automation_pdf: 'obt-manila-first-district-2025-automation.pdf',
    encrypted_serial_fixture: 'obt-manila-first-district-2025-automation-encrypted.pdf',
  },
}
writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`)
console.log(`Generated ${encryptedPath}`)
console.log(`Manifest ${manifestPath}`)
console.log(`Source SHA-256 ${sourceHash}`)
console.log(`Fields ${fieldDefinitions.length}`)
console.log(`Encrypted SHA-256 ${execFileSync('shasum', ['-a', '256', encryptedPath], { encoding: 'utf8' }).split(/\s+/)[0]}`)
