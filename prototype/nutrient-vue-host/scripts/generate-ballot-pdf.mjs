import { PDFDocument, PDFName, PDFString, StandardFonts, rgb } from 'pdf-lib'
import { execFileSync } from 'node:child_process'
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const fixtureDir = resolve(here, '../public/fixtures')
const plainPath = resolve(fixtureDir, 'obt-synthetic-fillable.pdf')
const encryptedPath = resolve(fixtureDir, 'obt-synthetic-encrypted.pdf')
mkdirSync(fixtureDir, { recursive: true })

const contests = [
  { id: 'senator', office: 'Senator', limit: 2, candidates: Array.from({ length: 12 }, (_, i) => [`S${String(i + 1).padStart(2, '0')}`, `Candidate ${String(i + 1).padStart(2, '0')}`]) },
  { id: 'mayor', office: 'Mayor', limit: 1, candidates: [['M01', 'Mayor Candidate 01'], ['M02', 'Mayor Candidate 02'], ['M03', 'Mayor Candidate 03'], ['M04', 'Mayor Candidate 04']] },
  { id: 'councilor', office: 'Councilor', limit: 4, candidates: Array.from({ length: 8 }, (_, i) => [`K${String(i + 1).padStart(2, '0')}`, `Councilor Candidate ${String(i + 1).padStart(2, '0')}`]) },
  { id: 'party_list', office: 'Party List', limit: 1, candidates: [['P01', 'Party List A'], ['P02', 'Party List B'], ['P03', 'Party List C'], ['P04', 'Party List D']] },
]

const pdf = await PDFDocument.create()
const font = await pdf.embedFont(StandardFonts.Helvetica)
const bold = await pdf.embedFont(StandardFonts.HelveticaBold)
const black = rgb(0.08, 0.08, 0.08)
const gray = rgb(0.35, 0.35, 0.35)
const light = rgb(0.96, 0.96, 0.94)
const blue = rgb(0.05, 0.22, 0.42)
const form = pdf.getForm()
const fieldNames = []

function text(page, value, x, y, size = 9, typeface = font, color = black) {
  page.drawText(value, { x, y, size, font: typeface, color })
}

function drawContest(page, contest, topY) {
  const left = 38
  const width = 536
  const headerH = 26
  const rowH = 22
  const columns = contest.candidates.length > 8 ? 3 : 2
  const columnWidth = width / columns
  const rows = Math.ceil(contest.candidates.length / columns)
  const height = headerH + 24 + rows * rowH + 8

  page.drawRectangle({ x: left, y: topY - height, width, height, borderWidth: 1, borderColor: black, color: rgb(1, 1, 1) })
  page.drawRectangle({ x: left, y: topY - headerH, width, height: headerH, color: blue })
  text(page, `${contest.office.toUpperCase()} / VOTE FOR ${contest.limit}`, left + 10, topY - 17, 10, bold, rgb(1, 1, 1))
  text(page, `Marked: 0 / ${contest.limit}`, left + width - 92, topY - 17, 8, bold, rgb(1, 1, 1))
  text(page, 'Completely mark the circle beside the desired candidate.', left + 8, topY - headerH - 15, 7, font, gray)
  text(page, `Select up to ${contest.limit}`, left + width - 86, topY - headerH - 15, 7, bold, gray)

  contest.candidates.forEach(([code, name], index) => {
    const column = Math.floor(index / rows)
    const row = index % rows
    const x = left + column * columnWidth
    const y = topY - headerH - 38 - row * rowH
    if (row % 2 === 0) page.drawRectangle({ x: x + 1, y: y - 4, width: columnWidth - 2, height: rowH, color: light })
    text(page, String(index + 1).padStart(2, '0'), x + 7, y + 3, 7, bold, gray)
    const checkbox = form.createCheckBox(`${contest.id}.${code}`)
    checkbox.addToPage(page, { x: x + 31, y: y - 1, width: 13, height: 13, borderWidth: 1, borderColor: black })
    fieldNames.push({ name: `${contest.id}.${code}` })
    text(page, name.toUpperCase(), x + 51, y + 3, 7.5, bold, black)
  })

  return topY - height - 12
}

