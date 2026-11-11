import { describe, expect, it, vi } from 'vitest'
import { BallotPdfProtocolClient, NutrientOfflineCapabilityCheck } from '@3neti/ballot-pdf-nutrient'

describe('ballot PDF protocol adapter', () => {
  it('sends real finalization bytes and selections in the protocol envelope', async () => {
    const fetchImpl = vi.fn(async (_url, options) => ({
      ok: true,
      json: async () => JSON.parse(options.body),
    }))
    const client = new BallotPdfProtocolClient({
      baseUrl: 'http://127.0.0.1:8017',
      fetchImpl,
      cryptoImpl: { randomUUID: () => 'fixed-id' },
    })

    const response = await client.requestFinalization({
      session_id: 'SESSION-1',
      pdf_base64: 'JVBERi0=',
      selections: { senator: ['C1', 'C2'] },
    })

    expect(fetchImpl).toHaveBeenCalledOnce()
    expect(JSON.parse(fetchImpl.mock.calls[0][1].body)).toMatchObject({
      message_type: 'ballot.finalize',
      session_id: 'SESSION-1',
      pdf_base64: 'JVBERi0=',
      selections: { senator: ['C1', 'C2'] },
    })
    expect(response.message_type).toBe('ballot.finalize')
  })

  it('checks locally provisioned Nutrient assets', async () => {
    const check = new NutrientOfflineCapabilityCheck({
      baseUrl: 'http://127.0.0.1:5173/nutrient-web/',
      fetchImpl: vi.fn(async () => ({ ok: true })),
    })
    await expect(check.check()).resolves.toEqual({
      localAssets: true,
      baseUrl: 'http://127.0.0.1:5173/nutrient-web/',
    })
  })
})
