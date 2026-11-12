import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { resolve } from 'node:path'

const root = resolve(import.meta.dirname, '..')
const manifestPath = resolve(root, 'public/fixtures/obt-manila-first-district-2025-manifest.json')
const sourceHash = '76b8fd0108c1f1534fcdc6e4522f3fe97f36f9ad0c6d1f3f101bc985750baeef'

test('Manila automation-ready OBT has deterministic source binding and contest mapping', () => {
  const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'))
  assert.equal(manifest.source_obt.sha256, sourceHash)
  assert.equal(manifest.interaction_model, 'DRE-field-selection')
  assert.equal(manifest.paper_record, 'controlling-legal-record')
  assert.equal(manifest.field_count, 269)
  assert.deepEqual(
    manifest.contests.map((contest) => [contest.id, contest.max_selections, contest.candidate_count]),
    [
      ['senator', 12, 66],
      ['house', 1, 4],
      ['mayor', 1, 11],
      ['vice_mayor', 1, 7],
      ['councilor', 6, 26],
      ['party_list', 1, 155],
    ],
  )
  assert.equal(manifest.contests[0].candidates[0].field_name, 'contest.senator.candidate.SEN-001')
  assert.equal(manifest.contests[0].candidates[0].candidate_code, 'SEN-001')
})
