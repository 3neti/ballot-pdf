import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { resolve } from 'node:path'

const root = resolve(import.meta.dirname, '..')
const fixture = resolve(root, 'public/fixtures/obt-synthetic-encrypted.pdf')

function qpdfJson() {
  const output = execFileSync('qpdf', ['--warning-exit-0', '--password=DEMO-000001', '--json', fixture], { encoding: 'utf8' })
  return JSON.parse(output)
}

test('ballot fixture is encrypted and structurally valid', () => {
  const check = execFileSync('qpdf', ['--warning-exit-0', '--password=DEMO-000001', '--check', fixture], { encoding: 'utf8' })
  assert.match(check, /No syntax or stream encoding errors found/)
})

test('ballot fixture embeds click-triggered two-selection enforcement', () => {
  const document = qpdfJson()
  const serialized = JSON.stringify(document)
  assert.equal((serialized.match(/"\/H":"\/N"/g) ?? []).length, 3)
  assert.ok((serialized.match(/"\/AA"/g) ?? []).length >= 3)
  assert.match(serialized, /fields\[i\]\.value === 'Yes'/)
  assert.match(serialized, /fields\[j\]\.readonly = selected >= 2/)
  assert.doesNotMatch(serialized, /\.highlight\s*=/)
  assert.match(serialized, /enforceSenatorLimit\(\);/)
})

test('two-selection rule rejects a third candidate in the reference model', () => {
  const selected = new Set()
  const select = (candidate) => {
    if (!selected.has(candidate) && selected.size >= 2) return false
    selected.add(candidate)
    return true
  }

  assert.equal(select('C1'), true)
  assert.equal(select('C2'), true)
  assert.equal(select('C3'), false)
  assert.deepEqual([...selected], ['C1', 'C2'])
})

test('fixture source remains reproducible and contains no live credentials', () => {
  const source = readFileSync(resolve(root, 'scripts/generate-ballot-pdf.mjs'), 'utf8')
  assert.match(source, /DEMO-000001/)
  assert.doesNotMatch(source, /VITE_|sk-|prod|private[_-]?key/i)
})
