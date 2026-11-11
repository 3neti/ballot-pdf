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

const pdf = await PDFDocument.create()
const page = pdf.addPage([612, 792])
const font = await pdf.embedFont(StandardFonts.Helvetica)
page.drawText('SYNTHETIC OFFICIAL BALLOT TEMPLATE', { x: 42, y: 744, size: 16, font, color: rgb(0.06, 0.12, 0.2) })
page.drawText('Public demonstration fixture. No legal effect.', { x: 42, y: 720, size: 10, font })
page.drawText('Election: DEMO-2028    Precinct: DEMO-PRECINCT-001', { x: 42, y: 690, size: 10, font })
page.drawText('Ballot style: DEMO-BS-001    Serial: PRECINCT-1-000001', { x: 42, y: 672, size: 10, font })
page.drawText('Senator: select up to two', { x: 42, y: 625, size: 13, font })

const form = pdf.getForm()
const senatorFields = []
for (const [index, candidate] of ['Candidate One', 'Candidate Two', 'Candidate Three'].entries()) {
  const y = 580 - index * 42
  page.drawText(candidate, { x: 78, y: y + 7, size: 12, font })
  const checkbox = form.createCheckBox(`senator.C${index + 1}`)
  checkbox.addToPage(page, { x: 48, y, width: 18, height: 18, borderWidth: 1 })
  senatorFields.push(checkbox)
}
page.drawText('Review and confirmation', { x: 42, y: 420, size: 13, font })
const review = form.createTextField('review.status')
review.setText('IN PROGRESS')
review.addToPage(page, { x: 42, y: 385, width: 210, height: 24, borderWidth: 1 })
page.drawText('Truth QR placement reserved', { x: 390, y: 94, size: 9, font })
page.drawRectangle({ x: 390, y: 110, width: 150, height: 150, borderWidth: 1, borderColor: rgb(0.4, 0.4, 0.4) })

pdf.addJavaScript('ballot-limit-enforcement', `
function enforceSenatorLimit() {
  var fields = [
    this.getField('senator.C1'),
    this.getField('senator.C2'),
    this.getField('senator.C3')
  ];
  var selected = 0;
  for (var i = 0; i < fields.length; i++) {
    if (fields[i] && (fields[i].value === 'Yes' || fields[i].value === true)) selected++;
  }
  if (selected > 2) {
    app.alert('Select no more than two senators.');
    return;
  }
  for (var j = 0; j < fields.length; j++) {
    if (fields[j]) fields[j].readonly = selected >= 2 && fields[j].value !== 'Yes' && fields[j].value !== true;
  }
}
enforceSenatorLimit();
`)
const limitAction = pdf.context.obj({
  S: PDFName.of('JavaScript'),
  JS: PDFString.of('enforceSenatorLimit();'),
})
for (const checkbox of senatorFields) {
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
