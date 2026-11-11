import { PDFDocument } from '../nutrient-vue-host/node_modules/pdf-lib/dist/pdf-lib.esm.js'
import { readFileSync, writeFileSync } from 'node:fs'

const [, , inputPath, outputPath, qrPath] = process.argv
if (!inputPath || !outputPath || !qrPath) throw new Error('Usage: node materialize-finalized-pdf.mjs input output qr')

const pdf = await PDFDocument.load(readFileSync(inputPath))
const qr = await pdf.embedPng(readFileSync(qrPath))
const page = pdf.getPages()[0]
page.drawImage(qr, { x: 390, y: 110, width: 150, height: 150 })
const form = pdf.getForm()
form.getTextField('review.status').setText('FINALIZED')
form.flatten()
pdf.setTitle('Synthetic Finalized Ballot PDF')
pdf.setSubject('Demonstration artifact; paper ballot remains the controlling legal record.')
writeFileSync(outputPath, await pdf.save())