let page = pdf.addPage([612, 792])
let y = 750
text(page, 'REPUBLIC OF THE PHILIPPINES', 38, y, 9, bold)
text(page, 'DEMONSTRATION ELECTION', 38, y - 16, 8, font, gray)
text(page, 'OFFICIAL BALLOT', 38, y - 43, 18, bold, blue)
text(page, 'OPSCAN WITH DRE / BALLOT PREPARATION PROTOTYPE', 38, y - 59, 7, bold, gray)
page.drawRectangle({ x: 38, y: y - 113, width: 536, height: 40, borderWidth: 1, borderColor: black })
text(page, 'Election: DEMO-2028', 48, y - 88, 8, bold)
text(page, 'Precinct: DEMO-PRECINCT-001', 205, y - 88, 8, bold)
text(page, 'Ballot style: DEMO-BS-001', 400, y - 88, 8, bold)
text(page, 'Ballot serial: assigned from approved electronic stock', 48, y - 102, 7, font, gray)
text(page, 'Public demonstration fixture. No legal effect. Deposited paper remains the controlling record.', 38, y - 129, 7, bold, gray)
text(page, 'INSTRUCTIONS', 38, y - 157, 9, bold, blue)
text(page, 'Review each contest. Select no more than the stated limit. Use the review field before confirmation.', 38, y - 172, 8, font, black)
text(page, 'A finalized copy is printed and receives a signed Truth QR at the local finalization service.', 38, y - 185, 7, font, gray)
y -= 210

for (const contest of contests) {
  if (y < 180) {
    page = pdf.addPage([612, 792])
    y = 750
    text(page, 'DEMONSTRATION ELECTION / OFFICIAL BALLOT', 38, y, 10, bold, blue)
    text(page, 'Continuation page - public demonstration fixture. No legal effect.', 38, y - 16, 7, font, gray)
    y -= 42
  }
  y = drawContest(page, contest, y)
}

if (y < 150) {
  page = pdf.addPage([612, 792])
  y = 750
  text(page, 'REVIEW AND CONFIRMATION', 38, y, 14, bold, blue)
  y -= 36
}
text(page, 'REVIEW AND CONFIRMATION', 38, y, 12, bold, blue)
text(page, 'Confirm only after reviewing all selections. The local service performs authoritative finalization.', 38, y - 17, 8, font, gray)
const review = form.createTextField('review.status')
review.setText('IN PROGRESS')
review.addToPage(page, { x: 38, y: y - 60, width: 220, height: 24, borderWidth: 1, borderColor: black })
text(page, 'Status', 38, y - 70, 7, bold, gray)
const confirm = form.createCheckBox('review.confirmed')
confirm.addToPage(page, { x: 284, y: y - 60, width: 18, height: 18, borderWidth: 1, borderColor: black })
text(page, 'Voter confirms selections', 311, y - 54, 8, bold)
page.drawRectangle({ x: 390, y: y - 105, width: 150, height: 88, borderWidth: 1, borderColor: gray })
text(page, 'TRUTH QR PLACEMENT', 405, y - 33, 7, bold, gray)
text(page, 'Added after finalization', 407, y - 48, 7, font, gray)
text(page, 'Paper ballot / legal record', 407, y - 89, 7, bold, black)

pdf.addJavaScript('ballot-limit-enforcement', `
function enforceContestLimits() {
  var contests = ${JSON.stringify(contests.map((contest) => ({ id: contest.id, limit: contest.limit, fields: contest.candidates.map(([code]) => `${contest.id}.${code}`) })))};
  for (var c = 0; c < contests.length; c++) {
    var definition = contests[c];
    var fields = [];
    var selected = 0;
    for (var i = 0; i < definition.fields.length; i++) {
      var field = this.getField(definition.fields[i]);
      fields.push(field);
      if (field && (field.value === 'Yes' || field.value === true)) selected++;
    }
    if (selected > definition.limit) {
      app.alert('Select no more than ' + definition.limit + ' choice(s) for ' + definition.id + '.');
      return;
    }
    for (var j = 0; j < fields.length; j++) {
      if (fields[j]) fields[j].readonly = selected >= definition.limit && fields[j].value !== 'Yes' && fields[j].value !== true;
    }
  }
}
enforceContestLimits();
`)
const limitAction = pdf.context.obj({ S: PDFName.of('JavaScript'), JS: PDFString.of('enforceContestLimits();') })
for (const field of fieldNames) {
  const checkbox = form.getCheckBox(field.name)
  checkbox.acroField.dict.set(PDFName.of('AA'), pdf.context.obj({ U: limitAction }))
  for (const widget of checkbox.acroField.getWidgets()) {
    widget.dict.set(PDFName.of('H'), PDFName.of('N'))
    widget.dict.set(PDFName.of('AA'), pdf.context.obj({ U: limitAction }))
  }
}
form.updateFieldAppearances(font)
writeFileSync(plainPath, await pdf.save())
execFileSync('qpdf', ['--encrypt', 'DEMO-000001', 'demo-owner', '256', '--', plainPath, encryptedPath])
rmSync(plainPath)
console.log(`Generated ${encryptedPath}`)
console.log(`SHA-256 ${execFileSync('shasum', ['-a', '256', encryptedPath], { encoding: 'utf8' }).trim()}`)
console.log(`Bytes ${readFileSync(encryptedPath).byteLength}`)
